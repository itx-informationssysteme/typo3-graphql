<?php

namespace Itx\Typo3GraphQL\Schema;

use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use JetBrains\PhpStorm\ArrayShape;

class TCATypeMapper
{
    /**
     * @param array $columnConfiguration
     *
     * @return Type
     * @throws UnsupportedTypeException
     */
    public static function map(#[ArrayShape([
        'config' => [
            'type' => 'string',
            'eval' => 'string',
            'format' => 'string'
        ]
    ])] array $columnConfiguration): Type
    {
        switch ($columnConfiguration['config']['type']) {
            case 'inline':
                // TODO
                break;
            case 'input':
                if (str_contains($columnConfiguration['config']['eval'] ?? '', 'int')) {
                    return Type::int();
                }

                if (str_contains($columnConfiguration['config']['eval'] ?? '', 'double2')) {
                    return Type::float();
                }

                return Type::string();
            case 'number':
                if ($columnConfiguration['config']['format'] ?? '' === 'decimal') {
                    return Type::float();
                }

                return Type::int();
            case 'language':
                return Type::int();
        }

        throw new UnsupportedTypeException('Unsupported type: ' . $columnConfiguration['config']['type'], 1654960583);
    }
}
