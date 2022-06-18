<?php

namespace Itx\Typo3GraphQL\Resolver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Schema\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class QueryResolver
{
    protected PersistenceManager $persistenceManager;

    public function __construct(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @throws NotFoundException
     */
    public function fetchSingleRecord($root, array $args, $context, ResolveInfo $resolveInfo, string $modelClassPath): array
    {
        $uid = (int)$args['uid'];
        $language = (int)($root['sys_language_uid'] ?? 0);

        $query = $this->persistenceManager->createQueryForType($modelClassPath);
        $query->getQuerySettings()->setRespectStoragePage(false)->setLanguageUid($language)->setLanguageOverlayMode(true);

        $query->matching($query->equals('uid', $uid));

        $result = $query->execute(true)[0] ?? null;
        if ($result === null) {
            throw new NotFoundException("No result for $modelClassPath with uid $uid found");
        }

        return $result;
    }

    public function fetchMultipleRecords($root, array $args, $context, ResolveInfo $resolveInfo, string $modelClassPath): array
    {
        $language = (int)($root['sys_language_uid'] ?? 0);
        $storagePids = (array)($args['storages'] ?? []);

        // TODO we can fetch only the field that we need by using the resolveInfo, but we need to make sure that the repository logic is kept
        $query = $this->persistenceManager->createQueryForType($modelClassPath);

        if (count($storagePids) === 0) {
            $query->getQuerySettings()->setRespectStoragePage(false);
        } else {
            $query->getQuerySettings()->setRespectStoragePage(true)->setStoragePageIds($storagePids);
        }

        $query->getQuerySettings()->setLanguageUid($language)->setLanguageOverlayMode(true);

        return $query->execute(true);
    }

    /**
     * @throws NotFoundException
     */
    public function fetchForeignRecord($root, array $args, $context, ResolveInfo $resolveInfo, Context $schemaContext): array
    {
        $tableName = $schemaContext->getTableName();
        $foreignUid = $root[$resolveInfo->fieldName];
        // TODO: maybe improve on this
        $language = (int)($root['sys_language_uid'] ?? 0);

        $modelClassPath = $schemaContext->getTypeRegistry()
                                        ->getModelClassPathByTableName($GLOBALS['TCA'][$tableName]['columns'][$resolveInfo->fieldName]['config']['foreign_table']);

        $query = $this->persistenceManager->createQueryForType($modelClassPath);
        $query->getQuerySettings()->setRespectStoragePage(false)->setLanguageUid($language)->setLanguageOverlayMode(true);

        $query->matching($query->equals('uid', $foreignUid));

        $result = $query->execute(true)[0] ?? null;
        if ($result === null) {
            throw new NotFoundException("No result for $modelClassPath with uid $foreignUid found");
        }

        return $result;
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    public function fetchForeignRecordWithMM($root, array $args, $context, ResolveInfo $resolveInfo, Context $schemaContext): array
    {
        $tableName = $schemaContext->getTableName();
        $foreignUid = $root[$resolveInfo->fieldName];

        $table = $GLOBALS['TCA'][$tableName]['columns'][$resolveInfo->fieldName]['config']['foreign_table'];
        $mm = $GLOBALS['TCA'][$tableName]['columns'][$resolveInfo->fieldName]['config']['MM'];

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $qb = $connectionPool->getQueryBuilderForTable($tableName);

        foreach ($resolveInfo->getFieldSelection() as $field => $_) {
            $qb->addSelect($field);
        }

        $qb->from($table, 'o')->leftJoin('o', $mm, 'm', $qb->expr()->eq('o.uid', 'm.uid_local'))->andWhere($qb->expr()
                                                                                                              ->eq('m.uid_foreign', $foreignUid));

        $qb->andWhere();

        return $qb->execute()->fetchAllAssociative();
    }
}
