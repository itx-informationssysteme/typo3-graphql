<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

class Range
{
    protected string $min = '';
    protected string $max = '';
    public function __construct(string $min, string $max){
        $this->min = $min;
        $this->max = $max;
    }
}
