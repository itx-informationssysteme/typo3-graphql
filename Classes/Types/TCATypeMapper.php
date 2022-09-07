<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Deferred;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Itx\Typo3GraphQL\Resolver\QueryResolver;
use Itx\Typo3GraphQL\Resolver\ResolverBuffer;
use Itx\Typo3GraphQL\Schema\Context;
use Itx\Typo3GraphQL\Schema\TableNameResolver;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use Itx\Typo3GraphQL\Utility\PaginationUtility;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use SimPod\GraphQLUtils\Builder\EnumBuilder;
use SimPod\GraphQLUtils\Exception\InvalidArgument;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\MathUtility;

class TCATypeMapper
{
    protected LanguageService $languageService;
    protected TableNameResolver $tableNameResolver;
    protected QueryResolver $queryResolver;
    protected ResolverBuffer $resolverBuffer;

    protected static array $translationFields = [
        'l10n_parent',
        'l18n_parent'
    ];

    public function __construct(LanguageService $languageService, TableNameResolver $tableNameResolver, QueryResolver $queryResolver, ResolverBuffer $resolverBuffer)
    {
        $this->languageService = $languageService;
        $this->tableNameResolver = $tableNameResolver;
        $this->queryResolver = $queryResolver;
        $this->resolverBuffer = $resolverBuffer;
    }

    /**
     * @param Context $context
     *
     * @return FieldBuilder
     * @throws NotFoundException
     * @throws UnsupportedTypeException
     * @throws NameNotFoundException
     */
    public function buildField(Context $context): FieldBuilder
    {
        $fieldBuilder = FieldBuilder::create($context->getFieldName());

        $columnConfiguration = $context->getColumnConfiguration();

        switch ($columnConfiguration['config']['type']) {
            case 'check':
                $fieldBuilder->setType(Type::boolean());
                break;
            case 'inline':
                $this->handleInlineType($context, $fieldBuilder);
                break;
            case 'text':
            case 'input':
                $this->handleInputType($context, $fieldBuilder);
                break;
            case 'number':
                $this->handleNumberType($context, $fieldBuilder);
                break;
            case 'language':
                $fieldBuilder->setType(Type::int());
                break;
            case 'select':
                $this->handleSelectType($context, $fieldBuilder);
                break;
            case 'category':
                $this->handleCategoryType($context, $fieldBuilder);
                break;
        }

        // If the field is a translation parent field, we don't want the relation but only the element id
        if (in_array($context->getFieldName(), self::$translationFields, true)) {
            $fieldBuilder->setType(Type::int());
        }

        if (!$fieldBuilder->hasType()) {
            throw new UnsupportedTypeException('Unsupported type: ' . $columnConfiguration['config']['type'], 1654960583);
        }

        // If the field has some kind of relation, the type is a list of the related type
        if (($columnConfiguration['config']['foreign_table'] ?? '') !== 'sys_file_reference' && (($columnConfiguration['config']['maxitems'] ?? 2) > 1) && ((!empty($columnConfiguration['config']['MM'])) || (!empty($columnConfiguration['config']['type'] === 'inline')))) {
            $paginationConnection = PaginationUtility::generateConnectionTypes($fieldBuilder->getType(), $context->getTypeRegistry());

            $fieldBuilder->setType(Type::nonNull($paginationConnection));

            PaginationUtility::addPaginationArgumentsToFieldBuilder($fieldBuilder);
        }

        // If the field is required, the type is a non-null type
        if (!empty($columnConfiguration['config']['eval']) && str_contains($columnConfiguration['config']['eval'], 'required')) {
            $type = $fieldBuilder->getType();
            $fieldBuilder->setType(Type::nonNull($type));
        }

        return $fieldBuilder;
    }

    protected function handleInputType(Context $context, FieldBuilder $fieldBuilder): void
    {
        if (str_contains($columnConfiguration['config']['eval'] ?? '', 'int')) {
            $fieldBuilder->setType(Type::int());

            return;
        }

        if (str_contains($columnConfiguration['config']['eval'] ?? '', 'double2')) {
            $fieldBuilder->setType(Type::float());

            return;
        }

        if ($columnConfiguration['config']['renderType'] ?? '' === 'inputLink') {
            $fieldBuilder->setType(TypeRegistry::link());

            return;
        }

        $fieldBuilder->setType(Type::string());
    }

    protected function handleNumberType(Context $context, FieldBuilder $fieldBuilder): void
    {
        if ($columnConfiguration['config']['format'] ?? '' === 'decimal') {
            $fieldBuilder->setType(Type::float());

            return;
        }

        $fieldBuilder->setType(Type::int());
    }

