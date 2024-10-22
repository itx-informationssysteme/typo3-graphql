<?php

namespace Itx\Typo3GraphQL\Events;

use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Types\TypeRegistry;

class CustomModelFieldEvent
{
    protected string $modelClassPath;
    protected string $tableName;
    protected TypeRegistry $typeRegistry;

    /** @var FieldBuilder[]  */
    protected array $fieldBuilders = [];

    public function __construct(string $modelClassPath, string $tableName, TypeRegistry $typeRegistry)
    {
        $this->modelClassPath = $modelClassPath;
        $this->tableName = $tableName;
        $this->typeRegistry = $typeRegistry;
    }

    public function getModelClassPath(): string
    {
        return $this->modelClassPath;
    }

    public function getTableName(): string
    {
        return $this->tableName;
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
