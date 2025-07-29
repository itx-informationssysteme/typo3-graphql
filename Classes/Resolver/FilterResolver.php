<?php

namespace Itx\Typo3GraphQL\Resolver;

use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Generator;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Domain\Model\Filter;
use Itx\Typo3GraphQL\Domain\Repository\FilterRepository;
use Itx\Typo3GraphQL\Enum\FacetType;
use Itx\Typo3GraphQL\Enum\FilterEventSource;
use Itx\Typo3GraphQL\Events\ModifyQueryBuilderForFilteringEvent;
use Itx\Typo3GraphQL\Exception\FieldDoesNotExistException;
use Itx\Typo3GraphQL\Service\ConfigurationService;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterInput;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterOption;
use Itx\Typo3GraphQL\Types\Skeleton\Range;
use Itx\Typo3GraphQL\Types\Skeleton\RangeFloat;
use Itx\Typo3GraphQL\Types\Skeleton\RangeFilterInput;
use Itx\Typo3GraphQL\Types\Skeleton\DateRange;
use Itx\Typo3GraphQL\Types\Skeleton\DateFilterInput;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use Itx\Typo3GraphQL\Utility\TcaUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use Itx\Typo3GraphQL\Utility\FilterUtility;

class FilterResolver
{
    protected PersistenceManager $persistenceManager;
    protected FilterRepository $filterRepository;

    public function __construct(
        PersistenceManager $persistenceManager,
        FilterRepository $filterRepository,
        protected EventDispatcherInterface $eventDispatcher,
        protected FrontendInterface $cache,
        protected ConfigurationService $configurationService
    ) {
        $this->persistenceManager = $persistenceManager;
        $this->filterRepository = $filterRepository;
    }

    /**
     * This method fetches filter options for a given root record type.
     *
     * @param             $root
     * @param array       $args
     * @param             $context
     * @param ResolveInfo $resolveInfo
     * @param string      $tableName
     * @param string      $modelClassPath
     *
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws FieldDoesNotExistException
     * @throws InvalidQueryException
     */
    public function fetchFiltersIncludingFacets(
        $root,
        array $args,
        $context,
        ResolveInfo $resolveInfo,
        string $tableName,
        string $modelClassPath
    ): array {
        return $this->computeFilterOptions($root, $args, $context, $resolveInfo, $tableName, $modelClassPath);
    }

