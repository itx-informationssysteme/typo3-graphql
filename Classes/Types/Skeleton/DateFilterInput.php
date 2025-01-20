<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

use Itx\Typo3GraphQL\Types\Model\RangeInputType;

class DateFilterInput
{
    public string $path = '';

    public DateRange $dateRange;

    public function __construct(string $path, DateRange $range)
    {
        $this->path = $path;
        $this->dateRange = $range;
    }
}
