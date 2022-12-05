<?php

namespace Itx\Typo3GraphQL\Utility;

use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Exception\BadInputException;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Resolver\FilterResolver;
use Itx\Typo3GraphQL\Types\Model\ConnectionType;
use Itx\Typo3GraphQL\Types\Model\EdgeType;
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
    public static function generateConnectionTypes(Type $objectType, TypeRegistry $typeRegistry, FilterResolver $filterResolver, string $tableName): ConnectionType
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
        $fieldBuilder->addArgument(QueryArgumentsUtility::$paginationFirst, Type::int(), 'Limit object count', 10)->addArgument(QueryArgumentsUtility::$paginationAfter, Type::string(), 'Cursor for pagination')->addArgument(QueryArgumentsUtility::$sortByField, Type::string(), 'Sort by field')->addArgument(QueryArgumentsUtility::$sortingOrder, TypeRegistry::sortingOrder(), 'Sorting order', 'ASC')->addArgument(QueryArgumentsUtility::$filters, TypeRegistry::filterCollectionInput(), 'Apply predefined filters to this query.', []);

        return $fieldBuilder;
    }
}
