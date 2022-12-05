<?php

namespace Itx\Typo3GraphQL\Schema;

use Itx\Typo3GraphQL\Types\TypeRegistry;

class Context
{
    protected string $modelClassPath;

    protected string $tableName;

    protected string $fieldName;

    protected array $columnConfiguration;

    protected TypeRegistry $typeRegistry;

    protected array $fieldAnnotations;

    public function __construct(string $modelClassPath, string $tableName, string $fieldName, array $columnConfiguration, TypeRegistry $typeRegistry, array $fieldAnnotations)
    {
        $this->modelClassPath = $modelClassPath;
        $this->tableName = $tableName;
        $this->fieldName = $fieldName;
        $this->columnConfiguration = $columnConfiguration;
        $this->typeRegistry = $typeRegistry;
        $this->fieldAnnotations = $fieldAnnotations;
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

    /**
     * @return array
     */
    public function getFieldAnnotations(): array
    {
        return $this->fieldAnnotations;
    }

    /**
     * @param array $fieldAnnotations
     */
    public function setFieldAnnotations(array $fieldAnnotations): void
    {
        $this->fieldAnnotations = $fieldAnnotations;
    }
}
