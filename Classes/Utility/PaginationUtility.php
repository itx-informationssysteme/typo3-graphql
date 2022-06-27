<?php

namespace Itx\Typo3GraphQL\Utility;

use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Types\Model\ConnectionType;
use Itx\Typo3GraphQL\Types\Model\EdgeType;
use Itx\Typo3GraphQL\Types\Model\PageInfoType;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use TYPO3\CMS\Core\Utility\MathUtility;

class PaginationUtility
{
    public static function toCursor(mixed $value): string
    {
        return base64_encode($value);
    }

    /**
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
    public static function generateConnectionTypes(Type $objectType, TypeRegistry $typeRegistry): ConnectionType
    {
        $edgeType = new EdgeType($objectType);
        $connectionType = new ConnectionType($objectType, $edgeType, $typeRegistry->getType(PageInfoType::$typeName));

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

    public static function addPaginationArgumentsToFieldBuilder(FieldBuilder $fieldBuilder): FieldBuilder
    {
        $fieldBuilder->addArgument('first', Type::int(), 'Limit object count', 10)
                     ->addArgument('after', Type::string(), 'Cursor for pagination');

        return $fieldBuilder;
    }
}
