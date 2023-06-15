<?php

namespace Itx\Typo3GraphQL\Resolver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Generator;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Domain\Repository\FilterRepository;
use Itx\Typo3GraphQL\Events\ModifyQueryBuilderForFilteringEvent;
use Itx\Typo3GraphQL\Exception\FieldDoesNotExistException;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterInput;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterOption;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
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
        // TODO check if type === discrete
        $discreteFilterArguments = $this->extractDiscreteFilterOptionsMap($args);
        $discreteFilterPaths = map($discreteFilterArguments)->map(fn(DiscreteFilterInput $filter) => $filter->path)->toArray();

        // Switch keys and values for $discreteFilterPaths
        $filters = array_flip($discreteFilterPaths);

        // Reorder to the same order as the discrete filter paths
        $filterResult = $this->filterRepository->findByModelAndPaths($modelClassPath, $discreteFilterPaths);

        // Sort them as we received them
        foreach ($filterResult as $filter) {
            $filters[$filter->getFilterPath()] = $filter;
        }

        $facets = [];
        foreach ($filters as $filter) {

            $facet = [];
            $facet['label'] = $filter->getName();
            $facet['path'] = $filter->getFilterPath();

            $options = $this->fetchAndProcessFilterOptions($tableName,
                                                           $filter->getFilterPath(),
                                                           $args,
                                                           $discreteFilterArguments,
                                                           $resolveInfo,
                                                           $modelClassPath,
                                                           $mmTable,
                                                           $localUid);

            $facet['options'] = $options;

            $facets[] = $facet;
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
     * @param string                            $tableName
     * @param string                            $filterPath
     * @param array                             $args
     * @param array<string,DiscreteFilterInput> $filterArguments
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
                                        array       $filterArguments,
                                        ResolveInfo $resolveInfo,
                                        string      $modelClassPath,
                                        ?string     $mmTable,
                                        ?int        $localUid): array
    {
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);

        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

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

        $queryBuilder->andWhere($queryBuilder->expr()->eq($tableName . '.sys_language_uid',
                                                          $queryBuilder->createNamedParameter($language, \PDO::PARAM_INT)));

        // Filter out filter arguments that are not part of the current filter path
        $whereFilters = array_filter($filterArguments,
            static function(DiscreteFilterInput $filterInput) use ($filterPath) {
                return $filterInput->path !== $filterPath && count($filterInput->options) > 0;
            });

        /** @var DiscreteFilterInput $whereFilter */
        foreach ($whereFilters as $whereFilter) {
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

        /** @var ModifyQueryBuilderForFilteringEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyQueryBuilderForFilteringEvent($modelClassPath,
                                                                                          $tableName,
                                                                                          $queryBuilder,
                                                                                          $args));
        $queryBuilder = $event->getQueryBuilder();

        $queryBuilder->addSelectLiteral("$lastElementTable.$lastElement AS value")
                     ->from($tableName)
                     ->groupBy("$lastElementTable.$lastElement")
                     ->addSelectLiteral("COUNT($tableName.uid) AS resultCount")
                     ->groupBy("$lastElementTable.$lastElement")
                     ->orderBy("$lastElementTable.$lastElement", 'ASC');

        return $queryBuilder->execute()->fetchAllAssociative() ?? [];
    }

    /**
     * @throws FieldDoesNotExistException
     * @throws Exception
     * @throws DBALException
     */
    private function fetchAndProcessFilterOptions(string      $tableName,
                                                  string      $filterPath,
                                                  array       $args,
                                                  array       $filterArguments,
                                                  ResolveInfo $resolveInfo,
                                                  string      $modelClassPath,
                                                  ?string     $mmTable,
                                                  ?int        $localUid): array
    {
        $options = [];

        $isSelectedNeeded = isset($resolveInfo->getFieldSelection(3)['facets']['options']['selected']) &&
            $resolveInfo->getFieldSelection(3)['facets']['options']['selected'];

        // Check whether the disabled field was requested
        $isDisabledNeeded = isset($resolveInfo->getFieldSelection(3)['facets']['options']['disabled']) &&
            $resolveInfo->getFieldSelection(3)['facets']['options']['disabled'];

        $cacheKey = md5($tableName . $filterPath . $args[QueryArgumentsUtility::$language]);

        if (!$this->cache->has($cacheKey)) {
            $originalFilterOptions = $this->fetchFilterOptions($tableName,
                                                               $filterPath,
                                                               $args,
                                                               [],
                                                               $resolveInfo,
                                                               $modelClassPath,
                                                               $mmTable,
                                                               $localUid);

            // Cache for 1 day
            $this->cache->set($cacheKey, $originalFilterOptions, ['filter_options'], 86400);
        } else {
            $originalFilterOptions = $this->cache->get($cacheKey);
        }

        // Index array with value as key
        $actualFilterOptions = $this->fetchFilterOptions($tableName,
                                                         $filterPath,
                                                         $args,
                                                         $filterArguments,
                                                         $resolveInfo,
                                                         $modelClassPath,
                                                         $mmTable,
                                                         $localUid);
        $actualFilterOptions = array_combine(array_column($actualFilterOptions, 'value'), $actualFilterOptions);

        // Set selected to true for all options that are selected
        foreach ($originalFilterOptions as $originalFilterOption) {
            foreach (explode(",", $originalFilterOption['value']) as $value) {
                $selected = false;
                if ($isSelectedNeeded && !empty($filterArguments[$filterPath])) {
                    $selected = in_array($value, $filterArguments[$filterPath]->options, true);
                }

                $disabled = false;

                if ($isDisabledNeeded && empty($actualFilterOptions[$value])) {
                    $disabled = true;
                }

                if (!isset($options[$value])) {
                    $options[$value] =
                        new DiscreteFilterOption($value, $originalFilterOption['resultCount'], $selected, $disabled);
                    continue;
                }

                $options[$value]->resultCount += $originalFilterOption['resultCount'];

                if ($selected) {
                    $options[$value]->selected = true;
                }
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
        $lastElementTable = $tableName;

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

                $queryBuilder->join($tca['MM'],
                                    $tca['foreign_table'],
                                    $tca['foreign_table'],
                                    $queryBuilder->expr()->eq($tca['MM'] . ".$mmTableForeignField",
                                                              $queryBuilder->quoteIdentifier($tca['foreign_table'] . '.uid')));
                continue;
            }

            // Join with foreign table
            $queryBuilder->join($currentTable,
                                $tca['foreign_table'],
                                $tca['foreign_table'],
                                $queryBuilder->expr()->eq($currentTable . '.' . $fieldName,
                                                          $queryBuilder->quoteIdentifier($tca['foreign_table'] . ".uid")));
        }

        return $lastElementTable;
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
