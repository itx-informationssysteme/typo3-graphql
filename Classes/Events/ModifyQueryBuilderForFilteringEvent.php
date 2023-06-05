<?php

namespace Itx\Typo3GraphQL\Events;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ModifyQueryBuilderForFilteringEvent
{
    protected string $modelClassPath;
    protected string $tableName;
    protected QueryBuilder $queryBuilder;

    public function __construct(string $modelClassPath, string $tableName, QueryBuilder $queryBuilder, protected array $args)
    {
        $this->modelClassPath = $modelClassPath;
        $this->tableName = $tableName;
        $this->queryBuilder = $queryBuilder;
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
     * Can be used to modify the query builder
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
