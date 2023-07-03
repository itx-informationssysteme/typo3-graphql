<?php

namespace Itx\Typo3GraphQL\Events;

use Itx\Typo3GraphQL\Enum\FilterEventSource;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ModifyQueryBuilderForFilteringEvent
{
    protected string $modelClassPath;
    protected string $tableName;
    protected QueryBuilder $queryBuilder;

    public function __construct(string $modelClassPath, string $tableName, QueryBuilder $queryBuilder, protected array $args, protected FilterEventSource $filterEventSource, protected ?string $filterType = null)
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

    /**
     * @return FilterEventSource
     */
    public function getFilterEventSource(): FilterEventSource
    {
        return $this->filterEventSource;
    }

    /**
     * @param FilterEventSource $filterEventSource
     */
    public function setFilterEventSource(FilterEventSource $filterEventSource): void
    {
        $this->filterEventSource = $filterEventSource;
    }

    /**
     * @return string|null
     */
    public function getFilterType(): ?string
    {
        return $this->filterType;
    }

    /**
     * @param string|null $filterType
     */
    public function setFilterType(?string $filterType): void
    {
        $this->filterType = $filterType;
    }
}
