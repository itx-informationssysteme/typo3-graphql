<?php

namespace Itx\Typo3GraphQL\Events;

use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Types\TypeRegistry;

class CustomQueryFieldEvent
{
    protected string $modelClassPath;
    protected string $tableName;
    protected TypeRegistry $typeRegistry;

    /** @var FieldBuilder[]  */
    protected array $fieldBuilders = [];

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }
    
    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry;
    }

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
