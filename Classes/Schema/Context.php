<?php

namespace Itx\Typo3GraphQL\Schema;

use Itx\Typo3GraphQL\Types\TypeRegistry;
use JetBrains\PhpStorm\ArrayShape;

class Context
{
    protected string $modelClassPath;

    protected string $tableName;

    protected string $fieldName;

    protected array $columnConfiguration;

    protected TypeRegistry $typeRegistry;

    public function __construct(string $modelClassPath, string $tableName, string $fieldName, array $columnConfiguration, TypeRegistry $typeRegistry)
    {
        $this->modelClassPath = $modelClassPath;
        $this->tableName = $tableName;
        $this->fieldName = $fieldName;
        $this->columnConfiguration = $columnConfiguration;
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @return string
     */
    public function getModelClassPath(): string
    {
        return $this->modelClassPath;
    }

    /**
     * @param string $modelClassPath
     */
    public function setModelClassPath(string $modelClassPath): void
    {
        $this->modelClassPath = $modelClassPath;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @param string $tableName
     */
    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     */
    public function setFieldName(string $fieldName): void
    {
        $this->fieldName = $fieldName;
    }

    /**
     * @return array
     */
    public function getColumnConfiguration(): array
    {
        return $this->columnConfiguration;
    }

    /**
     * @param array $columnConfiguration
     */
    public function setColumnConfiguration(#[ArrayShape([
        'label' => 'string',
        'config' => [
            'type' => 'string',
            'eval' => 'string',
            'format' => 'string',
            'items' => ['string' => 'string'],
            'foreign_table' => 'string',
            'MM' => 'string',
            'renderType' => 'string',
        ]
    ])] array $columnConfiguration): void
    {
        $this->columnConfiguration = $columnConfiguration;
    }

    /**
     * @return TypeRegistry
     */
    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry;
    }

    /**
     * @param TypeRegistry $typeRegistry
     */
    public function setTypeRegistry(TypeRegistry $typeRegistry): void
    {
        $this->typeRegistry = $typeRegistry;
    }
}
