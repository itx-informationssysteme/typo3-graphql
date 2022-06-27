<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Types\Model\FileType;
use Itx\Typo3GraphQL\Types\Model\LinkType;

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
    }

    /**
     * Gets an instance of LinkType
     */
    public static function link(): LinkType
    {
        if (!isset(self::$customTypes['Link'])) {
            self::$customTypes['Link'] = new LinkType();
        }

        return self::$customTypes['Link'];
    }

    /**
     * Gets an instance of FileType
     */
    public static function file(): FileType
    {
        if (!isset(self::$customTypes['File'])) {
            self::$customTypes['File'] = new FileType();
        }

        return self::$customTypes['File'];
    }


    /**
     * @throws NameNotFoundException
     */
    public function addObjectType(Type $type, string $tableName, string $modelClassPath): void {
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

    public function typeWithNameExists(string $name): bool {
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
