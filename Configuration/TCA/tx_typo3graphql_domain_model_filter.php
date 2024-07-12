<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:typo3_graphql/Resources/Private/Language/locallang_db.xlf:tx_typo3graphql_domain_model_filter',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'languageField' => 'sys_language_uid',
        'translationSource' => 'l10n_source',
        'origUid' => 't3_origuid',
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:typo3_graphql/Resources/Public/Icons/icon_tx_blogexample_domain_model_tag.gif',
    ],
    'columns' => [
        'name' => [
            'label' => 'LLL:EXT:typo3_graphql/Resources/Private/Language/locallang_db.xlf:tx_typo3graphql_domain_model_filter.name',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'trim',
                'required' => true,
                'max' => 256,
            ],
        ],
        'model' => [
            'label' => 'LLL:EXT:typo3_graphql/Resources/Private/Language/locallang_db.xlf:tx_typo3graphql_domain_model_filter.model',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'required' => true,
            ],
        ],
        'type_of_filter' => [
            'label' => 'LLL:EXT:typo3_graphql/Resources/Private/Language/locallang_db.xlf:tx_typo3graphql_domain_model_filter.typeOfFilter',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'Discrete Filter',
                        'value' => 'discrete',
                    ],
                    [
                        'label' => 'Range Filter',
                        'value' => 'range',
                    ],
                ],
            ],
        ],
        'filter_path' => [
            'label' => 'LLL:EXT:typo3_graphql/Resources/Private/Language/locallang_db.xlf:tx_typo3graphql_domain_model_filter.filterPath',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'trim',
                'required' => true,
                'max' => 256,
            ],
        ],
        'unit' => [
            'label' => 'LLL:EXT:basicdistribution/Resources/Private/Language/Backend.xlf:typo3graphql.unit',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
            'displayCond' => 'FIELD:type_of_filter:=:range',
        ],
        'categories' => [
            'config' => [
                'type' => 'category',
            ],
        ],
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        '',
                        0,
                    ],
                ],
                'foreign_table' => 'tx_typo3graphql_domain_model_filter',
                'foreign_table_where' =>
                    'AND {#tx_typo3graphql_domain_model_filter}.{#pid}=###CURRENT_PID###'
                    . ' AND {#tx_typo3graphql_domain_model_filter}.{#sys_language_uid} IN (-1,0)',
                'default' => 0,
            ],
        ],
        'l10n_source' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
        't3ver_label' => [
            'displayCond' => 'FIELD:t3ver_label:REQ:true',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.versionLabel',
            'config' => [
                'type' => 'none',
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.enabled',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
    ],
    'types' => [
        0 => ['showitem' => 'sys_language_uid, l10n_parent, hidden, name, type_of_filter, model, filter_path, unit, categories'],
    ],
];
