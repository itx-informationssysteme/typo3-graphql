<?php

namespace Itx\Typo3GraphQL\Resolver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Generator;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Domain\Repository\FilterRepository;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterInput;
use Itx\Typo3GraphQL\Types\Skeleton\DiscreteFilterOption;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
     */
    public function fetchFiltersIncludingFacets($root, array $args, $context, ResolveInfo $resolveInfo, string $tableName, string $modelClassPath): array
    {
        $filters = $this->filterRepository->findByModel($modelClassPath);

        $discreteFilterArguments = $this->extractDiscreteFilterOptionsMap($args);

        $facets = [];
        foreach ($filters as $filter) {
            // TODO check if type === discrete

            $facet = [];
            $facet['label'] = $filter->getName();
            $facet['path'] = $filter->getFilterPath();

            $options = $this->fetchFilterOptions($tableName, $filter->getFilterPath(), $args, $discreteFilterArguments, $resolveInfo);

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

        $discreteFilterArguments = $filterArguments['discreteFilters'] ?? [];

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
     *
     * @return DiscreteFilterOption[]
     * @throws BadInputException
     * @throws DBALException
     * @throws Exception
     */
    private function fetchFilterOptions(string $tableName, string $filterPath, array $args, array $filterArguments, ResolveInfo $resolveInfo): array
    {
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);

        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        $lastElementTable = $this->buildJoinsByWalkingPath($filterPathElements, $tableName, $queryBuilder);

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

            $whereFilterTable = $this->buildJoinsByWalkingPath($whereFilterPathElements, $tableName, $queryBuilder);

            $queryBuilder->andWhere($queryBuilder->expr()->in($whereFilterTable . '.' . $whereFilterLastElement, array_map(static function($a) use ($queryBuilder) { return $queryBuilder->createNamedParameter($a); }, $whereFilter->options)));
        }

        $queryBuilder->addSelectLiteral("$lastElementTable.$lastElement AS value")->from($tableName)->groupBy("$lastElementTable.$lastElement")->addSelectLiteral("COUNT($tableName.uid) AS resultCount")->groupBy("$lastElementTable.$lastElement")->orderBy("$lastElementTable.$lastElement", 'ASC');

        $results = $queryBuilder->execute()->fetchAllAssociative() ?? [];

        $options = [];

        // Set selected to true for all options that are selected
        foreach ($results as $key => $result) {
            $selected = false;
            if (!empty($filterArguments[$filterPath]) && $resolveInfo->getFieldSelection(3)['facets']['options']['selected'] ?? false) {
                $selected = in_array($result['value'], $filterArguments[$filterPath]->options, true);
            }

            $options[] = new DiscreteFilterOption($result['value'], $result['resultCount'], $selected);
        }

        return $options;
    }

    /**
     * @throws BadInputException
     */
    private function buildJoinsByWalkingPath(array $filterPathElements, string $tableName, QueryBuilder $queryBuilder): string {
        $lastElementTable = $tableName;

        // Go through the filter path and join the tables by using the TCA MM relations
        /**
         * @var string $currentTable
         * @var string $fieldName
         * @var array  $tca
         */
        foreach ($this->walkTcaRelations($filterPathElements, $tableName) as [$currentTable, $fieldName, $tca]) {
            $lastElementTable = $tca['foreign_table'];

            if ($tca['MM'] ?? false) {
                // Join with MM and foreign table
                $queryBuilder->join($currentTable, $tca['MM'], $tca['MM'], $queryBuilder->expr()->eq($tca['MM'] . '.uid_local', $queryBuilder->quoteIdentifier($currentTable . '.uid')));

                $queryBuilder->join($tca['MM'], $tca['foreign_table'], $tca['foreign_table'], $queryBuilder->expr()->eq($tca['MM'] . '.uid_foreign', $queryBuilder->quoteIdentifier($tca['foreign_table'] . '.uid')));
                continue;
            }

            // Join with foreign table
            $queryBuilder->join($currentTable, $tca['foreign_table'], $tca['foreign_table'], $queryBuilder->expr()->eq($currentTable . '.' . $fieldName, $queryBuilder->quoteIdentifier($tca['foreign_table'] . ".uid")));
        }

        return $lastElementTable;
    }

    /**
     * @return Generator<array<string,string,array|null>> The table name, the field name and the current tca config
     * @throws BadInputException
     */
    public function walkTcaRelations(array $filterPathElements, string $tableName): Generator
    {
        $currentTable = $tableName;

        foreach ($filterPathElements as $index => $filterPathElement) {
            $tca = $GLOBALS['TCA'][$currentTable]['columns'][$filterPathElement]['config'] ?? null;
            if ($tca === null) {
                throw new BadInputException("No TCA field found for $currentTable.$filterPathElement");
            }

            if ($tca['type'] !== 'select') {
                throw new BadInputException("TCA for $currentTable.$filterPathElement is not of type select");
            }

            if ($tca['foreign_table'] === null) {
                throw new BadInputException("TCA for $currentTable.$filterPathElement has no foreign_table");
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

    /**
     * @throws BadInputException
     */
    private function getTableFromPath(string $startingTable, string $path): array
    {
        $filterPathElements = explode('.', $path);
        $lastElement = array_pop($filterPathElements);

        $currentTable = $startingTable;

        if (count($filterPathElements) > 0) {
            foreach ($filterPathElements as $pathElement) {
                $tca = $GLOBALS['TCA'][$currentTable]['columns'][$pathElement]['config'] ?? null;

                if ($tca === null) {
                    throw new BadInputException("No TCA field found for $startingTable.$pathElement");
                }

                if ($tca['type'] !== 'select') {
                    throw new BadInputException("TCA for $startingTable.$pathElement is not of type select");
                }

                if ($tca['foreign_table'] === null) {
                    throw new BadInputException("TCA for $startingTable.$pathElement has no foreign_table");
                }

                $currentTable = $tca['foreign_table'];
            }
        }

        return [$currentTable, $lastElement];
    }
}
