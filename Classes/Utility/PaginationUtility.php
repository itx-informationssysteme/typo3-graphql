<?php

namespace Itx\Typo3GraphQL\Utility;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Resolver\FilterResolver;
use Itx\Typo3GraphQL\Types\Model\ConnectionType;
use Itx\Typo3GraphQL\Types\Model\EdgeType;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class PaginationUtility
{
    public static function toCursor(mixed $value): string
    {
        return base64_encode($value);
    }

    /**
     * @return int Returns 0 if the cursor is empty
     * @throws BadInputException
     */
    public static function offsetFromCursor(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        $decodedValue = (int)base64_decode($value);

        if (!MathUtility::canBeInterpretedAsInteger($decodedValue)) {
            throw new BadInputException('Cursor value must be an integer');
        }

        return $decodedValue;
    }

    /**
     * @throws NameNotFoundException
     * @throws NotFoundException
     */
    public static function generateConnectionTypes(Type           $objectType,
                                                   TypeRegistry   $typeRegistry,
                                                   FilterResolver $filterResolver,
                                                   string         $tableName): ConnectionType
    {
        $edgeType = new EdgeType($objectType);
        $connectionType = new ConnectionType($objectType, $edgeType);

        if (!$typeRegistry->hasType($edgeType->toString())) {
            $typeRegistry->addType($edgeType);
        }

        if (!$typeRegistry->hasType($connectionType->toString())) {
            $typeRegistry->addType($connectionType);
        } else {
            $connectionType = $typeRegistry->getType($connectionType->toString());
        }

        return $connectionType;
    }

    /**
     * @throws NameNotFoundException
     */
    public static function addArgumentsToFieldBuilder(FieldBuilder $fieldBuilder): FieldBuilder
    {
        $fieldBuilder->addArgument(QueryArgumentsUtility::$paginationFirst, Type::int(), 'Limit object count (page size)', 10)
                     ->addArgument(QueryArgumentsUtility::$paginationAfter, Type::string(), 'Cursor for pagination')
                     ->addArgument(QueryArgumentsUtility::$offset, Type::int(), 'Offset for pagination, overrides cursor')
                     ->addArgument(QueryArgumentsUtility::$sortByField, Type::string(), 'Sort by field')
                     ->addArgument(QueryArgumentsUtility::$sortingOrder, TypeRegistry::sortingOrder(), 'Sorting order', 'ASC')
                     ->addArgument(QueryArgumentsUtility::$filters,
                                   TypeRegistry::filterCollectionInput(),
                                   'Apply predefined filters to this query.',
                                   []);

        return $fieldBuilder;
    }

    public static function getFieldSelection(ResolveInfo $resolveInfo, string $tableName): array
    {
        $info = $resolveInfo->getFieldSelection(2);
        if (empty($info)) {
            return [];
        }

        $fields = ['uid', 'pid'];

        if ($info['edges']['node'] ?? false) {
            $fields = [...$fields, ...array_keys($info['edges']['node'])];
        }

        if ($info['items'] ?? false) {
            $fields = [...$fields, ...array_keys($info['items'])];
        }

        $fields = array_unique($fields);

        $dbFields = [];
        foreach ($fields as $field) {
            $dbFieldName = GeneralUtility::camelCaseToLowerCaseUnderscored($field);

            // Check if field exists in TCA
            if ((!isset($GLOBALS['TCA'][$tableName]['columns'][$dbFieldName]) && $dbFieldName !== 'uid' &&
                    $dbFieldName !== 'pid') || ($GLOBALS['TCA'][$tableName]['columns'][$dbFieldName]['config']['type'] ?? null) === 'none') {
                continue;
            }

            $dbFields[] = $tableName . '.' . $dbFieldName;
        }

        return $dbFields;
    }
}
