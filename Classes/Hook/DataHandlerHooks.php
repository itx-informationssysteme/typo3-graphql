<?php

namespace Itx\Typo3GraphQL\Hook;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerHooks
{
    public function processDatamap_afterDatabaseOperations($status, $table, $id, array $fieldArray, \TYPO3\CMS\Core\DataHandling\DataHandler &$pObj) {
        $container = GeneralUtility::getContainer();
        /** @var FrontendInterface $cache */
        $cache = $container->get('cache.typo3_graphql_cache');

        $cache->flushByTag($table);
    }
}
