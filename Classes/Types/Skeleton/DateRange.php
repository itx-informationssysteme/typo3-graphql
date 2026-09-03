<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

class DateRange
{
    public ?\DateTime $min;
    public ?\DateTime $max;
    public int $resultCount;

    public function __construct(?\DateTime $min, ?\DateTime $max, int $resultCount = 0)
    {
        $this->min = $min;
        $this->max = $max;
        $this->resultCount = $resultCount;
    }

    public static function fromString(?string $min, ?string $max)
    {
        $date = new DateRange(null, null);
        if ($min && \DateTime::createFromFormat(\DateTime::ATOM, $min)) {
            $date->min = \DateTime::createFromFormat(\DateTime::ATOM, $min);
        } else {
            $date->min = null;
        }
        if ($max && \DateTime::createFromFormat(\DateTime::ATOM, $max)) {
            $date->max = \DateTime::createFromFormat(\DateTime::ATOM, $max);
        } else {
            $date->max = null;
        }

        return $date;
    }
}
