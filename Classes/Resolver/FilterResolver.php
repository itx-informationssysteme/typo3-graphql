<?php

namespace Itx\Typo3GraphQL\Resolver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Domain\Repository\FilterRepository;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
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

        $facets = [];
        foreach ($filters as $filter) {
            $facet = [];
            $facet['label'] = $filter->getName();
            $facet['path'] = $filter->getFilterPath();

            $facet['options'] = $this->fetchFilterOptions($tableName, $filter->getFilterPath(), $args);

            $facets[] = $facet;
        }

        return $facets;
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws BadInputException
     */
    public function fetchFilterOptions(string $tableName, string $filterPath, array $args): array
    {
        $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
        $storagePids = (array)($args[QueryArgumentsUtility::$pageIds] ?? []);

        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        // Go through the filter path and join the tables by using the TCA MM relations
        $currentTable = $tableName;

        if (count($filterPathElements) > 0) {
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

                // For our model's table we want to prefilter based on the business logic
                if ($index === 0) {
                    if (count($storagePids) > 0) {
                        $queryBuilder->andWhere($queryBuilder->expr()->in($currentTable . '.pid', array_map(static function($a) use ($queryBuilder) { return $queryBuilder->createNamedParameter($a, \PDO::PARAM_INT); }, $storagePids)));
                    }

                    $queryBuilder->andWhere($queryBuilder->expr()->eq($currentTable . '.sys_language_uid', $queryBuilder->createNamedParameter($language, \PDO::PARAM_INT)));
                }

                if (($tca['MM'] ?? null) !== null) {
                    // Join with mm table and foreign table
                    $queryBuilder->join($currentTable, $tca['MM'], $tca['MM'], $queryBuilder->expr()->eq($tca['MM'] . '.uid_local', $queryBuilder->quoteIdentifier($currentTable . '.uid')));

                    $queryBuilder->join($tca['MM'], $tca['foreign_table'], $tca['foreign_table'], $queryBuilder->expr()->eq($tca['MM'] . '.uid_foreign', $queryBuilder->quoteIdentifier($tca['foreign_table'] . '.uid')));

                    $currentTable = $tca['foreign_table'];
                } elseif ($tca['foreign_table']) {
                    // Join with foreign table
                    $queryBuilder->join($currentTable, $tca['foreign_table'], $tca['foreign_table'], $queryBuilder->expr()->eq($currentTable . '.' . $filterPathElement, $queryBuilder->quoteIdentifier($tca['foreign_table'] . ".uid")));

                    $currentTable = $tca['foreign_table'];
                }
            }
        }

        // Group and count
        $queryBuilder->addSelectLiteral("$currentTable.$lastElement AS value")->from($tableName)->groupBy("$currentTable.$lastElement")->addSelectLiteral("COUNT($tableName.uid) AS resultCount")->groupBy("$currentTable.$lastElement")->orderBy("$currentTable.$lastElement", 'ASC');

        $result = $queryBuilder->execute()->fetchAllAssociative() ?? [];

        // TODO cleanup and security

        return $result;
    }
}
