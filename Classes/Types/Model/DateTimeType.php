<?php
declare(strict_types=1);

namespace Itx\Typo3GraphQL\Types\Model;

use DateTime;
use DateTimeInterface;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class DateTimeType extends ScalarType implements TypeNameInterface
{
    /**
     * @var string
     */
    public $description = 'The `DateTime` scalar type represents time data, represented as an ISO-8601 encoded UTC date string.';

    public function __construct(array $config = [])
    {
        $this->name = self::getTypeName();
        parent::__construct($config);
    }

    /**
     * @param mixed $value
     */
    public function serialize($value): string
    {
        if (!$value instanceof DateTime) {
            throw new InvariantViolation('DateTime is not an instance of DateTime: ' . Utils::printSafe($value));
        }

        return $value->format(DateTimeInterface::ATOM);
    }

    /**
     * @param mixed $value
     */
    public function parseValue($value): ?DateTime
    {
        return DateTime::createFromFormat(DateTimeInterface::ATOM, $value) ?: null;
    }

    /**
     *
     * @param Node       $valueNode
     * @param array|null $variables
     *
     * @return mixed
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        if ($valueNode instanceof StringValueNode) {
            return $valueNode->value;
        }

        return null;
    }

    public static function getTypeName(): string
    {
        return 'DateTime';
    }
}
