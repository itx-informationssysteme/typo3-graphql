<?php

namespace Itx\Typo3GraphQL\Resolver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Generator;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Domain\Repository\FilterRepository;
use Itx\Typo3GraphQL\Exception\FieldDoesNotExistException;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterInput;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterOption;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class FilterResolver
{
    protected PersistenceManager $persistenceManager;
    protected FilterRepository $filterRepository;

    public function __construct(PersistenceManager $persistenceManager, FilterRepository $filterRepository)
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
    public function fetchFiltersIncludingFacets($root, array $args, $context, ResolveInfo $resolveInfo, string $tableName, string $modelClassPath): array
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
    public function fetchFiltersWithRelationConstraintIncludingFacets($root, array $args, $context, ResolveInfo $resolveInfo, string $tableName, string $modelClassPath, string $mmTable, int $localUid): array
    {
        return $this->computeFilterOptions($root, $args, $context, $resolveInfo, $tableName, $modelClassPath, $mmTable, $localUid);
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws InvalidQueryException
     * @throws FieldDoesNotExistException
     */
    private function computeFilterOptions($root, array $args, $context, ResolveInfo $resolveInfo, string $tableName, string $modelClassPath, ?string $mmTable = null, ?int $localUid = null): array
    {
        // TODO check if type === discrete
        $discreteFilterArguments = $this->extractDiscreteFilterOptionsMap($args);
        $discreteFilterPaths = map($discreteFilterArguments)->map(fn(DiscreteFilterInput $filter) => $filter->path)->toArray();

        $filters = $this->filterRepository->findByModelAndPaths($modelClassPath, $discreteFilterPaths);

        $facets = [];
        foreach ($filters as $filter) {

            $facet = [];
            $facet['label'] = $filter->getName();
            $facet['path'] = $filter->getFilterPath();

            $options = $this->fetchFilterOptions($tableName, $filter->getFilterPath(), $args, $discreteFilterArguments, $resolveInfo, $mmTable, $localUid);

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
     * @param string|null                       $mmTable
     * @param int|null                          $localUid
     *
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws FieldDoesNotExistException
     */
    private function fetchFilterOptions(string $tableName, string $filterPath, array $args, array $filterArguments, ResolveInfo $resolveInfo, ?string $mmTable, ?int $localUid): array
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
            $queryBuilder->leftJoin($tableName, $mmTable, 'mm', $queryBuilder->expr()->eq('mm.uid_foreign', $queryBuilder->quoteIdentifier($tableName . '.uid')));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->createNamedParameter($localUid)));
        }

        if (count($storagePids) > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->in($tableName . '.pid', array_map(static function($a) use ($queryBuilder) { return $queryBuilder->createNamedParameter($a, \PDO::PARAM_INT); }, $storagePids)));
        }

        $queryBuilder->andWhere($queryBuilder->expr()->eq($tableName . '.sys_language_uid', $queryBuilder->createNamedParameter($language, \PDO::PARAM_INT)));

        // Filter out filter arguments that are not part of the current filter path
        $whereFilters = array_filter($filterArguments, static function(DiscreteFilterInput $filterInput) use ($filterPath) {
            return $filterInput->path !== $filterPath && count($filterInput->options) > 0;
        });

        foreach ($whereFilters as $whereFilter) {
            $whereFilterPathElements = explode('.', $whereFilter->path);
            $whereFilterLastElement = array_pop($whereFilterPathElements);

            $whereFilterTable = self::buildJoinsByWalkingPath($whereFilterPathElements, $tableName, $queryBuilder);

            $queryBuilder->andWhere($queryBuilder->expr()->in($whereFilterTable . '.' . $whereFilterLastElement, array_map(static function($a) use ($queryBuilder) { return $queryBuilder->createNamedParameter($a); }, $whereFilter->options)));
        }

        $queryBuilder->addSelectLiteral("$lastElementTable.$lastElement AS value")->from($tableName)->groupBy("$lastElementTable.$lastElement")->addSelectLiteral("COUNT($tableName.uid) AS resultCount")->groupBy("$lastElementTable.$lastElement")->orderBy("$lastElementTable.$lastElement", 'ASC');


        $sql = $queryBuilder->getSQL();
        $params = $queryBuilder->getParameters();

        $results = $queryBuilder->execute()->fetchAllAssociative() ?? [];

        $options = [];

        $isSelectedNeeded = isset($resolveInfo->getFieldSelection(3)['facets']['options']['selected']) && $resolveInfo->getFieldSelection(3)['facets']['options']['selected'];

        // Set selected to true for all options that are selected
        foreach ($results as $key => $result) {
            $selected = false;
            if ($isSelectedNeeded && !empty($filterArguments[$filterPath])) {
                $selected = in_array($result['value'], $filterArguments[$filterPath]->options, true);
            }

            $options[] = new DiscreteFilterOption($result['value'], $result['resultCount'], $selected);
        }

        return $options;
    }

    /**
     * @throws FieldDoesNotExistException
     */
    public static function buildJoinsByWalkingPath(array $filterPathElements, string $tableName, QueryBuilder $queryBuilder): string
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
                // Join with MM and foreign table
                $queryBuilder->join($currentTable, $tca['MM'], $tca['MM'], $queryBuilder->expr()->eq($tca['MM'] . '.uid_foreign', $queryBuilder->quoteIdentifier($currentTable . '.uid')));
                $queryBuilder->andWhere($queryBuilder->expr()->eq($tca['MM'] . '.tablenames', $queryBuilder->createNamedParameter($currentTable)));

                $queryBuilder->join($tca['MM'], $tca['foreign_table'], $tca['foreign_table'], $queryBuilder->expr()->eq($tca['MM'] . '.uid_local', $queryBuilder->quoteIdentifier($tca['foreign_table'] . '.uid')));
                continue;
            }

            // Join with foreign table
            $queryBuilder->join($currentTable, $tca['foreign_table'], $tca['foreign_table'], $queryBuilder->expr()->eq($currentTable . '.' . $fieldName, $queryBuilder->quoteIdentifier($tca['foreign_table'] . ".uid")));
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
