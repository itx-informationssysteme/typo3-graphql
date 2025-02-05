<?php

namespace Itx\Typo3GraphQL\Resolver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Domain\Model\Filter;
use Itx\Typo3GraphQL\Domain\Repository\FilterRepository;
use Itx\Typo3GraphQL\Enum\FilterEventSource;
use Itx\Typo3GraphQL\Events\ModifyQueryBuilderForFilteringEvent;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Exception\FieldDoesNotExistException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Schema\Context;
use Itx\Typo3GraphQL\Service\ConfigurationService;
use Itx\Typo3GraphQL\Utility\PaginationUtility;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use Itx\Typo3GraphQL\Utility\TcaUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
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

    public function __construct(PersistenceManager                 $persistenceManager,
                                FileRepository                     $fileRepository,
                                ConfigurationService               $configurationService,
                                FilterRepository                   $filterRepository,
                                DataMapper                         $dataMapper,
                                protected EventDispatcherInterface $eventDispatcher,
                                protected ConnectionPool           $connectionPool)
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
    public function fetchSingleRecord($root, array $args, $context, ResolveInfo $resolveInfo, string $modelClassPath): AbstractEntity | null
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

        /** @var AbstractEntity $result */
        $result = $query->execute()[0] ?? null;
        if ($result === null) {
            throw new NotFoundException("No result for $modelClassPath with uid $uid found");
        }

        return $result;
    }

    /**
     * @throws BadInputException|InvalidQueryException
     * @throws DBALException
     * @throws FieldDoesNotExistException
     * @throws Exception
     */
    public function fetchMultipleRecords($root,
                                         array $args,
                                         mixed $context,
                                         ResolveInfo $resolveInfo,
                                         string $modelClassPath,
                                         string $tableName): PaginatedQueryResult
    {
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);
        $limit = (int)($args[QueryArgumentsUtility::$paginationFirst] ?? 10);
        $offset = $args[QueryArgumentsUtility::$offset] ?? PaginationUtility::offsetFromCursor($args['after'] ?? '');

        $sorting = $args[QueryArgumentsUtility::$sorting] ?? [];

        $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
        $frontendRestrictionContainer = GeneralUtility::makeInstance(FrontendRestrictionContainer::class);
        $qb->setRestrictions($frontendRestrictionContainer);

        $qb->from($tableName);
        if(isset($GLOBALS['TCA'][$tableName]['columns']['sys_language_uid'])){
            $qb->andWhere($qb->expr()->eq("$tableName.sys_language_uid", $language));
        }

        if (!empty($storagePids)) {
            $qb->andWhere($qb->expr()->in("$tableName.pid", $storagePids));
        }

        $this->applyFiltersToQueryBuilder($qb, $modelClassPath, $tableName, $args);

        $tableNameQuoted = $qb->quoteIdentifier($tableName);

        $qb->selectLiteral("COUNT(DISTINCT $tableNameQuoted.uid)");
        $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent($modelClassPath,
                                                                                 $tableName,
                                                                                 $qb,
                                                                                 $args,
                                                                                 FilterEventSource::QUERY_COUNT));
        $count = $qb->execute()->fetchOne();

        $fields = PaginationUtility::getFieldSelection($resolveInfo, $tableName, array_map(static fn ($x) => $x['field'], $sorting));

        $qb->select(...$fields)->distinct();

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        foreach ($sorting as $item) {
            $field = $item['field'];
            $order = $item['order'];

            $fieldPrefix = "$tableName.";
            if (!TcaUtility::doesFieldExist($tableName, $field)) {
                $fieldPrefix = '';
            }

            $qb->addOrderBy($fieldPrefix . $field, $order);
        }

        $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent($modelClassPath,
                                                                                 $tableName,
                                                                                 $qb,
                                                                                 $args,
                                                                                 FilterEventSource::QUERY));

        return new PaginatedQueryResult($qb->execute()->fetchAllAssociative(),
                                        $count,
                                        $offset,
                                        $limit,
                                        $resolveInfo,
                                        $modelClassPath,
                                        $this->dataMapper);
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws BadInputException
     * @throws InvalidQueryException
     * @throws FieldDoesNotExistException
     * @throws NotFoundException
     */
    public function fetchForeignRecordsWithMM(AbstractDomainObject $root,
                                              array                $args,
                                                                   $context,
                                              ResolveInfo          $resolveInfo,
                                              Context              $schemaContext,
                                              string               $foreignTable): PaginatedQueryResult
    {
        $tableName = $schemaContext->getTableName();
        $localUid = $root->getUid();
        $limit = (int)($args[QueryArgumentsUtility::$paginationFirst] ?? 10);
        $offset = $args[QueryArgumentsUtility::$offset] ?? PaginationUtility::offsetFromCursor($args['after'] ?? '');

        $sorting = $args[QueryArgumentsUtility::$sorting] ?? [];

        $mm = $GLOBALS['TCA'][$tableName]['columns'][$resolveInfo->fieldName]['config']['MM'];
        $modelClassPath = $schemaContext->getTypeRegistry()->getModelClassPathByTableName($foreignTable);

        $qb = $this->connectionPool->getQueryBuilderForTable($foreignTable);
        $frontendRestrictionContainer = GeneralUtility::makeInstance(FrontendRestrictionContainer::class);
        $qb->setRestrictions($frontendRestrictionContainer);

        $qb->from($foreignTable)
           ->leftJoin($foreignTable, $mm, 'm', $qb->expr()->eq("$foreignTable.uid", 'm.uid_foreign'))
           ->andWhere($qb->expr()->eq('m.uid_local', $localUid));

        $this->applyFiltersToQueryBuilder($qb, $modelClassPath, $foreignTable, $args);

        $foreignTableQuoted = $qb->quoteIdentifier($foreignTable);

        $qb->selectLiteral("COUNT(DISTINCT $foreignTableQuoted.uid)")->distinct();

        $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent($modelClassPath,
                                                                                 $foreignTable,
                                                                                 $qb,
                                                                                 $args,
                                                                                 FilterEventSource::QUERY_COUNT));

        $count = $qb->execute()->fetchOne();

        $fields = PaginationUtility::getFieldSelection($resolveInfo, $foreignTable, array_map(static fn ($x) => $x['field'], $sorting));

        $qb->select(...$fields)->distinct();

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        foreach ($sorting as $item) {
            $field = $item['field'];
            $order = $item['order'];

            $fieldPrefix = "$tableName.";
            if (!TcaUtility::doesFieldExist($tableName, $field)) {
                $fieldPrefix = '';
            }

            $qb->addOrderBy($fieldPrefix . $field, $order);
        }

        $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent($modelClassPath,
                                                                                 $foreignTable,
                                                                                 $qb,
                                                                                 $args,
                                                                                 FilterEventSource::QUERY));

        return new PaginatedQueryResult($qb->execute()->fetchAllAssociative(),
                                        $count,
                                        $offset,
                                        $limit,
                                        $resolveInfo,
                                        $modelClassPath,
                                        $this->dataMapper);
    }

    /**
     * This functions applies all filters to the query.
     *
     * @return array{0: array<Filter>, 1: array<Filter>}
     * @throws FieldDoesNotExistException
     * @throws InvalidQueryException
     */
    public function applyFiltersToQueryBuilder(QueryBuilder $qb, string $modelClassPath, string $table, array $args): array
    {
        $filters = $args[QueryArgumentsUtility::$filters] ?? [];
        $discreteFilters = [];
        $rangeFilters = [];
        $dateFilters = [];
        $joinedTables = [];

        if(array_key_exists(QueryArgumentsUtility::$discreteFilters, $filters)){
            if ($filters[QueryArgumentsUtility::$discreteFilters]) {
                $discreteFilters = $filters[QueryArgumentsUtility::$discreteFilters] ?? [];

                // Path as key for discrete filters
                $discreteFilters =
                    array_combine(array_map(static fn($filter) => $filter['path'], $discreteFilters), $discreteFilters);
            }
        }
        if(array_key_exists(QueryArgumentsUtility::$rangeFilters, $filters)){
            if ($filters[QueryArgumentsUtility::$rangeFilters]) {
                $rangeFilters = $filters[QueryArgumentsUtility::$rangeFilters] ?? [];

                // Path as key for discrete filters
                $rangeFilters = array_combine(array_map(static fn($filter) => $filter['path'], $rangeFilters), $rangeFilters);
            }
        }
        if(array_key_exists(QueryArgumentsUtility::$dateFilters, $filters)){
            if ($filters[QueryArgumentsUtility::$dateFilters]) {
                $dateFilters = $filters[QueryArgumentsUtility::$dateFilters] ?? [];

                // Path as key for discrete filters
                $dateFilters = array_combine(array_map(static fn($filter) => $filter['path'], $dateFilters), $dateFilters);
            }
        }

        $discreteFilterConfigurations =
            $this->filterRepository->findByModelAndPathsAndType($modelClassPath, array_keys($discreteFilters), 'discrete');
        $rangeFilterConfiguration =
            $this->filterRepository->findByModelAndPathsAndType($modelClassPath, array_keys($rangeFilters), 'range');
        $dateFilterConfiguration =
            $this->filterRepository->findByModelAndPathsAndType($modelClassPath, array_keys($dateFilters), 'dateRange');

        foreach ($discreteFilterConfigurations as $filterConfiguration) {
            $discreteFilter = $discreteFilters[$filterConfiguration->getFilterPath()] ?? [];

            $filterPathElements = explode('.', $discreteFilter['path']);
            $lastElement = array_pop($filterPathElements);

            if (count($discreteFilter['options'] ?? []) === 0) {
                continue;
            }

            $lastElementTable = FilterResolver::buildJoinsByWalkingPath($filterPathElements, $table, $qb, $joinedTables);
            $joinedTables = $lastElementTable['joinedTables'];

            $inSetExpressions = [];

            foreach ($discreteFilter['options'] as $option) {
                $inSetExpressions[] =
                    $qb->expr()->inSet($lastElementTable['lastElementTableAlias'] . '.' . $lastElement, $qb->createNamedParameter($option));
            }

            $qb->andWhere($qb->expr()->orX(...$inSetExpressions));
        }

        foreach ($rangeFilterConfiguration as $filterConfiguration) {
            $rangeFilter = $rangeFilters[$filterConfiguration->getFilterPath()] ?? [];

            $whereFilterPathElements = explode('.', $rangeFilter['path']);
            $whereFilterLastElement = array_pop($whereFilterPathElements);

            $whereFilterTable = FilterResolver::buildJoinsByWalkingPath($whereFilterPathElements, $table, $qb, $joinedTables);
            $joinedTables = $whereFilterTable['joinedTables'];

            $andExpressions = [];

            if (($rangeFilter['range']['min'] ?? null) !== null) {
                $andExpressions[] = $qb->expr()->gte($whereFilterTable['lastElementTableAlias'] . '.' . $whereFilterLastElement,
                                                     $qb->createNamedParameter($rangeFilter['range']['min']));
            }

            if (($rangeFilter['range']['max'] ?? null) !== null) {
                $andExpressions[] = $qb->expr()->lte($whereFilterTable['lastElementTableAlias'] . '.' . $whereFilterLastElement,
                                                     $qb->createNamedParameter($rangeFilter['range']['max']));
            }

            $qb->andWhere(...$andExpressions);
        }

        foreach ($dateFilterConfiguration as $filterConfiguration) {
            $dateFilter = $dateFilters[$filterConfiguration->getFilterPath()] ?? [];

            $whereFilterPathElements = explode('.', $dateFilter['path']);
            $whereFilterLastElement = array_pop($whereFilterPathElements);

            $whereFilterTable = FilterResolver::buildJoinsByWalkingPath($whereFilterPathElements, $table, $qb, $joinedTables);
            $joinedTables = $whereFilterTable['joinedTables'];

            $andExpressions = [];

            /** @var ?\DateTimeInterface $rangeMin  */
            $rangeMin = $dateFilter['range']['min'];
            if (($rangeMin ?? null) !== null) {
                $andExpressions[] = $qb->expr()->gte($whereFilterTable['lastElementTableAlias'] . '.' . $whereFilterLastElement,
                                                     $qb->createNamedParameter($rangeMin->format(\DateTimeInterface::ATOM)));
            }

            /** @var ?\DateTimeInterface $rangeMax  */
            $rangeMax = $dateFilter['range']['max'];
            if (($rangeMax ?? null) !== null) {
                $andExpressions[] = $qb->expr()->lte($whereFilterTable['lastElementTableAlias'] . '.' . $whereFilterLastElement,
                                                     $qb->createNamedParameter($rangeMax->format(\DateTimeInterface::ATOM)));
            }

            $qb->andWhere(...$andExpressions);
        }

        return [$discreteFilterConfigurations, $rangeFilterConfiguration, $dateFilterConfiguration];
    }
}
