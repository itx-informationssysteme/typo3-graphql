<?php

namespace Itx\Typo3GraphQL\Resolver;

use Itx\Typo3GraphQL\Exception\BadInputException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class FilterResolver
{
    protected PersistenceManager $persistenceManager;

    public function __construct(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function fetchFilterOptions(string $filterPath, string $tableName): array
    {
        $filterPathElements = explode('.', $filterPath);
        $lastElement = array_pop($filterPathElements);

        // Query Builder
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        // Go through the filter path and join the tables by using the TCA MM relations
        $currentTable = $tableName;

        if (count($filterPathElements) > 0) {
            foreach ($filterPathElements as $filterPathElement) {
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

                // Join with mm table and foreign table
                $queryBuilder->join($currentTable, $tca['MM'], $tca['MM'], $queryBuilder->expr()->eq($tca['MM'] . '.uid_local', $queryBuilder->quoteIdentifier($currentTable . '.uid')));

                $queryBuilder->join($tca['MM'], $tca['foreign_table'], $tca['foreign_table'], $queryBuilder->expr()->eq($tca['MM'] . '.uid_foreign', $queryBuilder->quoteIdentifier($tca['foreign_table'] . '.uid')));

                $currentTable = $tca['foreign_table'];
            }
        }

        // Group and count
        $queryBuilder->addSelectLiteral("$currentTable.$lastElement AS value")->from($tableName)->groupBy("$currentTable.$lastElement")->addSelectLiteral("COUNT($currentTable.$lastElement) AS resultCount")->groupBy("$currentTable.$lastElement")->orderBy("$currentTable.$lastElement", 'ASC');

        $result = $queryBuilder->execute()->fetchAllAssociative();

        // TODO cleanup and security

        return $result;
    }
}