    /**
     * This method fetches filter options that are contained in a relation. This probably only works one relation deep.
     *
     * @param             $root
     * @param array       $args
     * @param             $context
     * @param ResolveInfo $resolveInfo
     * @param string      $tableName
     * @param string      $modelClassPath
     * @param string      $mmTable
     * @param int         $localUid
     *
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws FieldDoesNotExistException
     * @throws InvalidQueryException
     */
    public function fetchFiltersWithRelationConstraintIncludingFacets(
        $root,
        array $args,
        $context,
        ResolveInfo $resolveInfo,
        string $tableName,
        string $modelClassPath,
        string $mmTable,
        int $localUid
    ): array {
        return $this->computeFilterOptions(
            $root,
            $args,
            $context,
            $resolveInfo,
            $tableName,
            $modelClassPath,
            $mmTable,
            $localUid
        );
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws InvalidQueryException
     * @throws FieldDoesNotExistException
     */
    private function computeFilterOptions(
        $root,
        array $args,
        $context,
        ResolveInfo $resolveInfo,
        string $tableName,
        string $modelClassPath,
        ?string $mmTable = null,
        ?int $localUid = null
    ): array {
        $facets = [];

        $discreteFilterArguments = $this->extractDiscreteFilterOptionsMap($args);
        $discreteFilterPaths = map($discreteFilterArguments)->map(fn(DiscreteFilterInput $filter) => $filter->path)->toArray();

        $rangefilterArguments = $this->extractRangeFilterObjectsMap($args);
        $rangeFilterPaths = map($rangefilterArguments)->map(fn(RangeFilterInput $filter) => $filter->path)->toArray();

        $datefilterArguments = $this->extractDateFilterObjectsMap($args);
        $dateFilterPaths = map($datefilterArguments)->map(fn(DateFilterInput $filter) => $filter->path)->toArray();

        if (array_key_exists('discreteFilters', $args['filters'])) {
            // Switch keys and values for $discreteFilterPaths
            $filters = array_flip($discreteFilterPaths);

            // Reorder to the same order as the discrete filter paths
            $filterResult =
                $this->filterRepository->findByModelAndPathsAndType($modelClassPath, $discreteFilterPaths, 'discrete');
            $staticFilters = $this->configurationService->getFiltersForModel($modelClassPath, $discreteFilterPaths, 'discrete');
            $filterResult = array_merge($filterResult, $staticFilters);

            // Sort them as we received them
            foreach ($filterResult as $filter) {
                $filters[$filter->getFilterPath()] = $filter;
            }

            //Facets befüllen
            foreach ($filters as $path => $filter) {
                FilterUtility::resetAlias();

                if (!$filter instanceof Filter) {
                    throw new \RuntimeException("Discrete Filter $path not found");
                }

                $facet = [];
                $facet['label'] = $filter->getName();
                $facet['path'] = $filter->getFilterPath();
                $facet['type'] = FacetType::DISCRETE;

                $options = $this->fetchAndProcessFilterOptions(
                    $tableName,
                    $filter->getFilterPath(),
                    $args,
                    $discreteFilterArguments,
                    $rangefilterArguments,
                    $datefilterArguments,
                    $resolveInfo,
                    $modelClassPath,
                    $mmTable,
                    $localUid
                );

                $facet['options'] = $options;

                $facets[] = $facet;
            }
        }

        if (array_key_exists('rangeFilters', $args['filters'])) {
            $filters = array_flip($rangeFilterPaths);
            $filterResult = $this->filterRepository->findByModelAndPathsAndType($modelClassPath, $rangeFilterPaths, 'range');
            $staticFilters = $this->configurationService->getFiltersForModel($modelClassPath, $rangeFilterPaths, 'range');
            $filterResult = array_merge($filterResult, $staticFilters);

            foreach ($filterResult as $filter) {
                $filters[$filter->getFilterPath()] = $filter;
            }

            foreach ($filters as $path => $filter) {
                if (!$filter instanceof Filter) {
                    throw new \RuntimeException("Range Filter $path not found");
                }

                $facet = [];
                $facet['label'] = $filter->getName();
                $facet['path'] = $filter->getFilterPath();
                $facet['type'] = FacetType::RANGE;
                $facet['unit'] = $filter->getUnit();

                $facet['range'] = $this->fetchRanges(
                    $tableName,
                    $filter->getFilterPath(),
                    $args,
                    $discreteFilterArguments,
                    $rangefilterArguments,
                    $datefilterArguments,
                    $resolveInfo,
                    $modelClassPath,
                    $mmTable,
                    $localUid
                );

                $facets[] = $facet;
            }
        }

        if (array_key_exists('dateFilters', $args['filters'])) {
            $filters = array_flip($dateFilterPaths);
            $filterResult = $this->filterRepository->findByModelAndPathsAndType($modelClassPath, $dateFilterPaths, 'dateRange');

            foreach ($filterResult as $filter) {
                $filters[$filter->getFilterPath()] = $filter;
            }

            foreach ($filters as $path => $filter) {
                if (!$filter instanceof Filter) {
                    throw new \RuntimeException("DateRange Filter $path not found");
                }

                $facet = [];
                $facet['label'] = $filter->getName();
                $facet['path'] = $filter->getFilterPath();
                $facet['type'] = FacetType::DATERANGE;
                $facet['unit'] = $filter->getUnit();

                $facet['range'] = $this->fetchDateRanges($tableName,
                                                     $filter->getFilterPath(),
                                                     $args,
                                                     $discreteFilterArguments,
                                                     $rangefilterArguments,
                                                     $datefilterArguments,
                                                     $resolveInfo,
                                                     $modelClassPath,
                                                     $mmTable,
                                                     $localUid);

                $facets[] = $facet;
            }
        }

        return $facets;
    }

    /**
     * @param array $args
     *
     * @return array<string,DiscreteFilterInput>
     */
    private function extractDiscreteFilterOptionsMap(array $args): array
    {
        $filterArguments = $args[QueryArgumentsUtility::$filters] ?? [];

        $discreteFilterArguments = $filterArguments[QueryArgumentsUtility::$discreteFilters] ?? [];

        // Set key path from discrete filter array as key
        foreach ($discreteFilterArguments as $key => $filter) {
            $discreteFilterArguments[$filter['path']] = new DiscreteFilterInput($filter['path'], $filter['options']);
            unset($discreteFilterArguments[$key]);
        }

        return $discreteFilterArguments;
    }

    /**
     * @param array $args
     *
     * @return array<string,RangeFilterInput>
     */
    private function extractRangeFilterObjectsMap(array $args): array
    {
        $filterArguments = $args[QueryArgumentsUtility::$filters] ?? [];

        $rangeFilterArguments = $filterArguments[QueryArgumentsUtility::$rangeFilters] ?? [];

        // Set key path from range filter array as key
        foreach ($rangeFilterArguments as $key => $filter) {
            $rangeFilterInput = new RangeFilterInput($filter['path']);

            if (isset($filter['range']['min']) || isset($filter['range']['max'])) {
                $range =  new Range($filter['range']['min'] ?? null,$filter['range']['max'] ?? null);
                $rangeFilterInput->setRange($range);
            }

            if (isset($filter['rangeFloat']['min']) || isset($filter['rangeFloat']['max'])) {
                $rangeFloat = new RangeFloat($filter['rangeFloat']['min'] ?? null, $filter['rangeFloat']['max'] ?? null);
                $rangeFilterInput->setRangeFloat($rangeFloat);
            }

            $rangeFilterArguments[$filter['path']] = $rangeFilterInput;
            unset($rangeFilterArguments[$key]);
        }

        return $rangeFilterArguments;
    }

    /**
     * @param array $args
     *
     * @return array<string,DateFilterInput>
     */
    private function extractDateFilterObjectsMap(array $args): array
    {
        $filterArguments = $args[QueryArgumentsUtility::$filters] ?? [];

        $dateFilterArguments = $filterArguments[QueryArgumentsUtility::$dateFilters] ?? [];

        // Set key path from range filter array as key
        foreach ($dateFilterArguments as $key => $filter) {
            $dateFilterArguments[$filter['path']] = new DateFilterInput($filter['path'],
                                                                          new DateRange($filter['dateRange']['min'] ?? null,
                                                                                    $filter['dateRange']['max'] ?? null));
            unset($dateFilterArguments[$key]);
        }

        return $dateFilterArguments;
    }

    /**
     * @param string                            $tableName
     * @param string                            $filterPath
     * @param array                             $args
     * @param array<string,DiscreteFilterInput> $discreteFilterArguments
     * @param array<string,RangeFilterInput>    $rangeFilterArguments
     * @param array<string,DateFilterInput>     $dateFilterArguments
     * @param ResolveInfo                       $resolveInfo
     * @param string                            $modelClassPath
     * @param string|null                       $mmTable
     * @param int|null                          $localUid
     *
     * @return array<DiscreteFilterOption>
     * @throws DBALException
     * @throws Exception
     * @throws FieldDoesNotExistException
     */
    private function fetchFilterOptions(
        string $tableName,
        string $filterPath,
        array $args,
        array $discreteFilterArguments,
        array $rangeFilterArguments,
        array $dateFilterArguments,
        ResolveInfo $resolveInfo,
        string $modelClassPath,
        ?string $mmTable,
        ?int $localUid,
        bool $triggerEvent
    ): array {
        $language = $args[QueryArgumentsUtility::$language] ?? null;
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);

        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        $lastElementTable = self::buildJoinsByWalkingPath($filterPathElements, $tableName, $queryBuilder);

        // If we have a relation constraint, we need to add the constraint to the query
        if ($mmTable !== null && $localUid !== null) {
            $queryBuilder->leftJoin(
                $tableName,
                $mmTable,
                'mm',
                $queryBuilder->expr()
                             ->eq('mm.uid_foreign', $queryBuilder->quoteIdentifier($tableName . '.uid'))
            );
            $queryBuilder->andWhere($queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->createNamedParameter($localUid)));
        }

