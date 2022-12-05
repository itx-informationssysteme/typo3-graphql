<?php

namespace Itx\Typo3GraphQL\Resolver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Domain\Repository\FilterRepository;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Exception\FieldDoesNotExistException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Schema\Context;
use Itx\Typo3GraphQL\Service\ConfigurationService;
use Itx\Typo3GraphQL\Utility\PaginationUtility;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class QueryResolver
{
    protected PersistenceManager $persistenceManager;
    protected FileRepository $fileRepository;
    protected ConfigurationService $configurationService;
    protected FilterRepository $filterRepository;
    protected DataMapper $dataMapper;

    public function __construct(PersistenceManager $persistenceManager, FileRepository $fileRepository, ConfigurationService $configurationService, FilterRepository $filterRepository, DataMapper $dataMapper)
    {
        $this->persistenceManager = $persistenceManager;
        $this->fileRepository = $fileRepository;
        $this->configurationService = $configurationService;
        $this->filterRepository = $filterRepository;
        $this->dataMapper = $dataMapper;
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
        $query->getQuerySettings()->setRespectStoragePage(false)->setRespectSysLanguage(true)->setLanguageUid($language)->setLanguageOverlayMode($languageOverlayMode);

        $query->matching($query->equals('uid', $uid));

        $result = $query->execute()[0] ?? null;
        if ($result === null) {
            throw new NotFoundException("No result for $modelClassPath with uid $uid found");
        }

        return $result;
    }

    /**
     * @throws BadInputException|InvalidQueryException
     */
    public function fetchMultipleRecords($root, array $args, $context, ResolveInfo $resolveInfo, string $modelClassPath, string $tableName): PaginatedQueryResult
    {
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);
        $limit = (int)($args[QueryArgumentsUtility::$paginationFirst] ?? 10);
        $offset = PaginationUtility::offsetFromCursor($args['after'] ?? 0);

        $sortBy = $args[QueryArgumentsUtility::$sortByField] ?? null;
        $sortDirection = $args[QueryArgumentsUtility::$sortingOrder] ?? 'ASC';

        $filters = $args[QueryArgumentsUtility::$filters] ?? [];
        $discreteFilters = $filters[QueryArgumentsUtility::$discreteFilters] ?? [];

        // Path as key for discrete filters
        $discreteFilters = array_combine(array_map(static function($filter) {
            return $filter['path'];
        }, $discreteFilters),            $discreteFilters);

        // TODO we can fetch only the field that we need by using the resolveInfo, but we need to make sure that the repository logic is kept
        $query = $this->persistenceManager->createQueryForType($modelClassPath);

        if (count($storagePids) === 0) {
            $query->getQuerySettings()->setRespectStoragePage(false);
        } else {
            $query->getQuerySettings()->setRespectStoragePage(true)->setStoragePageIds($storagePids);
        }

        $languageOverlayMode = $this->configurationService->getModels()[$modelClassPath]['languageOverlayMode'] ?? true;
        $query->getQuerySettings()->setRespectSysLanguage(true)->setLanguageUid($language)->setLanguageOverlayMode($languageOverlayMode);

        $filterConfigurations = $this->filterRepository->findByModelAndPaths($modelClassPath, array_keys($discreteFilters));

        foreach ($filterConfigurations as $filterConfiguration) {
            $discreteFilter = $discreteFilters[$filterConfiguration->getFilterPath()] ?? [];

            if (count($discreteFilter['options'] ?? []) === 0) {
                continue;
            }

            $query->matching($query->in($discreteFilter['path'], $discreteFilter['options']));
        }

        $count = $query->count();

        $query->setOffset($offset);
        $query->setLimit($limit);

        if ($sortBy !== null) {
            $query->setOrderings([$sortBy => $sortDirection]);
        }

        return new PaginatedQueryResult($query->execute()->toArray(), $count, $offset, $limit, $resolveInfo, $modelClassPath, $this->dataMapper);
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

        // TODO: maybe improve on this regarding language overlays
        $language = (int)($root['sys_language_uid'] ?? 0);

        $modelClassPath = $schemaContext->getTypeRegistry()->getModelClassPathByTableName($foreignTable);

        $query = $this->persistenceManager->createQueryForType($modelClassPath);
        $query->getQuerySettings()->setRespectStoragePage(false)->setLanguageUid($language)->setLanguageOverlayMode(true);

        $query->matching($query->equals('uid', $foreignUid));

        $result = $query->execute()[0] ?? null;
        if ($result === null) {
            throw new NotFoundException("No result for $modelClassPath with uid $foreignUid found");
        }

        return $result;
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws BadInputException
     * @throws InvalidQueryException
     * @throws FieldDoesNotExistException
     * @throws NotFoundException
     */
    public function fetchForeignRecordsWithMM(AbstractDomainObject $root, array $args, $context, ResolveInfo $resolveInfo, Context $schemaContext, string $foreignTable): PaginatedQueryResult
    {
        $tableName = $schemaContext->getTableName();
        $localUid = $root->getUid();
        $limit = (int)($args[QueryArgumentsUtility::$paginationFirst] ?? 10);
        $offset = PaginationUtility::offsetFromCursor($args['after'] ?? 0);

        $sortBy = $args[QueryArgumentsUtility::$sortByField] ?? null;
        $sortDirection = $args[QueryArgumentsUtility::$sortingOrder] ?? 'ASC';

        $mm = $GLOBALS['TCA'][$tableName]['columns'][$resolveInfo->fieldName]['config']['MM'];
        $modelClassPath = $schemaContext->getTypeRegistry()->getModelClassPathByTableName($foreignTable);

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $qb = $connectionPool->getQueryBuilderForTable($foreignTable);

        $qb->from($foreignTable)->leftJoin($foreignTable, $mm, 'm', $qb->expr()->eq("$foreignTable.uid", 'm.uid_foreign'))->andWhere($qb->expr()->eq('m.uid_local', $localUid));

        $filters = $args[QueryArgumentsUtility::$filters] ?? [];
        $discreteFilters = $filters[QueryArgumentsUtility::$discreteFilters] ?? [];

        // Path as key for discrete filters
        $discreteFilters = array_combine(array_map(static function($filter) {
            return $filter['path'];
        }, $discreteFilters),            $discreteFilters);

        $filterConfigurations = $this->filterRepository->findByModelAndPaths($modelClassPath, array_keys($discreteFilters));

        foreach ($filterConfigurations as $filterConfiguration) {
            $discreteFilter = $discreteFilters[$filterConfiguration->getFilterPath()] ?? [];

            $filterPathElements = explode('.', $discreteFilter['path']);
            $lastElement = array_pop($filterPathElements);

            if (count($discreteFilter['options'] ?? []) === 0) {
                continue;
            }

            $lastElementTable = FilterResolver::buildJoinsByWalkingPath($filterPathElements, $foreignTable, $qb);

            $qb->andWhere($qb->expr()->in($lastElementTable . '.' . $lastElement, $qb->quoteArrayBasedValueListToStringList($discreteFilter['options'])));
        }

        $count = $qb->count("$foreignTable.uid")->execute()->fetchOne();

        $qb->select("$foreignTable.*");

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        if ($sortBy !== null) {
            $qb->orderBy("$foreignTable." . $qb->createNamedParameter($sortBy), $sortDirection);
        }

        return new PaginatedQueryResult($qb->execute()->fetchAllAssociative(), $count, $offset, $limit, $resolveInfo, $modelClassPath, $this->dataMapper);
    }
}
