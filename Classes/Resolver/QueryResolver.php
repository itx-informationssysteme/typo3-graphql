<?php

namespace Itx\Typo3GraphQL\Resolver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Schema\Context;
use Itx\Typo3GraphQL\Service\ConfigurationService;
use Itx\Typo3GraphQL\Utility\PaginationUtility;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class QueryResolver
{
    protected PersistenceManager $persistenceManager;
    protected FileRepository $fileRepository;
    protected ConfigurationService $configurationService;

    public function __construct(PersistenceManager $persistenceManager, FileRepository $fileRepository, ConfigurationService $configurationService)
    {
        $this->persistenceManager = $persistenceManager;
        $this->fileRepository = $fileRepository;
        $this->configurationService = $configurationService;
    }

    /**
     * @throws NotFoundException
     */
    public function fetchSingleRecord($root, array $args, $context, ResolveInfo $resolveInfo, string $modelClassPath): array
    {
        $uid = (int)$args[QueryArgumentsUtility::$uid];
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);

        $query = $this->persistenceManager->createQueryForType($modelClassPath);

        $languageOverlayMode = $this->configurationService->getModels()[$modelClassPath]['languageOverlayMode'] ?? true;
        $query->getQuerySettings()
              ->setRespectStoragePage(false)
              ->setRespectSysLanguage(true)
              ->setLanguageUid($language)
              ->setLanguageOverlayMode($languageOverlayMode);

        $query->matching($query->equals('uid', $uid));

        $result = $query->execute(true)[0] ?? null;
        if ($result === null) {
            throw new NotFoundException("No result for $modelClassPath with uid $uid found");
        }

        return $result;
    }

    /**
     * @throws BadInputException
     */
    public function fetchMultipleRecords($root, array $args, $context, ResolveInfo $resolveInfo, string $modelClassPath, string $tableName): PaginatedQueryResult
    {
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);
        $limit = (int)($args[QueryArgumentsUtility::$paginationFirst] ?? 10);
        $offset = PaginationUtility::offsetFromCursor($args['after'] ?? 0);

        $sortBy = $args[QueryArgumentsUtility::$sortByField] ?? null;
        $sortDirection = $args[QueryArgumentsUtility::$sortingOrder] ?? 'ASC';

        // TODO we can fetch only the field that we need by using the resolveInfo, but we need to make sure that the repository logic is kept
        $query = $this->persistenceManager->createQueryForType($modelClassPath);

        if (count($storagePids) === 0) {
            $query->getQuerySettings()->setRespectStoragePage(false);
        } else {
            $query->getQuerySettings()->setRespectStoragePage(true)->setStoragePageIds($storagePids);
        }

        $languageOverlayMode = $this->configurationService->getModels()[$modelClassPath]['languageOverlayMode'] ?? true;
        $query->getQuerySettings()
              ->setRespectSysLanguage(true)
              ->setLanguageUid($language)
              ->setLanguageOverlayMode($languageOverlayMode);

        $count = $query->count();

        $query->setOffset($offset);
        $query->setLimit($limit);

        if ($sortBy !== null) {
            $query->setOrderings([$sortBy => $sortDirection]);
        }

        return new PaginatedQueryResult($query->execute(true), $count, $offset, $limit, $resolveInfo);
    }

    /**
     * @throws NotFoundException
     */
    public function fetchForeignRecord($root, array $args, $context, ResolveInfo $resolveInfo, Context $schemaContext, string $foreignTable): ?array
    {
        $foreignUid = $root[$resolveInfo->fieldName];

        // We don't need records with uid 0
        if ($foreignUid === 0) {
            return null;
        }

        // TODO: maybe improve on this
        $language = (int)($root['sys_language_uid'] ?? 0);

        $modelClassPath = $schemaContext->getTypeRegistry()
                                        ->getModelClassPathByTableName($foreignTable);

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
     * @throws BadInputException
     */
    public function fetchForeignRecordsWithMM($root, array $args, $context, ResolveInfo $resolveInfo, Context $schemaContext, string $foreignTable): PaginatedQueryResult
    {
        $tableName = $schemaContext->getTableName();
        $foreignUid = $root['uid'];
        $limit = (int)($args[QueryArgumentsUtility::$paginationFirst] ?? 10);
        $offset = PaginationUtility::offsetFromCursor($args['after'] ?? 0);

        $sortBy = $args[QueryArgumentsUtility::$sortByField] ?? null;
        $sortDirection = $args[QueryArgumentsUtility::$sortingOrder] ?? 'ASC';

        $mm = $GLOBALS['TCA'][$tableName]['columns'][$resolveInfo->fieldName]['config']['MM'];

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $qb = $connectionPool->getQueryBuilderForTable($tableName);

        $qb->from($foreignTable, 'o')->leftJoin('o', $mm, 'm', $qb->expr()->eq('o.uid', 'm.uid_local'))->andWhere($qb->expr()
                                                                                                              ->eq('m.uid_foreign', $foreignUid));

        $count = $qb->count('o.uid')->execute()->fetchOne();

        $qb->select("o.*");

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        if ($sortBy !== null) {
            $qb->orderBy('o.' . $qb->createNamedParameter($sortBy), $sortDirection);
        }

        return new PaginatedQueryResult($qb->execute()->fetchAllAssociative(), $count, $offset, $limit, $resolveInfo);
    }

    public function fetchFile($root, array $args, $context, ResolveInfo $resolveInfo, Context $schemaContext): ?FileInterface
    {
        return $this->fileRepository->findByRelation($schemaContext->getTableName(), $resolveInfo->fieldName, $root['uid'])[0] ?? null;
    }

    public function fetchFiles($root, array $args, $context, ResolveInfo $resolveInfo, Context $schemaContext): array
    {
        return $this->fileRepository->findByRelation($schemaContext->getTableName(), $resolveInfo->fieldName, $root['uid']);
    }
}
