<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Types\Model\DateTimeType;
use Itx\Typo3GraphQL\Types\Model\DiscreteFilterInputType;
use Itx\Typo3GraphQL\Types\Model\FacetType;
use Itx\Typo3GraphQL\Types\Model\FileType;
use Itx\Typo3GraphQL\Types\Model\FilterCollectionInputType;
use Itx\Typo3GraphQL\Types\Model\FilterOptionType;
use Itx\Typo3GraphQL\Types\Model\LinkType;
use Itx\Typo3GraphQL\Types\Model\SortingOrderType;
use Itx\Typo3GraphQL\Types\Model\TypeNameInterface;
use Itx\Typo3GraphQL\Types\Skeleton\PageInfoType;

class TypeRegistry
{
    protected static array $customTypes = [];

    protected array $typeStore = [];
    protected array $tableToObjectNameMap = [];
    protected array $tableToModelClasspathMap = [];

    /**
     * @throws NameNotFoundException
     */
    public function __construct() {
        $this->addType(self::link());
        $this->addType(self::file());
        $this->addType(self::pageInfo());
        $this->addType(self::sortingOrder());

        $this->addType(self::filterOption());
        $this->addType(self::discreteFilterInput());
        $this->addType(self::filterCollectionInput());
        $this->addType(self::facet());
        $this->addType(self::dateTime());
    }

    /**
     * @throws NameNotFoundException
     */
    protected static function getOrCreateCustomType(string $type): Type
    {
        // Check if $type implements the TypeNameInterface
        if (!in_array(TypeNameInterface::class, class_implements($type), true)) {
            throw new NameNotFoundException(sprintf('Type %s does not implement the TypeNameInterface', $type));
        }

        /** @var TypeNameInterface $type */
        $typeName = $type::getTypeName();

        if (!isset(self::$customTypes[$typeName])) {
            self::$customTypes[$typeName] = new $type();
        }

        return self::$customTypes[$typeName];
    }

    /**
     * Gets an instance of LinkType
     *
     * @throws NameNotFoundException
     */
    public static function link(): LinkType
    {
        /** @var LinkType $type */
        $type = self::getOrCreateCustomType(LinkType::class);

        return $type;
    }

    /**
     * Gets an instance of FileType
     *
     * @throws NameNotFoundException
     */
    public static function file(): FileType
    {
        /** @var FileType $type */
        $type = self::getOrCreateCustomType(FileType::class);

        return $type;
    }

    /**
     * Gets an instance of PageInfoType
     *
     * @throws NameNotFoundException
     */
    public static function pageInfo(): PageInfoType
    {
        /** @var PageInfoType $type */
        $type = self::getOrCreateCustomType(PageInfoType::class);

        return $type;
    }

    /**
     * Gets an instance of SortingOrderType
     *
     * @throws NameNotFoundException
     */
    public static function sortingOrder(): SortingOrderType
    {
        /** @var SortingOrderType $type */
        $type = self::getOrCreateCustomType(SortingOrderType::class);

        return $type;
    }

    /**
     * Gets an instance of FilterOptionType
     *
     * @throws NameNotFoundException
     */
    public static function filterOption(): FilterOptionType
    {
        /** @var FilterOptionType $type */
        $type = self::getOrCreateCustomType(FilterOptionType::class);

        return $type;
    }

    /**
     * Gets an instance of FilterCollectionInputType
     *
     * @throws NameNotFoundException
     */
    public static function filterCollectionInput(): FilterCollectionInputType
    {
        /** @var FilterCollectionInputType $type */
        $type = self::getOrCreateCustomType(FilterCollectionInputType::class);

        return $type;
    }

    /**
     * Gets an instance of DiscreteFilterInputType
     *
     * @throws NameNotFoundException
     */
    public static function discreteFilterInput(): DiscreteFilterInputType
    {
        /** @var DiscreteFilterInputType $type */
        $type = self::getOrCreateCustomType(DiscreteFilterInputType::class);

        return $type;
    }

    /**
     * Gets an instance of FacetType
     *
     * @throws NameNotFoundException
     */
    public static function facet(): FacetType
    {
        /** @var FacetType $type */
        $type = self::getOrCreateCustomType(FacetType::class);

        return $type;
    }

    /**
     * Gets an instance of DateTime
     *
     * @throws NameNotFoundException
     */
    public static function dateTime(): DateTimeType
    {
        /** @var DateTimeType $type */
        $type = self::getOrCreateCustomType(DateTimeType::class);

        return $type;
    }

    /**
     * @throws NameNotFoundException
     */
    public function addModelObjectType(Type $type, string $tableName, string $modelClassPath): void {
        $name = $type->toString();

        if ($name === '') {
            throw new NameNotFoundException("Object type $type is missing a name");
        }

        $this->typeStore[$name] = $type;
        $this->tableToObjectNameMap[$tableName] = $name;
        $this->tableToModelClasspathMap[$tableName] = $modelClassPath;
    }

    /**
     * @throws NameNotFoundException
     */
    public function addType(Type $type): void {
        $name = $type->toString();

        if ($name === '') {
            throw new NameNotFoundException("Type $type is missing a name");
        }

        $this->typeStore[$name] = $type;
    }

    /**
     * @param string $name is the Types name
     *
     * @throws NotFoundException
     */
    public function getType(string $name): Type {
        if (empty($this->typeStore[$name])) {
            throw new NotFoundException("There is no type with name $name registered.");
        }

        return $this->typeStore[$name];
    }

    public function hasType(string $name): bool {
        return isset($this->typeStore[$name]);
    }

    /**
     * @param string $name is usually the table name
     *
     * @throws NotFoundException
     */
    public function getTypeByTableName(string $name): Type {
        if (empty($this->tableToObjectNameMap[$name])) {
            throw new NotFoundException("There is no type with name $name registered.");
        }

        $typeName = $this->tableToObjectNameMap[$name];

        return $this->typeStore[$typeName];
    }

    /**
     * @throws NotFoundException
     */
    public function getModelClassPathByTableName(string $tableName) {
        if (empty($this->tableToModelClasspathMap[$tableName])) {
            throw new NotFoundException("There is no model class path for table $tableName registered.");
        }

        return $this->tableToModelClasspathMap[$tableName];
    }
}
