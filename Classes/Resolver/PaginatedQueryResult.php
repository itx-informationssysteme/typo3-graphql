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

    public function __construct(array       $items,
                                int         $totalCount,
                                int         $offset,
                                ResolveInfo $resolveInfo,
                                string      $modelClassPath,
                                ?DataMapper $dataMapper = null)
    {
        $previousCursor = $offset;

        if ($dataMapper) {
            $itemsAsModel = $dataMapper->map($modelClassPath, $items);
            $this->items = $itemsAsModel;
        } else {
            $this->items = $items;
        }

        if ($resolveInfo->fieldName === 'edges') {
            foreach ($items as $counter => $item) {
                $cursor = $previousCursor + $counter + 1;

                $this->edges[] = [
                    'node' => $item,
                    'cursor' => PaginationUtility::toCursor($cursor),
                ];
            }
        }

        $this->totalCount = $totalCount;

        $this->pageInfo = [
            'endCursor' => PaginationUtility::toCursor($offset + count($items)),
            'hasNextPage' => $totalCount > $offset + count($items),
        ];
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
