<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

class Range
{
    public ?int $min;
    public ?int $max;

    public int $resultCount;

    public function __construct(?int $min, ?int $max, int $resultCount = 0)
    {
        $this->min = $min;
        $this->max = $max;
        $this->resultCount = $resultCount;
    }
}
