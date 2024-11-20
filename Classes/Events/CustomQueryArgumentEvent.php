<?php

namespace Itx\Typo3GraphQL\Events;

use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Enum\RootQueryType;
use Itx\Typo3GraphQL\Types\TypeRegistry;

class CustomQueryArgumentEvent
{
    public function __construct(
        protected RootQueryType $queryType,
        protected FieldBuilder $fieldBuilder,
        protected string $modelClassPath,
        protected string $tableName,
        protected TypeRegistry $typeRegistry
    ) {}

    /**
     * @return RootQueryType
     */
    public function getQueryType(): RootQueryType
    {
        return $this->queryType;
    }

    /**
     * @return FieldBuilder
     */
    public function getFieldBuilder(): FieldBuilder
    {
        return $this->fieldBuilder;
    }

    /**
     * @return string
     */
    public function getModelClassPath(): string
    {
        return $this->modelClassPath;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return TypeRegistry
     */
    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry;
    }
}
