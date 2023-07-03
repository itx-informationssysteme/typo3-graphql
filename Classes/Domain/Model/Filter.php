<?php

namespace Itx\Typo3GraphQL\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Filter extends AbstractEntity
{
    protected string $name = '';
    protected string $model = '';
    protected string $filterPath = '';

    protected string $unit;
    protected string $typeOfFilter;

    /** @var ObjectStorage */
    protected ObjectStorage $categories;

    public function __construct()
    {
        $this->categories = new ObjectStorage();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @param string $model
     */
    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    /**
     * @return string
     */
    public function getFilterPath(): string
    {
        return $this->filterPath;
    }

    /**
     * @param string $filterPath
     */
    public function setFilterPath(string $filterPath): void
    {
        $this->filterPath = $filterPath;
    }

    /**
     * @return string
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * @param string $unit
     */
    public function setUnit(string $unit): void
    {
        $this->unit = $unit;
    }

    /**
     * @return string
     */
    public function getTypeOfFilter(): string
    {
        return $this->typeOfFilter;
    }

    /**
     * @param string $typeOfFilter
     */
    public function setTypeOfFilter(string $typeOfFilter): void
    {
        $this->typeOfFilter = $typeOfFilter;
    }
}
