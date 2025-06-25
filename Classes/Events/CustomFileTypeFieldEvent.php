<?php

namespace Itx\Typo3GraphQL\Events;

use SimPod\GraphQLUtils\Builder\FieldBuilder;

class CustomFileTypeFieldEvent
{
    /**
     * @param array<FieldBuilder> $fieldBuilders
     */
    protected array $fieldBuilders = [];

    public function __construct() { }

    public function setFieldBuilders(array $fieldBuilders): void
    {
        $this->fieldBuilders = $fieldBuilders;
    }

    public function addFieldBuilder(FieldBuilder $fieldBuilder): void
    {
        $this->fieldBuilders[] = $fieldBuilder;
    }

    public function getFieldBuilders(): array
    {
        return $this->fieldBuilders;
    }
}