    protected function handleInlineType(Context $context, FieldBuilder $fieldBuilder): void
    {
        $columnConfiguration = $context->getColumnConfiguration();

        if (($columnConfiguration['config']['foreign_table'] ?? '') !== 'sys_file_reference') {
            return;
        }

        $fieldBuilder->setType(TypeRegistry::file());

        $schemaContext = $context;

        // Select the correct query resolver for the file reference field item amount
        if (($columnConfiguration['config']['maxitems'] ?? 2) > 1) {
            $fieldBuilder->setType(Type::listOf($fieldBuilder->getType()));
            $fieldBuilder->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use (
                $schemaContext
            ) {
                return $this->queryResolver->fetchFiles($root, $args, $context, $resolveInfo, $schemaContext);
            });
        } else {
            $fieldBuilder->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use (
                $schemaContext
            ) {
                return $this->queryResolver->fetchFile($root, $args, $context, $resolveInfo, $schemaContext);
            });
        }
    }

    /**
     * @throws NameNotFoundException
     * @throws UnsupportedTypeException
     */
    protected function handleSelectType(Context $context, FieldBuilder $fieldBuilder): void
    {
        $columnConfiguration = $context->getColumnConfiguration();

        if (!empty($columnConfiguration['config']['items'])) {
            // If all values are integers or floats, we don't need an enum
            if (count(array_filter($columnConfiguration['config']['items'], static fn($x) => !MathUtility::canBeInterpretedAsInteger($x[1]))) === 0) {
                $fieldBuilder->setType(Type::int());

                return;
            }

            if (count(array_filter($columnConfiguration['config']['items'], static fn($x) => !MathUtility::canBeInterpretedAsFloat($x[1]))) === 0) {
                $fieldBuilder->setType(Type::float());

                return;
            }

            $name = NamingUtility::generateName($this->languageService->sL($columnConfiguration['label']), false);

            if ($name === '') {
                throw new UnsupportedTypeException("Could not find a name for enum");
            }

            if ($context->getTypeRegistry()->hasType($name)) {
                $objectTypeName = NamingUtility::generateNameFromClassPath($context->getModelClassPath(), false);
                $name = $objectTypeName . $name;
            }

            $builder = EnumBuilder::create($name);

            foreach ($columnConfiguration['config']['items'] as [$label, $item]) {
                if ($item === '') {
                    $fieldBuilder->setType(Type::string());

                    return;
                }

                try {
                    $builder->addValue($item, null, $this->languageService->sL($label));
                }
                catch (InvalidArgument $e) {
                    $fieldBuilder->setType(Type::string());

                    return;
                }
            }

            $enumType = new EnumType($builder->build());

            $context->getTypeRegistry()->addType($enumType);

            $fieldBuilder->setType($enumType);

            return;
        }

        $foreignTable = $columnConfiguration['config']['foreign_table'] ?? '';

        if ($foreignTable !== '') {
            try {
                $type = $context->getTypeRegistry()->getTypeByTableName($foreignTable);
                $fieldBuilder->setType($type);
            }
            catch (NotFoundException $e) {
                // TODO make configurable
                //throw new NotFoundException("Could not find type for foreign table '{$foreignTable}'");
            }
        }

        // Resolve relations to referenced types
        if ($foreignTable !== 'sys_file_reference' && !in_array($context->getFieldName(), self::$translationFields, true)) {
            $columnConfiguration = $context->getColumnConfiguration();
            $schemaContext = $context;

            if ($foreignTable !== '' && empty($columnConfiguration['config']['MM'])) {
                $fieldBuilder->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use (
                    $foreignTable, $schemaContext
                ): Deferred {
                    $foreignUid = $root[$resolveInfo->fieldName];
                    $modelClassPath = $schemaContext->getTypeRegistry()
                                                    ->getModelClassPathByTableName($foreignTable);
                    $language = (int)($args[QueryArgumentsUtility::$language] ?? 0);
                    $this->resolverBuffer->add($modelClassPath, $foreignUid, $language);

                    return new Deferred(function() use ($modelClassPath, $foreignUid, $language) {
                        $this->resolverBuffer->loadBuffered($modelClassPath, $language);

                        return $this->resolverBuffer->get($modelClassPath, $foreignUid, $language);
                    });
                });
            } elseif (!empty($columnConfiguration['config']['MM'])) {
                $fieldBuilder->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use (
                    $foreignTable, $schemaContext
                ) {
                    return $this->queryResolver->fetchForeignRecordsWithMM($root, $args, $context, $resolveInfo, $schemaContext, $foreignTable);
                });
            }
        }
    }

    /**
     * @throws NotFoundException
     */
    public function handleCategoryType(Context $context, FieldBuilder $fieldBuilder): void
    {
        $columnConfiguration = $context->getColumnConfiguration();

        if ($columnConfiguration['config']['relationship'] !== 'oneToOne') {
            return;
        }

        try {
            $type = $context->getTypeRegistry()->getTypeByTableName('sys_category');
            $fieldBuilder->setType($type);
        }
        catch (NotFoundException $e) {
            throw new NotFoundException("Could not find type for foreign table 'sys_category'");
        }

        $schemaContext = $context;

        $fieldBuilder->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use ($schemaContext) {
            return $this->queryResolver->fetchForeignRecord($root, $args, $context, $resolveInfo, $schemaContext, 'sys_category');
        });
    }
}
