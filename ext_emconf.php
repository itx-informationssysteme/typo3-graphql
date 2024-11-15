<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 GraphQL API',
    'description' => 'This extension provides a GraphQL API for TYPO3.',
    'category' => 'extension',
    'author' => 'it.x informationssysteme gmbh',
    'author_email' => 'typo-itx@itx.de',
    'state' => 'beta',
    'version' => '1.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.1-12.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
