<?php
declare(strict_types = 1);

return [
    \Itx\Typo3GraphQL\Domain\Model\Page::class => [
        'tableName' => 'pages',
    ],
    \Itx\Typo3GraphQL\Domain\Model\PageContent::class => [
        'tableName' => 'tt_content',
    ],
];
