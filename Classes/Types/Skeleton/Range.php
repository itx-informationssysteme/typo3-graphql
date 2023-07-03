<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

class Range
{
    public ?int $min;
    public ?int $max;
    public function __construct(?int $min, ?int $max){
        $this->min = $min;
        $this->max = $max;
    }
}
