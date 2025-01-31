<?php
declare(strict_types=1);

namespace Itx\Typo3GraphQL\Types\Model;

use DateTimeImmutable;
use DateTimeInterface;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use function Safe\preg_match;

class DateTimeType extends ScalarType implements TypeNameInterface
{
    private const RFC_3339_REGEX = '~^(\d{4}-(0[1-9]|1[012])-(0[1-9]|[12][\d]|3[01])T([01][\d]|2[0-3]):([0-5][\d]):([0-5][\d]|60))(\.\d{1,})?(([Z])|([+|-]([01][\d]|2[0-3]):[0-5][\d]))$~';

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
     * @return string
     */
    public function serialize(mixed $value): string
    {
        if (! $value instanceof DateTimeInterface) {
            throw new InvariantViolation(
                'DateTime is not an instance of DateTimeImmutable nor DateTime: ' . Utils::printSafe($value)
            );
        }

        return $value->format(DateTimeInterface::ATOM);
    }

    /**
     * @param mixed $value
     * @return DateTimeImmutable
     * @throws \DateMalformedStringException
     */
    public function parseValue(mixed $value): DateTimeImmutable
    {
        if (! is_string($value)) {
            throw new \Exception('DateTime must be a string');
        }

        if (! $this->validateDatetime($value)) {
            throw new \Exception('Invalid date time format');
        }

        return new \DateTimeImmutable($value);
    }

    /**
     *
     * @param Node $valueNode
     * @param array|null $variables
     *
     * @return DateTimeImmutable|null
     * @throws \DateMalformedStringException
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): ?DateTimeImmutable
    {
        if (! $valueNode instanceof StringValueNode) {
            return null;
        }

        return $this->parseValue($valueNode->value);
    }

    private function validateDatetime(string $value): bool
    {
        if (preg_match(self::RFC_3339_REGEX, $value) !== 1) {
            return false;
        }

        $tPosition = strpos($value, 'T');
        assert($tPosition !== false);

        return $this->validateDate(substr($value, 0, $tPosition));
    }

    private function validateDate(string $date): bool
    {
        // Verify the correct number of days for the month contained in the date-string.
        [$year, $month, $day] = explode('-', $date);
        $year                 = (int) $year;
        $month                = (int) $month;
        $day                  = (int) $day;

        switch ($month) {
            case 2: // February
                $isLeapYear = $this->isLeapYear($year);
                if ($isLeapYear && $day > 29) {
                    return false;
                }

                return $isLeapYear || $day <= 28;

            case 4: // April
            case 6: // June
            case 9: // September
            case 11: // November
                if ($day > 30) {
                    return false;
                }

                break;
        }

        return true;
    }

    private function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    public static function getTypeName(): string
    {
        return 'DateTime';
    }
}
