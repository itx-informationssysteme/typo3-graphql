<?php

namespace Itx\Typo3GraphQL\Utility;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
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
    public static function generateConnectionTypes(
        Type $objectType,
        TypeRegistry $typeRegistry
    ): ConnectionType {
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

    public static function getFieldSelection(ResolveInfo $resolveInfo, string $tableName, array $additionalFields = []): array
    {
        $info = $resolveInfo->getFieldSelection(2);
        if (empty($info)) {
            return [];
        }

        $fields = ['uid', 'pid', ...$additionalFields];

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
            if (!TcaUtility::doesFieldExist($tableName, $dbFieldName)) {
                continue;
            }

            $dbFields[] = $tableName . '.' . $dbFieldName;
        }

        return $dbFields;
    }
}
