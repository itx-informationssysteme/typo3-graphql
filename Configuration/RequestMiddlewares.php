<?php

use Itx\Typo3GraphQL\Middleware\ExtbaseBridge;

return [
    'frontend' => [
        'itx/typo3_graphql/graphql-server' => [
            'target' => \Itx\Typo3GraphQL\Middleware\GraphQLServerMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
            ],
        ],
        'itx/typo3_graphql/extbase-bridge' => [
            'target' => ExtbaseBridge::class,
            'before' => [
                'itx/typo3_graphql/graphql-server'
            ],
        ],
        'itx/typo3_graphql/graphql-cors' => [
            'target' => \Itx\Typo3GraphQL\Middleware\CorsMiddleware::class,
            'before' => [
                'itx/typo3_graphql/graphql-server',
                'itx/typo3_graphql/extbase-bridge'
            ],
        ],
    ],
];
