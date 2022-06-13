<?php

namespace Itx\Typo3GraphQL\Schema;

use TYPO3\CMS\Extbase\Persistence\ClassesConfiguration;

class TableNameResolver
{
    protected ClassesConfiguration $classesConfiguration;

    public function __construct(ClassesConfiguration $classesConfiguration)
    {
        $this->classesConfiguration = $classesConfiguration;
    }

    public function resolve(string $name): string
    {
        $classConfiguration = $this->classesConfiguration->getConfigurationFor($name);
        if (!$classConfiguration) {
            return $this->resolveTableNameByStringProcessing($name);
        }

        return $classConfiguration['tableName'];
    }

    /**
     *
     * Copy of DataMapFactory::resolveTableName, because it is protected
     * Resolve the table name for the given class name
     *
     * @param string $className
     *
     * @return string The table name
     */
    protected function resolveTableNameByStringProcessing(string $className): string
    {
        $className = ltrim($className, '\\');
        $classNameParts = explode('\\', $className);
        // Skip vendor and product name for core classes
        if (strpos($className, 'TYPO3\\CMS\\') === 0) {
            $classPartsToSkip = 2;
        } else {
            $classPartsToSkip = 1;
        }
        $tableName = 'tx_' . strtolower(implode('_', array_slice($classNameParts, $classPartsToSkip)));

        return $tableName;
    }
}
