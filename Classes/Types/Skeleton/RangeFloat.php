<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

class RangeFloat
{
    public ?float $min;
    public ?float $max;

    public function __construct(?float $min, ?float $max){
        $this->min = $min;
        $this->max = $max;
    }
}
