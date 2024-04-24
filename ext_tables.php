<?php
defined('TYPO3') || die('Access denied.');

use Itx\Typo3GraphQL\Hook\DataHandlerHooks;

call_user_func(
    function()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['typo3_graphql'] = DataHandlerHooks::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['typo3_graphql'] = DataHandlerHooks::class;
    }
);
