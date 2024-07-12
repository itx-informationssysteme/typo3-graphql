<?php

$EM_CONF['typo3_graphql'] = [
    'title' => 'TYPO3 GraphQL API',
    'description' => 'This extension provides a GraphQL API for TYPO3.',
    'category' => 'extension',
    'author' => 'Benjamin Jasper',
    'author_email' => 'benjamin.jasper@itx.de',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.1-12.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
