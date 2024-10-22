<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

class DiscreteFilterOption
{
    public int $resultCount = 0;
    public bool $selected = false;
    public string $value = '';
    public bool $disabled = false;

    public function __construct(string $value, int $resultCount, bool $isSelected, bool $isDisabled)
    {
        $this->value = $value;
        $this->resultCount = $resultCount;
        $this->selected = $isSelected;
        $this->disabled = $isDisabled;
    }
}
