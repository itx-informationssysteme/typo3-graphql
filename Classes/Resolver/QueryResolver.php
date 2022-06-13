<?php

namespace Itx\Typo3GraphQL\Resolver;

use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Types\TypeRegistry;
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
        $language = (int)$args['language'];

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
        $language = (int)$args['language'];

        // TODO we can fetch only the field that we need by using the resolveInfo, but we need to make sure that the repository logic is kept
        $query = $this->persistenceManager->createQueryForType($modelClassPath);

        // TODO optional argument storage pid
        $query->getQuerySettings()->setRespectStoragePage(false)->setLanguageUid($language)->setLanguageOverlayMode(true);

        return $query->execute(true);
    }

    /**
     * @throws NotFoundException
     */
    public function fetchForeignRecord($root,
                                       array $args,
        $context,
                                       ResolveInfo $resolveInfo,
                                       TypeRegistry $typeRegistry,
                                       string $tableName): array
    {
        $foreignUid = $root[$resolveInfo->fieldName];
        // TODO: maybe improve on this
        $language = (int)$root['sys_language_uid'];

        $modelClassPath =
            $typeRegistry->getModelClassPathByTableName($GLOBALS['TCA'][$tableName]['columns'][$resolveInfo->fieldName]['config']['foreign_table']);

        $query = $this->persistenceManager->createQueryForType($modelClassPath);
        $query->getQuerySettings()->setRespectStoragePage(false)->setLanguageUid($language)->setLanguageOverlayMode(true);

        $query->matching($query->equals('uid', $foreignUid));

        $result = $query->execute(true)[0] ?? null;
        if ($result === null) {
            throw new NotFoundException("No result for $modelClassPath with uid $foreignUid found");
        }

        return $result;
    }
}
