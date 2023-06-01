<?php

namespace Itx\Typo3GraphQL\Events;

use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

class CustomQueryConstraintEvent
{
    /** @var ConstraintInterface[] */
    private array $constraints = [];

    public function __construct(protected string $modelClassPath, protected string $tableName, protected array $args, protected QueryInterface $query)
    {
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
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function getQuery(): QueryInterface
    {
        return $this->query;
    }

    public function addConstraint(ConstraintInterface $constraint): void
    {
        $this->constraints[] = $constraint;
    }
}
