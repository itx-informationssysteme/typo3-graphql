<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

class RangeFilterInput
{
    public string $path = '';

    public ?Range $range = null;
    public ?RangeFloat $rangeFloat = null;
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function setRange(Range $range): void
    {
        $this->range = $range;
    }

    public function setRangeFloat(RangeFloat $rangeFloat): void
    {
        $this->rangeFloat = $rangeFloat;
    }
}
