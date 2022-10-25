<?php

namespace Itx\Typo3GraphQL\Resolver;

use ArrayAccess;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DefaultFieldResolver
{
    /**
     * If a resolve function is not given, then a default resolve behavior is used
     * which takes the property of the root value of the same name as the field
     * and returns it as the result, or if it's a function, returns the result
     * of calling that function while passing along args and context.
     *
     * @param mixed                $objectValue
     * @param array<string, mixed> $args
     * @param mixed|null           $contextValue
     * @param ResolveInfo          $info
     *
     * @return mixed|null
     */
    public static function defaultFieldResolver(mixed $objectValue, array $args, mixed $contextValue, ResolveInfo $info): mixed
    {
        $fieldName = $info->fieldName;
        $property  = null;

        if (is_array($objectValue) || $objectValue instanceof ArrayAccess) {
            if (isset($objectValue[$fieldName])) {
                $property = $objectValue[$fieldName];
            } else {
                $snakeCaseFieldName = GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);
                if (isset($objectValue[$snakeCaseFieldName])) {
                    $property = $objectValue[$snakeCaseFieldName];
                }
            }
        } elseif (is_object($objectValue)) {
            if (isset($objectValue->{$fieldName})) {
                $property = $objectValue->{$fieldName};
            }
        }

        return $property instanceof Closure
            ? $property($objectValue, $args, $contextValue, $info)
            : $property;
    }
}
