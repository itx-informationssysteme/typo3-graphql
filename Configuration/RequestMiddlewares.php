<?php

return [
    'frontend' => [
        'itx/typo3_graphql/request-object-fixer' => [
            'target' => \Itx\Typo3GraphQL\Middleware\RequestObjectFixerMiddleware::class,
            'before' => [
                'itx/typo3_graphql/graphql-server',
                'itx/typo3_graphql/graphql-cors',
            ],
        ],
        'itx/typo3_graphql/graphql-server' => [
            'target' => \Itx\Typo3GraphQL\Middleware\GraphQLServerMiddleware::class,
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
            'before' => [
                'typo3/cms-frontend/shortcut-and-mountpoint-redirect',
            ]
        ],
        'itx/typo3_graphql/graphql-cors' => [
            'target' => \Itx\Typo3GraphQL\Middleware\CorsMiddleware::class,
            'before' => [
                'itx/typo3_graphql/graphql-server',
                'itx/typo3_graphql/extbase-bridge',
            ],
        ],
    ],
];
