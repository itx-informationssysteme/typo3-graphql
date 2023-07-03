<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

use Itx\Typo3GraphQL\Types\Model\RangeInputType;

class RangeFilterInput
{
    public string $path = '';

    public Range $range;

    public function __construct(string $path, Range $range)
    {
        $this->path = $path;
        $this->range = $range;
    }
}
