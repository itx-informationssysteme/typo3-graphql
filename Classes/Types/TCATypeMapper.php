<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Itx\Typo3GraphQL\Schema\TableNameResolver;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use JetBrains\PhpStorm\ArrayShape;
use phpDocumentor\Reflection\Types\Array_;
use SimPod\GraphQLUtils\Builder\EnumBuilder;
use SimPod\GraphQLUtils\Exception\InvalidArgument;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\MathUtility;

class TCATypeMapper
{
    protected LanguageService $languageService;
    protected TableNameResolver $tableNameResolver;

    public function __construct(LanguageService $languageService, TableNameResolver $tableNameResolver)
    {
        $this->languageService = $languageService;
        $this->tableNameResolver = $tableNameResolver;
    }

    /**
     * @param array        $columnConfiguration
     * @param TypeRegistry $typeRegistry
     *
     * @return Type
     * @throws UnsupportedTypeException
     * @throws \Itx\Typo3GraphQL\Exception\NameNotFoundException
     * @throws \SimPod\GraphQLUtils\Exception\InvalidArgument
     * @throws NotFoundException
     */
    public function map(#[ArrayShape([
        'label' => 'string',
        'config' => [
            'type' => 'string',
            'eval' => 'string',
            'format' => 'string',
            'items' => ['string' => 'string'],
            'foreign_table' => 'string',
            'MM' => 'string'
        ]
    ])] array                        $columnConfiguration,
                        TypeRegistry $typeRegistry): Type
    {
        /** @var Type|null $returnType */
        $returnType = null;

        switch ($columnConfiguration['config']['type']) {
            case 'check':
                $returnType = Type::boolean();
                break;
            case 'inline':
                // TODO
                break;
            case 'text':
            case 'input':
                if (str_contains($columnConfiguration['config']['eval'] ?? '', 'int')) {
                    $returnType = Type::int();
                    break;
                }

                if (str_contains($columnConfiguration['config']['eval'] ?? '', 'double2')) {
                    $returnType = Type::float();
                    break;
                }

                $returnType = Type::string();
                break;
            case 'number':
                if ($columnConfiguration['config']['format'] ?? '' === 'decimal') {
                    $returnType = Type::float();
                    break;
                }

                $returnType = Type::int();
                break;
            case 'language':
                $returnType = Type::int();
                break;
            case 'select':
                if (!empty($columnConfiguration['config']['items'])) {
                    // If all values are integers or floats, we don't need an enum
                    if (count(array_filter($columnConfiguration['config']['items'],
                            static fn($x) => !MathUtility::canBeInterpretedAsInteger($x[1]))) === 0) {
                        $returnType = Type::int();
                        break;
                    }

                    if (count(array_filter($columnConfiguration['config']['items'],
                            static fn($x) => !MathUtility::canBeInterpretedAsFloat($x[1]))) === 0) {
                        $returnType = Type::float();
                        break;
                    }

                    $name = NamingUtility::generateName($this->languageService->sL($columnConfiguration['label']), false);

                    if ($name === '') {
                        throw new UnsupportedTypeException("Could not find a name for enum");
                    }

                    $builder = EnumBuilder::create($name);

                    foreach ($columnConfiguration['config']['items'] as [$label, $item]) {
                        if ($item === '') {
                            $returnType = Type::string();
                            break 2;
                        }

                        try {
                            $builder->addValue($item, null, $this->languageService->sL($label));
                        }
                        catch (InvalidArgument $e) {
                            $returnType = Type::string();
                            break 2;
                        }
                    }

                    $enumType = new EnumType($builder->build());

                    $typeRegistry->addType($enumType);

                    $returnType = $enumType;
                    break;
                }

                if (!empty($columnConfiguration['config']['foreign_table'])) {
                    try {
                        $returnType = $typeRegistry->getTypeByTableName($columnConfiguration['config']['foreign_table']);
                    }
                    catch (NotFoundException $e) {
                        throw new NotFoundException("Could not find type for foreign table '{$columnConfiguration['config']['foreign_table']}'");
                    }

                    if (!empty($columnConfiguration['config']['MM'])) {
                        $returnType = Type::listOf($returnType);
                        break;
                    }
                }
        }

        if ($returnType === null) {
            throw new UnsupportedTypeException('Unsupported type: ' . $columnConfiguration['config']['type'], 1654960583);
        }

        if (!empty($columnConfiguration['config']['eval']) && str_contains($columnConfiguration['config']['eval'], 'required')) {
            $returnType = Type::nonNull($returnType);
        }

        return $returnType;
    }
}
