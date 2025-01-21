<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class DateType extends ScalarType
{
    public function serialize($value)
    {
        if (!filter_var($value, FILTER_DEFAULT)) {
            throw new InvariantViolation("Could not serialize following value as date: " . Utils::printSafe($value));
        }

        return $this->parseValue($value);
    }

    public function parseValue($value)
    {
        if (!filter_var($value, FILTER_DEFAULT)) {
            throw new Error("Cannot represent following value as date: " . Utils::printSafeJson($value));
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, [$valueNode]);
        }

        if (!filter_var($valueNode->value, FILTER_DEFAULT)) {
            throw new Error("Not a valid date", [$valueNode]);
        }

        return $valueNode->value;
    }
}
