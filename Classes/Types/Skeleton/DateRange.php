<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

use Doctrine\DBAL\Types\DateType;

class DateRange
{
    public ?DateType $min;
    public ?DateType $max;
    public function __construct(?DateType $min, ?DateType $max){
        $this->min = $min;
        $this->max = $max;
    }
}
