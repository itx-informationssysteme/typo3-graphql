<?php

return [
    'frontend' => [
        'itx/typo3_graphql/graphql-server' => [
            'target' => \Itx\Typo3GraphQL\Middleware\GraphQLServerMiddleware::class,
            'before' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
        'itx/typo3_graphql/graphql-cors' => [
            'target' => \Itx\Typo3GraphQL\Middleware\CorsMiddleware::class,
            'before' => [
                'itx/typo3_graphql/graphql-server',
            ],
        ],
    ],
];
