<?php

namespace Itx\Typo3GraphQL\Resolver;

use GraphQL\Type\Definition\ResolveInfo;
use Itx\Typo3GraphQL\Utility\PaginationUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class PaginatedQueryResult
{
    public array $edges = [];
    public int $totalCount;
    public array $pageInfo;
    public array $items = [];
    public array $facets = [];

    public function __construct(array $items, int $totalCount, int $offset, int $limit, ResolveInfo $resolveInfo, string $modelClassPath, DataMapper $dataMapper)
    {
        $previousCursor = $offset;

        if (!empty($resolveInfo->getFieldSelection()['edges'])) {
            foreach ($items as $counter => $item) {
                $cursor = $previousCursor + $counter + 1;

                // Apply DataMapper to each item if it's not a model yet (e.g. when using lazy loading)
                $itemAsModel = $item;

                if (!is_a($item, $modelClassPath)) {
                    $itemAsModel = $dataMapper->map($modelClassPath, $item);
                }

                $this->edges[] = [
                    'node' => $itemAsModel,
                    'cursor' => PaginationUtility::toCursor($cursor),
                ];
            }
        }

        $this->totalCount = $totalCount;

        $this->pageInfo = [
            'endCursor' => PaginationUtility::toCursor($offset + count($items)),
            'hasNextPage' => $totalCount > $offset + count($items),
        ];

        if (!empty($resolveInfo->getFieldSelection()['items'])) {
            $this->items = $items;
        }
    }

    public function getFacets(): array
    {
        return $this->facets;
    }

    public function setFacets(array $facets): void
    {
        $this->facets = $facets;
    }
}
