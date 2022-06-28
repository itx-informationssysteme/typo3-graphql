<?php

namespace Itx\Typo3GraphQL\Resolver;

use Itx\Typo3GraphQL\Utility\PaginationUtility;

class PaginatedQueryResult
{
    public array $edges;
    public int $totalCount;
    public array $pageInfo;

    public function __construct(array $items, int $totalCount, int $offset, int $limit)
    {
        $previousCursor = $offset;

        foreach ($items as $counter => $item) {
            $cursor = $previousCursor + $counter + 1;

            $this->edges[] = [
                'node' => $item,
                'cursor' => PaginationUtility::toCursor($cursor),
            ];
        }

        $this->totalCount = $totalCount;

        $this->pageInfo = [
            'endCursor' => PaginationUtility::toCursor($offset + count($items)),
            'hasNextPage' => $totalCount > $offset + count($items),
        ];
    }
}