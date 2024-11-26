<?php

namespace Itx\Typo3GraphQL\Resolver;

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
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterInput;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterOption;
use Itx\Typo3GraphQL\Types\Skeleton\Range;
use Itx\Typo3GraphQL\Types\Skeleton\RangeFilterInput;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use Itx\Typo3GraphQL\Utility\TcaUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class FilterResolver
{
    protected PersistenceManager $persistenceManager;
    protected FilterRepository $filterRepository;

    public function __construct(PersistenceManager                 $persistenceManager,
                                FilterRepository                   $filterRepository,
                                protected EventDispatcherInterface $eventDispatcher,
                                protected FrontendInterface        $cache)
    {
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
    public function fetchFiltersIncludingFacets($root,
                                                array $args,
        $context,
                                                ResolveInfo $resolveInfo,
                                                string $tableName,
                                                string $modelClassPath): array
    {
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
    public function fetchFiltersWithRelationConstraintIncludingFacets($root,
                                                                      array $args,
        $context,
                                                                      ResolveInfo $resolveInfo,
                                                                      string $tableName,
                                                                      string $modelClassPath,
                                                                      string $mmTable,
                                                                      int $localUid): array
    {
        return $this->computeFilterOptions($root,
                                           $args,
                                           $context,
                                           $resolveInfo,
                                           $tableName,
                                           $modelClassPath,
                                           $mmTable,
                                           $localUid);
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws InvalidQueryException
     * @throws FieldDoesNotExistException
     */
    private function computeFilterOptions($root,
                                          array $args,
        $context,
                                          ResolveInfo $resolveInfo,
                                          string $tableName,
                                          string $modelClassPath,
                                          ?string $mmTable = null,
                                          ?int $localUid = null): array
    {
        $facets = [];

        $discreteFilterArguments = $this->extractDiscreteFilterOptionsMap($args);
        $discreteFilterPaths = map($discreteFilterArguments)->map(fn(DiscreteFilterInput $filter) => $filter->path)->toArray();

        $rangefilterArguments = $this->extractRangeFilterObjectsMap($args);
        $rangeFilterPaths = map($rangefilterArguments)->map(fn(RangeFilterInput $filter) => $filter->path)->toArray();

        if (array_key_exists('discreteFilters', $args['filters'])) {

            // Switch keys and values for $discreteFilterPaths
            $filters = array_flip($discreteFilterPaths);

            // Reorder to the same order as the discrete filter paths
            $filterResult =
                $this->filterRepository->findByModelAndPathsAndType($modelClassPath, $discreteFilterPaths, 'discrete');

            // Sort them as we received them
            foreach ($filterResult as $filter) {
                $filters[$filter->getFilterPath()] = $filter;
            }

            foreach ($filters as $path => $filter) {
                if (!$filter instanceof Filter) {
                    throw new \RuntimeException("Discrete Filter $path not found");
                }

                $facet = [];
                $facet['label'] = $filter->getName();
                $facet['path'] = $filter->getFilterPath();
                $facet['type'] = FacetType::DISCRETE;

                $options = $this->fetchAndProcessFilterOptions($tableName,
                                                               $filter->getFilterPath(),
                                                               $args,
                                                               $discreteFilterArguments,
                                                               $rangefilterArguments,
                                                               $resolveInfo,
                                                               $modelClassPath,
                                                               $mmTable,
                                                               $localUid);

                $facet['options'] = $options;

                $facets[] = $facet;
            }
        }

        if (array_key_exists('rangeFilters', $args['filters'])) {
            $filters = array_flip($rangeFilterPaths);
            $filterResult = $this->filterRepository->findByModelAndPathsAndType($modelClassPath, $rangeFilterPaths, 'range');

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

                $facet['range'] = $this->fetchRanges($tableName,
                                                     $filter->getFilterPath(),
                                                     $args,
                                                     $discreteFilterArguments,
                                                     $rangefilterArguments,
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
            $rangeFilterArguments[$filter['path']] = new RangeFilterInput($filter['path'],
                                                                          new Range($filter['range']['min'] ?? null,
                                                                                    $filter['range']['max'] ?? null));
            unset($rangeFilterArguments[$key]);
        }

        return $rangeFilterArguments;
    }

    /**
     * @param string                            $tableName
     * @param string                            $filterPath
     * @param array                             $args
     * @param array<string,DiscreteFilterInput> $discreteFilterArguments
     * @param array<string,RangeFilterInput>    $rangeFilterArguments
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
    private function fetchFilterOptions(string      $tableName,
                                        string      $filterPath,
                                        array       $args,
                                        array       $discreteFilterArguments,
                                        array       $rangeFilterArguments,
                                        ResolveInfo $resolveInfo,
                                        string      $modelClassPath,
                                        ?string     $mmTable,
                                        ?int        $localUid,
                                        bool        $triggerEvent): array
    {
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);

        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $frontendRestrictionContainer = GeneralUtility::makeInstance(FrontendRestrictionContainer::class);
        $queryBuilder->setRestrictions($frontendRestrictionContainer);

        $lastElementTable = self::buildJoinsByWalkingPath($filterPathElements, $tableName, $queryBuilder);

        // If we have a relation constraint, we need to add the constraint to the query
        if ($mmTable !== null && $localUid !== null) {
            $queryBuilder->leftJoin($tableName,
                                    $mmTable,
                                    'mm',
                                    $queryBuilder->expr()
                                                 ->eq('mm.uid_foreign', $queryBuilder->quoteIdentifier($tableName . '.uid')));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->createNamedParameter($localUid)));
        }

        if (count($storagePids) > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->in($tableName . '.pid',
                                                              array_map(static fn($a) => $queryBuilder->createNamedParameter($a,
                                                                                                                             \PDO::PARAM_INT),
                                                                  $storagePids)));
        }

        if (isset($GLOBALS['TCA'][$tableName]['columns']['sys_language_uid'])) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($tableName . '.sys_language_uid',
                $queryBuilder->createNamedParameter($language, \PDO::PARAM_INT)));
        }

        $this->applyDiscreteFilters($discreteFilterArguments, $tableName, $queryBuilder, $filterPath);
        $this->applyRangeFilters($rangeFilterArguments, $tableName, $queryBuilder, $filterPath);

        if ($triggerEvent) {
            /** @var ModifyQueryBuilderForFilteringEvent $event */
            $event = $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent($modelClassPath,
                                                                                              $tableName,
                                                                                              $queryBuilder,
                                                                                              $args,
                                                                                              FilterEventSource::FILTER,
                                                                                              'discrete'));
            $queryBuilder = $event->getQueryBuilder();

        }

        $fieldPrefix = "$lastElementTable.";
        if (!TcaUtility::doesFieldExist($lastElementTable, $lastElement)) {
            $fieldPrefix = '';
        }

        $queryBuilder->addSelectLiteral("$lastElementTable.$lastElement AS value")
                     ->from($tableName)
                     ->addSelectLiteral("COUNT($tableName.uid) AS resultCount")
                     ->groupBy("$fieldPrefix$lastElement")
                     ->orderBy("$fieldPrefix$lastElement", 'ASC');

        $result = $queryBuilder->execute()->fetchAllAssociative() ?? [];

        return $this->mapFilterOptions($result);
    }

    /**
     * @param string                            $tableName
     * @param string                            $filterPath
     * @param array                             $args
     * @param array<string,DiscreteFilterInput> $discreteFilterArguments
     * @param array<string,RangeFilterInput>    $rangeFilterArguments
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
    private function fetchAndProcessFilterOptions(string      $tableName,
                                                  string      $filterPath,
                                                  array       $args,
                                                  array       $discreteFilterArguments,
                                                  array       $rangeFilterArguments,
                                                  ResolveInfo $resolveInfo,
                                                  string      $modelClassPath,
                                                  ?string     $mmTable,
                                                  ?int        $localUid): array
    {
        $isSelectedNeeded = isset($resolveInfo->getFieldSelection(3)['facets']['options']['selected']) &&
            $resolveInfo->getFieldSelection(3)['facets']['options']['selected'];

        $cacheKey = md5($tableName . $filterPath . $args[QueryArgumentsUtility::$language]);

        if (!$this->cache->has($cacheKey)) {
            $originalFilterOptions = $this->fetchFilterOptions($tableName,
                                                               $filterPath,
                                                               $args,
                                                               [],
                                                               [],
                                                               $resolveInfo,
                                                               $modelClassPath,
                                                               $mmTable,
                                                               $localUid,
                                                               false);


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
        $actualFilterOptions = $this->fetchFilterOptions($tableName,
                                                         $filterPath,
                                                         $args,
                                                         $discreteFilterArguments,
                                                         $rangeFilterArguments,
                                                         $resolveInfo,
                                                         $modelClassPath,
                                                         $mmTable,
                                                         $localUid,
                                                         true);

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
    private function fetchRanges(string      $tableName,
                                 string      $filterPath,
                                 array       $args,
                                 array       $discreteFilterArguments,
                                 array       $rangeFilterArguments,
                                 ResolveInfo $resolveInfo,
                                 string      $modelClassPath,
                                 ?string     $mmTable,
                                 ?int        $localUid): Range
    {
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);

        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $frontendRestrictionContainer = GeneralUtility::makeInstance(FrontendRestrictionContainer::class);
        $queryBuilder->setRestrictions($frontendRestrictionContainer);

        $lastElementTable = self::buildJoinsByWalkingPath($filterPathElements, $tableName, $queryBuilder);

        // If we have a relation constraint, we need to add the constraint to the query
        if ($mmTable !== null && $localUid !== null) {
            $queryBuilder->leftJoin($tableName,
                                    $mmTable,
                                    'mm',
                                    $queryBuilder->expr()
                                                 ->eq('mm.uid_foreign', $queryBuilder->quoteIdentifier($tableName . '.uid')));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->createNamedParameter($localUid)));
        }

        if (count($storagePids) > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->in($tableName . '.pid',
                                                              array_map(static fn($a) => $queryBuilder->createNamedParameter($a,
                                                                                                                             \PDO::PARAM_INT),
                                                                  $storagePids)));
        }

        if (isset($GLOBALS['TCA'][$tableName]['columns']['sys_language_uid'])) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($tableName . '.sys_language_uid',
                $queryBuilder->createNamedParameter($language, \PDO::PARAM_INT)));
        }

        $this->applyDiscreteFilters($discreteFilterArguments, $tableName, $queryBuilder, $filterPath);
        $this->applyRangeFilters($rangeFilterArguments, $tableName, $queryBuilder, $filterPath);

        /** @var ModifyQueryBuilderForFilteringEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent($modelClassPath,
                                                                                          $tableName,
                                                                                          $queryBuilder,
                                                                                          $args,
                                                                                          FilterEventSource::FILTER,
                                                                                          'range'));
        $queryBuilder = $event->getQueryBuilder();

        $fieldPrefix = "$lastElementTable.";
        if (!TcaUtility::doesFieldExist($lastElementTable, $lastElement)) {
            $fieldPrefix = '';
        }

        $queryBuilder->addSelectLiteral("MIN($fieldPrefix$lastElement) AS min, MAX($fieldPrefix$lastElement) AS max")
                     ->from($tableName);

        $result = $queryBuilder->execute()->fetchAllAssociative() ?? [];

        return new Range($result[0]['min'] ?? 0, $result[0]['max'] ?? 0);
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
        $otherFilters = array_filter($filterInputs,
            static function(DiscreteFilterInput $filterInput) use ($filterPath) {
                return $filterInput->path !== $filterPath && count($filterInput->options) > 0;
            });

        /** @var DiscreteFilterInput $whereFilter */
        foreach ($otherFilters as $whereFilter) {
            $whereFilterPathElements = explode('.', $whereFilter->path);
            $whereFilterLastElement = array_pop($whereFilterPathElements);

            $whereFilterTable = self::buildJoinsByWalkingPath($whereFilterPathElements, $tableName, $queryBuilder);

            $inSetExpressions = [];

            foreach ($whereFilter->options as $option) {
                $inSetExpressions[] = $queryBuilder->expr()->inSet($whereFilterTable . '.' . $whereFilterLastElement,
                                                                   $queryBuilder->createNamedParameter($option));
            }

            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$inSetExpressions));
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
    private function applyRangeFilters(array        $filterInputs,
                                       string       $tableName,
                                       QueryBuilder $queryBuilder,
                                       string       $filterPath): void
    {
        // Filter out filter arguments that are not part of the current filter path
        $otherFilters = array_filter($filterInputs,
            static function(RangeFilterInput $filterInput) use ($filterPath) {
                return $filterInput->path !== $filterPath &&
                    ($filterInput->range->min !== null || $filterInput->range->max !== null);
            });

        /** @var RangeFilterInput $whereFilter */
        foreach ($otherFilters as $whereFilter) {
            $whereFilterPathElements = explode('.', $whereFilter->path);
            $whereFilterLastElement = array_pop($whereFilterPathElements);

            $whereFilterTable = self::buildJoinsByWalkingPath($whereFilterPathElements, $tableName, $queryBuilder);

            $andExpressions = [];

            if ($whereFilter->range->min !== null) {
                $andExpressions[] = $queryBuilder->expr()->gte($whereFilterTable . '.' . $whereFilterLastElement,
                                                               $queryBuilder->createNamedParameter($whereFilter->range->min));
            }

            if ($whereFilter->range->max !== null) {
                $andExpressions[] = $queryBuilder->expr()->lte($whereFilterTable . '.' . $whereFilterLastElement,
                                                               $queryBuilder->createNamedParameter($whereFilter->range->max));
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
            foreach (explode(",", trim($rawFilterOption['value'])) as $value) {
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
    public static function buildJoinsByWalkingPath(array        $filterPathElements,
                                                   string       $tableName,
                                                   QueryBuilder $queryBuilder): string
    {
        $joinedTables[] = $queryBuilder->getQueryParts()["from"][0]["table"];
        $i = 1;
        $lastElementTableAlias = NULL;

        // Go through the filter path and join the tables by using the TCA MM relations
        /**
         * @var string $currentTable
         * @var string $fieldName
         * @var array  $tca
         */
        foreach (self::walkTcaRelations($filterPathElements, $tableName) as [$currentTable, $fieldName, $tca]) {
            $lastElementTable = $tca['foreign_table'];

            if ($tca['MM'] ?? false) {
                // Figure out from which side of the MM table we need to join TODO: This might not be robust enough
                $isLocalTable = ($tca['MM_match_fields']['tablenames'] ?? '') === $currentTable;

                $mmTableLocalField = $isLocalTable ? 'uid_foreign' : 'uid_local';
                $mmTableForeignField = $isLocalTable ? 'uid_local' : 'uid_foreign';

                // Join with MM and foreign table
                $queryBuilder->join($currentTable,
                    $tca['MM'],
                    $tca['MM'],
                    $queryBuilder->expr()->eq($tca['MM'] . ".$mmTableLocalField",
                        $queryBuilder->quoteIdentifier($currentTable . '.uid')));
                foreach ($tca['MM_match_fields'] ?? [] as $key => $value) {
                    $queryBuilder->andWhere($queryBuilder->expr()->eq($tca['MM'] . '.' . $key,
                        $queryBuilder->createNamedParameter($value)));
                }

                $lastElementTableAlias = $lastElementTable;
                if(in_array($lastElementTable, $joinedTables)){
                    $lastElementTableAlias = $lastElementTable . $i++;
                }
                $joinedTables[] = $lastElementTableAlias;

                $queryBuilder->join(
                    $tca['MM'],
                    $lastElementTable,
                    $lastElementTableAlias,
                    $queryBuilder->expr()->eq($tca['MM'] . ".$mmTableForeignField",
                        $queryBuilder->quoteIdentifier($lastElementTableAlias . '.uid')));

                continue;
            }

            // Join with foreign table
            $queryBuilder->join($currentTable,
                                $lastElementTable,
                                $lastElementTable,
                                $queryBuilder->expr()->eq($currentTable . '.' . $fieldName,
                                                          $queryBuilder->quoteIdentifier($tca['foreign_table'] . ".uid")));
            $joinedTables[] = $lastElementTable;
        }

        return $lastElementTableAlias;
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
