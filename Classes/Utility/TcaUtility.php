<?php

namespace Itx\Typo3GraphQL\Utility;

class TcaUtility
{
    const TYPO3_FIELDS = [
        'uid',
        'pid',
        'tstamp',
        'crdate',
        'cruser_id',
        'deleted',
        'hidden',
        'starttime',
        'endtime',
        'sorting',
        'fe_group',
        'editlock',
        'lockToDomain',
        'lockToIP',
        'lockToWorkspace',
        'sys_language_uid',
        'l10n_parent',
        'l10n_diffsource',
        'l10n_source',
        'l10n_state',
        'l10n_children',
        't3ver_oid',
        't3ver_id',
        't3ver_wsid',
        't3ver_label',
        't3ver_state',
        't3ver_stage',
        't3ver_count',
        't3ver_tstamp',
        't3ver_move_id',
        't3_origuid',
        't3_origpid',
        't3ver_editor',
        't3ver_state'
    ];

    public static function doesFieldExist(string $tableName, string $fieldName): bool
    {
        // Check if the field exists in the TCA as an entry or if it has type none
        $tca = $GLOBALS['TCA'][$tableName]['columns'][$fieldName] ?? null;

        return ($tca !== null && $tca['config']['type'] !== 'none') || in_array($fieldName, self::TYPO3_FIELDS);
    }
}