        if (count($storagePids) > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->in(
                $tableName . '.pid',
                array_map(
                    static fn($a) => $queryBuilder->createNamedParameter(
                        $a,
                        \PDO::PARAM_INT
                    ),
                    $storagePids
                )
            ));
        }

        if ($language !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq(
                $tableName . '.sys_language_uid',
                $queryBuilder->createNamedParameter($language, \PDO::PARAM_INT)
            ));
        }

        $this->applyDiscreteFilters($discreteFilterArguments, $tableName, $queryBuilder, $filterPath);
        $this->applyRangeFilters($rangeFilterArguments, $tableName, $queryBuilder, $filterPath);
        $this->applyDateFilters($dateFilterArguments, $tableName, $queryBuilder, $filterPath);

        if ($triggerEvent) {
            /** @var ModifyQueryBuilderForFilteringEvent $event */
            $event = $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent(
                $modelClassPath,
                $tableName,
                $queryBuilder,
                $args,
                FilterEventSource::FILTER,
                'discrete'
            ));
            $queryBuilder = $event->getQueryBuilder();
        }

        $fieldPrefix = "$lastElementTable.";
        $check = preg_replace('/\d+$/', '', $lastElementTable);
        if (!TcaUtility::fieldExistsAndIsCustom($check, $lastElement)) {
            $fieldPrefix = '';
        }

        $queryBuilder->addSelectLiteral("$lastElementTable.$lastElement AS value")
                     ->from($tableName)
                     ->addSelectLiteral("COUNT($tableName.uid) AS resultCount")
                     ->groupBy("$lastElementTable.$lastElement")
                     ->orderBy("$lastElementTable.$lastElement", 'ASC');

        $result = $queryBuilder->executeQuery()->fetchAllAssociative() ?? [];

        return $this->mapFilterOptions($result);
    }

    /**
     * @param string                            $tableName
     * @param string                            $filterPath
     * @param array                             $args
     * @param array<string,DiscreteFilterInput> $discreteFilterArguments
     * @param array<string,RangeFilterInput>    $rangeFilterArguments
     * @param array<string,DateFilterInput>     $dateFilterArguments
     * @param ResolveInfo                       $resolveInfo
     * @param string                            $modelClassPath
     * @param string|null                       $mmTable
     * @param int|null                          $localUid
     *
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws FieldDoesNotExistException
     */
    private function fetchAndProcessFilterOptions(
        string $tableName,
        string $filterPath,
        array $args,
        array $discreteFilterArguments,
        array $rangeFilterArguments,
        array $dateFilterArguments,
        ResolveInfo $resolveInfo,
        string $modelClassPath,
        ?string $mmTable,
        ?int $localUid
    ): array {
        $isSelectedNeeded = isset($resolveInfo->getFieldSelection(3)['facets']['options']['selected']) &&
            $resolveInfo->getFieldSelection(3)['facets']['options']['selected'];

        $cacheKey = md5($tableName . $filterPath . ($args[QueryArgumentsUtility::$language] ?? '') . implode($args[QueryArgumentsUtility::$pageIds] ?? []));

        if (!$this->cache->has($cacheKey)) {
            $originalFilterOptions = $this->fetchFilterOptions(
                $tableName,
                $filterPath,
                $args,
                [],
                [],
                [],
                $resolveInfo,
                $modelClassPath,
                $mmTable,
                $localUid,
                false
            );

            // Cache for 1 day, and apply cache tags based on $tableName and $mmTable
            $cacheTags = [$tableName];
            if ($mmTable !== null) {
                $cacheTags[] = $mmTable;
            }

            $explodedFilterPath = explode('.', $filterPath);
            array_pop($explodedFilterPath);
            foreach (self::walkTcaRelations($explodedFilterPath, $tableName) as [$currentTable, $fieldName, $tca]) {
                if (($tca['foreign_table'] ?? null) !== null) {
                    $cacheTags[] = $tca['foreign_table'];
                }
            }

            $this->cache->set($cacheKey, $originalFilterOptions, ['filter_options', ...$cacheTags], 86400);
        } else {
            $originalFilterOptions = $this->cache->get($cacheKey);
        }

        // Index array with value as key
        $actualFilterOptions = $this->fetchFilterOptions(
            $tableName,
            $filterPath,
            $args,
            $discreteFilterArguments,
            $rangeFilterArguments,
            $dateFilterArguments,
            $resolveInfo,
            $modelClassPath,
            $mmTable,
            $localUid,
            true
        );

        // Set selected to true for all options that are selected and disabled to true for all options that are not in actualFilterOptions anymore
        foreach ($originalFilterOptions as $originalFilterOption) {
            $selected = false;
            if ($isSelectedNeeded && !empty($discreteFilterArguments[$filterPath])) {
                $selected = in_array($originalFilterOption->value, $discreteFilterArguments[$filterPath]->options, true);
            }
            $originalFilterOption->selected = $selected;

            $disabled = false;

            if (empty($actualFilterOptions[$originalFilterOption->value]) && !$selected) {
                $disabled = true;
                $originalFilterOption->resultCount = 0;
            } else {
                $originalFilterOption->resultCount = $actualFilterOptions[$originalFilterOption->value]?->resultCount ?? 0;
            }

            $originalFilterOption->disabled = $disabled;
        }

        return $originalFilterOptions;
    }

    /**
     * @param string                             $tableName
     * @param string                             $filterPath
     * @param array                              $args
     * @param array<string, DiscreteFilterInput> $discreteFilterArguments
     * @param array<string, RangeFilterInput>    $rangeFilterArguments
     * @param array<string, DateFilterInput>     $dateFilterArguments
     * @param ResolveInfo                        $resolveInfo
     * @param string                             $modelClassPath
     * @param string|null                        $mmTable
     * @param int|null                           $localUid
     *
     * @return Range
     * @throws DBALException
     * @throws Exception
     * @throws FieldDoesNotExistException
     */
    private function fetchRanges(
        string $tableName,
        string $filterPath,
        array $args,
        array $discreteFilterArguments,
        array $rangeFilterArguments,
        array $dateFilterArguments,
        ResolveInfo $resolveInfo,
        string $modelClassPath,
        ?string $mmTable,
        ?int $localUid
    ): Range {
        $language = $args[QueryArgumentsUtility::$language] ?? null;
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);

        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        $lastElementTable = self::buildJoinsByWalkingPath($filterPathElements, $tableName, $queryBuilder);

        // If we have a relation constraint, we need to add the constraint to the query
        if ($mmTable !== null && $localUid !== null) {
            $queryBuilder->leftJoin(
                $tableName,
                $mmTable,
                'mm',
                $queryBuilder->expr()
                             ->eq('mm.uid_foreign', $queryBuilder->quoteIdentifier($tableName . '.uid'))
            );
            $queryBuilder->andWhere($queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->createNamedParameter($localUid)));
        }

        if (count($storagePids) > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->in(
                $tableName . '.pid',
                array_map(
                    static fn($a) => $queryBuilder->createNamedParameter(
                        $a,
                        \PDO::PARAM_INT
                    ),
                    $storagePids
                )
            ));
        }

        if ($language !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq(
                $tableName . '.sys_language_uid',
                $queryBuilder->createNamedParameter($language, \PDO::PARAM_INT)
            ));
        }

        $this->applyDiscreteFilters($discreteFilterArguments, $tableName, $queryBuilder, $filterPath);
        $this->applyRangeFilters($rangeFilterArguments, $tableName, $queryBuilder, $filterPath);
        $this->applyDateFilters($dateFilterArguments, $tableName, $queryBuilder, $filterPath);

        /** @var ModifyQueryBuilderForFilteringEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent(
            $modelClassPath,
            $tableName,
            $queryBuilder,
            $args,
            FilterEventSource::FILTER,
            'range'
        ));
        $queryBuilder = $event->getQueryBuilder();

        $fieldPrefix = "$lastElementTable.";
        if (!TcaUtility::fieldExistsAndIsCustom($lastElementTable, $lastElement)) {
            $fieldPrefix = '';
        }

        $queryBuilder->addSelectLiteral("MIN($fieldPrefix$lastElement) AS min, MAX($fieldPrefix$lastElement) AS max")
                     ->from($tableName);

        $result = $queryBuilder->executeQuery()->fetchAllAssociative() ?? [];

        return new Range($result[0]['min'] ?? 0, $result[0]['max'] ?? 0);
    }

    /**
     * @param string                             $tableName
     * @param string                             $filterPath
     * @param array                              $args
     * @param array<string, DiscreteFilterInput> $discreteFilterArguments
     * @param array<string, RangeFilterInput>    $rangeFilterArguments
     * @param array<string, DateFilterInput>     $dateFilterArguments
     * @param ResolveInfo                        $resolveInfo
     * @param string                             $modelClassPath
     * @param string|null                        $mmTable
     * @param int|null                           $localUid
     *
     * @return Range
     * @throws DBALException
     * @throws Exception
     * @throws FieldDoesNotExistException
     */
    private function fetchDateRanges(string      $tableName,
                                 string      $filterPath,
                                 array       $args,
                                 array       $discreteFilterArguments,
                                 array       $rangeFilterArguments,
                                 array       $dateFilterArguments,
                                 ResolveInfo $resolveInfo,
                                 string      $modelClassPath,
                                 ?string     $mmTable,
                                 ?int        $localUid): DateRange
    {
        $language = $args[QueryArgumentsUtility::$language] ?? null;
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);

        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        $lastElementTable = self::buildJoinsByWalkingPath($filterPathElements, $tableName, $queryBuilder);

        // If we have a relation constraint, we need to add the constraint to the query
        if ($mmTable !== null && $localUid !== null) {
            $queryBuilder->leftJoin(
                $tableName,
                $mmTable,
                'mm',
                $queryBuilder->expr()
                             ->eq('mm.uid_foreign', $queryBuilder->quoteIdentifier($tableName . '.uid'))
            );
            $queryBuilder->andWhere($queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->createNamedParameter($localUid)));
        }

        if (count($storagePids) > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->in(
                $tableName . '.pid',
                array_map(
                    static fn($a) => $queryBuilder->createNamedParameter(
                        $a,
                        \PDO::PARAM_INT
                    ),
                    $storagePids
                )
            ));
        }

        if ($language !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq(
                $tableName . '.sys_language_uid',
                $queryBuilder->createNamedParameter($language, \PDO::PARAM_INT)
            ));
        }

        $this->applyDiscreteFilters($discreteFilterArguments, $tableName, $queryBuilder, $filterPath);
        $this->applyRangeFilters($rangeFilterArguments, $tableName, $queryBuilder, $filterPath);
        $this->applyDateFilters($dateFilterArguments, $tableName, $queryBuilder, $filterPath);

        /** @var ModifyQueryBuilderForFilteringEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent(
            $modelClassPath,
            $tableName,
            $queryBuilder,
            $args,
            FilterEventSource::FILTER,
            'range'
        ));
        $queryBuilder = $event->getQueryBuilder();

        $fieldPrefix = "$lastElementTable.";
        if (!TcaUtility::fieldExistsAndIsCustom($lastElementTable, $lastElement)) {
            $fieldPrefix = '';
        }

        $queryBuilder->addSelectLiteral("MIN($fieldPrefix$lastElement) AS min, MAX($fieldPrefix$lastElement) AS max")
                     ->from($tableName);

        $result = $queryBuilder->executeQuery()->fetchAllAssociative() ?? [];

        if (DateTime::createFromFormat("Y-m-d", $result[0]['min'])){
            $min = DateTime::createFromFormat("Y-m-d", $result[0]['min']);
        } else {
            $min = new DateTime();
        }
        if (DateTime::createFromFormat("Y-m-d", $result[0]['max'])){
            $max = DateTime::createFromFormat("Y-m-d", $result[0]['max']);
        } else {
            $max = new DateTime();
        }

        return new DateRange($min, $max);
    }

    /**
     * @param array<string,DiscreteFilterInput> $filterInputs
     * @param                                   $tableName
     * @param QueryBuilder                      $queryBuilder
     * @param string                            $filterPath
     *
     * @throws FieldDoesNotExistException
     */
    private function applyDiscreteFilters(array $filterInputs, $tableName, QueryBuilder $queryBuilder, string $filterPath): void
    {
        // Filter out filter arguments that are not part of the current filter path
        $otherFilters = array_filter(
            $filterInputs,
            static function (DiscreteFilterInput $filterInput) use ($filterPath) {
                return $filterInput->path !== $filterPath && count($filterInput->options) > 0;
            }
        );

        /** @var DiscreteFilterInput $whereFilter */
        foreach ($otherFilters as $whereFilter) {
            $whereFilterPathElements = explode('.', $whereFilter->path);
            $whereFilterLastElement = array_pop($whereFilterPathElements);

            $whereFilterTable = self::buildJoinsByWalkingPath($whereFilterPathElements, $tableName, $queryBuilder);

            $inSetExpressions = [];

            foreach ($whereFilter->options as $option) {
                $inSetExpressions[] = $queryBuilder->expr()->inSet(
                    $whereFilterTable . '.' . $whereFilterLastElement,
                    $queryBuilder->createNamedParameter($option)
                );
            }

            if(sizeof($inSetExpressions) > 0)
                $queryBuilder->andWhere($queryBuilder->expr()->or(...$inSetExpressions));
        }
    }

    /**
     * @param array<string,RangeFilterInput> $filterInputs
     * @param string                         $tableName
     * @param QueryBuilder                   $queryBuilder
     * @param string                         $filterPath
     *
     * @throws FieldDoesNotExistException
     */
    private function applyRangeFilters(
        array $filterInputs,
        string $tableName,
        QueryBuilder $queryBuilder,
        string $filterPath
    ): void {
        // Filter out filter arguments that are not part of the current filter path
        $otherFilters = array_filter(
            $filterInputs,
            static function (RangeFilterInput $filterInput) use ($filterPath) {
                return $filterInput->path !== $filterPath &&
                    (($filterInput->range && ($filterInput->range->min !== null || $filterInput->range->max !== null)) ||
                    ($filterInput->rangeFloat && ($filterInput->rangeFloat->min !== null || $filterInput->rangeFloat->max !== null)));
            }
        );

        /** @var RangeFilterInput $whereFilter */
        foreach ($otherFilters as $whereFilter) {
            $whereFilterPathElements = explode('.', $whereFilter->path);
            $whereFilterLastElement = array_pop($whereFilterPathElements);

            $whereFilterTable = self::buildJoinsByWalkingPath($whereFilterPathElements, $tableName, $queryBuilder);

            $andExpressions = [];

            if ($whereFilter->range) {
                if ($whereFilter->range->min !== null) {
                    $andExpressions[] = $queryBuilder->expr()->gte(
                        $whereFilterTable . '.' . $whereFilterLastElement,
                        $queryBuilder->createNamedParameter($whereFilter->range->min)
                    );
                }

                if ($whereFilter->range->max !== null) {
                    $andExpressions[] = $queryBuilder->expr()->lte(
                        $whereFilterTable . '.' . $whereFilterLastElement,
                        $queryBuilder->createNamedParameter($whereFilter->range->max)
                    );
                }
            }

            if ($whereFilter->rangeFloat) {
                if ($whereFilter->rangeFloat->min !== null) {
                    $andExpressions[] = $queryBuilder->expr()->gte($whereFilterTable['lastElementTableAlias'] . '.' . $whereFilterLastElement,
                        $queryBuilder->createNamedParameter($whereFilter->rangeFloat->min));
                }

                if ($whereFilter->rangeFloat->max !== null) {
                    $andExpressions[] = $queryBuilder->expr()->gte($whereFilterTable['lastElementTableAlias'] . '.' . $whereFilterLastElement,
                        $queryBuilder->createNamedParameter($whereFilter->rangeFloat->max));
                }
            }

            $queryBuilder->andWhere(...$andExpressions);
        }
    }

    /**
     * @param array<string,DateFilterInput>  $filterInputs
     * @param string                         $tableName
     * @param QueryBuilder                   $queryBuilder
     * @param string                         $filterPath
     *
     * @throws FieldDoesNotExistException
     */
    private function applyDateFilters(array        $filterInputs,
                                       string       $tableName,
                                       QueryBuilder $queryBuilder,
                                       string       $filterPath): void
    {
        // Filter out filter arguments that are not part of the current filter path
        $otherFilters = array_filter($filterInputs,
            static function(DateFilterInput $filterInput) use ($filterPath) {
                return $filterInput->path !== $filterPath &&
                    ($filterInput->dateRange->min !== null || $filterInput->dateRange->max !== null);
            });

        /** @var DateFilterInput $whereFilter */
        foreach ($otherFilters as $whereFilter) {
            $whereFilterPathElements = explode('.', $whereFilter->path);
            $whereFilterLastElement = array_pop($whereFilterPathElements);

            $whereFilterTable = self::buildJoinsByWalkingPath($whereFilterPathElements, $tableName, $queryBuilder);

            $andExpressions = [];

            if ($whereFilter->dateRange->min !== null) {
                $andExpressions[] = $queryBuilder->expr()->gte($whereFilterTable . '.' . $whereFilterLastElement,
                                                               $queryBuilder->createNamedParameter($whereFilter->dateRange->min));
            }

            if ($whereFilter->dateRange->max !== null) {
                $andExpressions[] = $queryBuilder->expr()->lte($whereFilterTable . '.' . $whereFilterLastElement,
                                                               $queryBuilder->createNamedParameter($whereFilter->dateRange->max));
            }

            $queryBuilder->andWhere(...$andExpressions);
        }
    }

    /**
     * @param array $rawFilterOptions
     *
     * @return array<DiscreteFilterOption>
     */
    private function mapFilterOptions(array $rawFilterOptions): array
    {
        $options = [];

        foreach ($rawFilterOptions as $rawFilterOption) {
            foreach (explode(',', trim($rawFilterOption['value'])) as $value) {
                $value = trim($value);

                if (!isset($options[$value])) {
                    $options[$value] = new DiscreteFilterOption($value, $rawFilterOption['resultCount'], false, false);

                    continue;
                }

                $options[$value]->resultCount += $rawFilterOption['resultCount'];
            }
        }

        return $options;
    }

    /**
     * @throws FieldDoesNotExistException
     */
    public static function buildJoinsByWalkingPath(
        array $filterPathElements,
        string $tableName,
        QueryBuilder $queryBuilder
    ): string {
        if ($queryBuilder->getQueryParts()["from"][0]["table"] ?? false) {
            FilterUtility::handleAlias(str_replace('`', '', $queryBuilder->getQueryParts()["from"][0]["table"]));
        }

        $i = 1;
        $_lastElementTableAlias = $tableName;

        // Go through the filter path and join the tables by using the TCA MM relations
        /**
         * @var string $currentTable
         * @var string $fieldName
         * @var array  $tca
         */
        foreach (self::walkTcaRelations($filterPathElements, $tableName) as [$currentTable, $fieldName, $tca]) {
            $lastElementTable = $tca['foreign_table'];
            $lastElementTableAlias = $lastElementTable;

            $_lastElementTableAlias = $lastElementTableAlias;

            if ($tca['MM'] ?? false) {
                // Figure out from which side of the MM table we need to join TODO: This might not be robust enough
                $isLocalTable = isset($tca['MM_opposite_field']);

                $mmTableLocalField = $isLocalTable ? 'uid_foreign' : 'uid_local';
                $mmTableForeignField = $isLocalTable ? 'uid_local' : 'uid_foreign';

                $lastElementTableAlias = $tca['MM'];
                $lastElementTableAlias = FilterUtility::handleAlias($lastElementTableAlias);
                $lastElementTableAliasTCAMM = $lastElementTableAlias;

                // Join with MM and foreign table
                $queryBuilder->join(
                    $currentTable,
                    $tca['MM'],
                    $lastElementTableAlias,
                    $queryBuilder->expr()->eq(
                        $lastElementTableAlias . ".$mmTableLocalField",
                        $queryBuilder->quoteIdentifier($currentTable . '.uid')
                    )
                );
                foreach ($tca['MM_match_fields'] ?? [] as $key => $value) {
                    $queryBuilder->andWhere($queryBuilder->expr()->eq(
                        $tca['MM'] . '.' . $key,
                        $queryBuilder->createNamedParameter($value)
                    ));
                }

                $lastElementTableAlias = FilterUtility::handleAlias($lastElementTable);

                $queryBuilder->join(
                    $tca['MM'],
                    $lastElementTable,
                    $lastElementTableAlias,
                    $queryBuilder->expr()->eq($lastElementTableAliasTCAMM . ".$mmTableForeignField",
                        $queryBuilder->quoteIdentifier($lastElementTableAlias . '.uid')));

                $_lastElementTableAlias = $lastElementTableAlias;

                continue;
            }

            $lastElementTableAlias = FilterUtility::handleAlias($lastElementTable);

            // Join with foreign table
            $queryBuilder->join(
                $currentTable,
                $lastElementTable,
                $lastElementTableAlias,
                $queryBuilder->expr()->eq(
                    $currentTable . '.' . $fieldName,
                    $queryBuilder->quoteIdentifier($tca['foreign_table'] . '.uid')
                )
            );

            $_lastElementTableAlias = $lastElementTableAlias;
        }

        return $_lastElementTableAlias;
    }

    /**
     * @return Generator<array<string,string,array|null>> The table name, the field name and the current tca config
     * @throws FieldDoesNotExistException
     */
    public static function walkTcaRelations(array $filterPathElements, string $tableName): Generator
    {
        $currentTable = $tableName;

        foreach ($filterPathElements as $index => $filterPathElement) {
            $tca = $GLOBALS['TCA'][$currentTable]['columns'][$filterPathElement]['config'] ?? null;
            if ($tca === null) {
                throw new FieldDoesNotExistException("No TCA field found for $currentTable.$filterPathElement");
            }

            if ($tca['foreign_table'] === null) {
                throw new FieldDoesNotExistException("TCA for $currentTable.$filterPathElement has no foreign_table");
            }

            if (($tca['MM'] ?? null) !== null) {
                // Join with mm table and foreign table
                yield [$currentTable, $filterPathElement, $tca];
                $currentTable = $tca['foreign_table'];
            } elseif ($tca['foreign_table']) {
                // Join with foreign table
                yield [$currentTable, $filterPathElement, $tca];
                $currentTable = $tca['foreign_table'];
            }
        }
    }
}
