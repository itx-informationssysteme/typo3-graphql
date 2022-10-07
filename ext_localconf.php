<?php
defined('TYPO3') or die('Access denied.');


call_user_func(
    function () {
        // Register cache
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['typo3_graphql_cache'] ??= [];
    }
);
