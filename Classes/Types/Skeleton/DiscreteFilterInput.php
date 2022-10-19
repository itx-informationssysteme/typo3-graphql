<?php

namespace Itx\Typo3GraphQL\Types\Skeleton;

class DiscreteFilterInput
{
    public string $path = '';

    /** @var string[] $options */
    public array $options = [];

    public function __construct(string $path, array $options)
    {
        $this->path = $path;
        $this->options = $options;
    }
}
