<?php

namespace Itx\Typo3GraphQL\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Filter extends AbstractEntity
{
    protected string $name = '';
    protected string $model = '';
    protected string $filterPath = '';

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
}
