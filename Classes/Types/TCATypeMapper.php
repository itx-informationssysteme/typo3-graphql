<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Itx\Typo3GraphQL\Resolver\QueryResolver;
use Itx\Typo3GraphQL\Schema\TableNameResolver;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use JetBrains\PhpStorm\ArrayShape;
use phpDocumentor\Reflection\Types\Array_;
use SimPod\GraphQLUtils\Builder\EnumBuilder;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Exception\InvalidArgument;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class TCATypeMapper
{
    protected LanguageService $languageService;
    protected TableNameResolver $tableNameResolver;
    protected QueryResolver $queryResolver;

    protected static array $translationFields = [
        'l10n_parent',
        'l18n_parent'
    ];

    public function __construct(LanguageService $languageService, TableNameResolver $tableNameResolver, QueryResolver $queryResolver)
    {
        $this->languageService = $languageService;
        $this->tableNameResolver = $tableNameResolver;
        $this->queryResolver = $queryResolver;
    }

    /**
     * @param string       $fieldName
     * @param array        $columnConfiguration
     * @param string       $modelClassPath
     * @param string       $tableName
     * @param TypeRegistry $typeRegistry
     *
     * @return FieldBuilder
     * @throws NameNotFoundException
     * @throws NotFoundException
     * @throws UnsupportedTypeException
     */
    public function buildField(string $fieldName, #[ArrayShape([
        'label' => 'string',
        'config' => [
            'type' => 'string',
            'eval' => 'string',
            'format' => 'string',
            'items' => ['string' => 'string'],
            'foreign_table' => 'string',
            'MM' => 'string',
            'renderType' => 'string',
        ]
    ])] array                         $columnConfiguration, string $modelClassPath, string $tableName, TypeRegistry $typeRegistry): FieldBuilder
    {
        /** @var Type|null $fieldType */
        $fieldType = null;

        switch ($columnConfiguration['config']['type']) {
            case 'check':
                $fieldType = Type::boolean();
                break;
            case 'inline':
                if ($columnConfiguration['config']['foreign_table'] ?? '' === 'sys_file_reference') {
                    $fieldType = TypeRegistry::file();
                    break;
                }

                break;
            case 'text':
            case 'input':
                if (str_contains($columnConfiguration['config']['eval'] ?? '', 'int')) {
                    $fieldType = Type::int();
                    break;
                }

                if (str_contains($columnConfiguration['config']['eval'] ?? '', 'double2')) {
                    $fieldType = Type::float();
                    break;
                }

                if ($columnConfiguration['config']['renderType'] ?? '' === 'inputLink') {
                    $fieldType = TypeRegistry::link();
                    break;
                }

                $fieldType = Type::string();
                break;
            case 'number':
                if ($columnConfiguration['config']['format'] ?? '' === 'decimal') {
                    $fieldType = Type::float();
                    break;
                }

                $fieldType = Type::int();
                break;
            case 'language':
                $fieldType = Type::int();
                break;
            case 'select':
                if (!empty($columnConfiguration['config']['items'])) {
                    // If all values are integers or floats, we don't need an enum
                    if (count(array_filter($columnConfiguration['config']['items'], static fn($x) => !MathUtility::canBeInterpretedAsInteger($x[1]))) === 0) {
                        $fieldType = Type::int();
                        break;
                    }

                    if (count(array_filter($columnConfiguration['config']['items'], static fn($x) => !MathUtility::canBeInterpretedAsFloat($x[1]))) === 0) {
                        $fieldType = Type::float();
                        break;
                    }

                    $name = NamingUtility::generateName($this->languageService->sL($columnConfiguration['label']), false);

                    if ($name === '') {
                        throw new UnsupportedTypeException("Could not find a name for enum");
                    }

                    $builder = EnumBuilder::create($name);

                    foreach ($columnConfiguration['config']['items'] as [$label, $item]) {
                        if ($item === '') {
                            $fieldType = Type::string();
                            break 2;
                        }

                        try {
                            $builder->addValue($item, null, $this->languageService->sL($label));
                        }
                        catch (InvalidArgument $e) {
                            $fieldType = Type::string();
                            break 2;
                        }
                    }

                    $enumType = new EnumType($builder->build());

                    $typeRegistry->addType($enumType);

                    $fieldType = $enumType;
                    break;
                }

                if (!empty($columnConfiguration['config']['foreign_table'])) {
                    try {
                        $fieldType = $typeRegistry->getTypeByTableName($columnConfiguration['config']['foreign_table']);
                    }
                    catch (NotFoundException $e) {
                        throw new NotFoundException("Could not find type for foreign table '{$columnConfiguration['config']['foreign_table']}'");
                    }

                    if (!empty($columnConfiguration['config']['MM'])) {
                        $fieldType = Type::listOf($fieldType);
                        break;
                    }
                }
        }

        if (in_array($fieldName, self::$translationFields, true)) {
            $fieldType = Type::int();
        }

        if ($fieldType === null) {
            throw new UnsupportedTypeException('Unsupported type: ' . $columnConfiguration['config']['type'], 1654960583);
        }

        if (!empty($columnConfiguration['config']['eval']) && str_contains($columnConfiguration['config']['eval'], 'required')) {
            $fieldType = Type::nonNull($fieldType);
        }

        $fieldBuilder = FieldBuilder::create($fieldName, $fieldType);

        if (!in_array($fieldName, self::$translationFields, true)) {
            $this->addSpecificFieldResolver($fieldBuilder, $fieldName, $modelClassPath, $tableName, $columnConfiguration, $typeRegistry);
        }

        return $fieldBuilder;
    }

    protected function addSpecificFieldResolver(FieldBuilder $field, string $fieldName, string $modelClassPath, string $tableName, array $columnConfiguration, TypeRegistry $typeRegistry): void
    {
        if ($columnConfiguration['config']['foreign_table'] ?? '' === 'sys_file_reference') {

            return;
        }

        if (!empty($columnConfiguration['config']['foreign_table']) && empty($columnConfiguration['config']['MM'])) {
            $field->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use (
                $tableName, $typeRegistry
            ) {
                return $this->queryResolver->fetchForeignRecord($root, $args, $context, $resolveInfo, $typeRegistry, $tableName);
            });
        } elseif (!empty($columnConfiguration['config']['MM'])) {
            $field->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use (
                $modelClassPath, $tableName, $typeRegistry
            ) {
                return $this->queryResolver->fetchForeignRecordWithMM($root, $args, $context, $resolveInfo, $typeRegistry, $tableName, $modelClassPath);
            });
        }
    }
}
