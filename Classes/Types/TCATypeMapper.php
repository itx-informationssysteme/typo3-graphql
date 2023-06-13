<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Itx\Typo3GraphQL\Resolver\DefaultFieldResolver;
use Itx\Typo3GraphQL\Resolver\FilterResolver;
use Itx\Typo3GraphQL\Resolver\QueryResolver;
use Itx\Typo3GraphQL\Resolver\ResolverBuffer;
use Itx\Typo3GraphQL\Schema\Context;
use Itx\Typo3GraphQL\Schema\TableNameResolver;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use Itx\Typo3GraphQL\Utility\PaginationUtility;
use SimPod\GraphQLUtils\Builder\EnumBuilder;
use SimPod\GraphQLUtils\Exception\InvalidArgument;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class TCATypeMapper
{
    protected LanguageService $languageService;
    protected TableNameResolver $tableNameResolver;
    protected QueryResolver $queryResolver;
    protected ResolverBuffer $resolverBuffer;
    protected FilterResolver $filterResolver;

    protected static array $translationFields = [
        'l10n_parent',
        'l18n_parent'
    ];

    public function __construct(LanguageService   $languageService,
                                TableNameResolver $tableNameResolver,
                                QueryResolver     $queryResolver,
                                ResolverBuffer    $resolverBuffer,
                                FilterResolver    $filterResolver)
    {
        $this->languageService = $languageService;
        $this->tableNameResolver = $tableNameResolver;
        $this->queryResolver = $queryResolver;
        $this->resolverBuffer = $resolverBuffer;
        $this->filterResolver = $filterResolver;
    }

    /**
     * @param Context $context
     *
     * @return FieldBuilder
     * @throws NotFoundException
     * @throws UnsupportedTypeException
     * @throws NameNotFoundException|InvalidArgument
     */
    public function buildField(Context $context): FieldBuilder
    {
        $fieldBuilder = FieldBuilder::create($context->getFieldName());

        $fieldBuilder->setDescription($this->languageService->sL($context->getColumnConfiguration()['label'] ?? ''));

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
        if (($columnConfiguration['config']['foreign_table'] ?? '') !== 'sys_file_reference' &&
            (($columnConfiguration['config']['maxitems'] ?? 2) > 1) &&
            ((!empty($columnConfiguration['config']['MM'])) || (!empty($columnConfiguration['config']['type'] === 'inline')))) {
            $isLazy = false;
            foreach ($context->getFieldAnnotations() as $annotation) {
                if ($annotation instanceof Lazy) {
                    $isLazy = true;
                }
            }

            if ($isLazy) {
                $paginationConnection = PaginationUtility::generateConnectionTypes($fieldBuilder->getType(),
                                                                                   $context->getTypeRegistry(),
                                                                                   $this->filterResolver,
                                                                                   $context->getTableName());

                $fieldBuilder->setType(Type::nonNull($paginationConnection));

                PaginationUtility::addArgumentsToFieldBuilder($fieldBuilder);
            } else {
                $fieldBuilder->setType(Type::nonNull(Type::listOf(Type::nonNull($fieldBuilder->getType()))));
            }
        }

        // If the field is required, the type is a non-null type
        if (!empty($columnConfiguration['config']['eval']) && str_contains($columnConfiguration['config']['eval'], 'required')) {
            $type = $fieldBuilder->getType();
            $fieldBuilder->setType(Type::nonNull($type));
        }

        return $fieldBuilder;
    }

    /**
     * @throws NameNotFoundException
     */
    protected function handleInputType(Context $context, FieldBuilder $fieldBuilder): void
    {
        $columnConfiguration = $context->getColumnConfiguration();

        // DateTime
        if (($columnConfiguration['config']['renderType'] ?? '') === 'inputDateTime') {
            $fieldBuilder->setType(TypeRegistry::dateTime());

            return;
        }

        // Link
        if (($columnConfiguration['config']['renderType'] ?? '') === 'inputLink') {
            $fieldBuilder->setType(TypeRegistry::link());

            return;
        }

        if (str_contains($columnConfiguration['config']['eval'] ?? '', 'int')) {
            $fieldBuilder->setType(Type::int());

            return;
        }

        if (str_contains($columnConfiguration['config']['eval'] ?? '', 'double2')) {
            $fieldBuilder->setType(Type::float());

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

    /**
     * @throws NameNotFoundException
     */
    protected function handleInlineType(Context $context, FieldBuilder $fieldBuilder): void
    {
        $columnConfiguration = $context->getColumnConfiguration();

        if (($columnConfiguration['config']['foreign_table'] ?? '') !== 'sys_file_reference') {
            return;
        }

        $expectedFileTypes =
            $columnConfiguration['config']['overrideChildTca']['columns']['uid_local']['config']['appearance']['elementBrowserAllowed']
            ?? '';

        // Fetch available crop variants
        $cropVariants = implode(', ',
                                array_keys($columnConfiguration['config']['overrideChildTca']['columns']['crop']['config']['cropVariants']
                                               ?? []));

        $description = $this->languageService->sL($columnConfiguration['label'] ?? '');

        if ($expectedFileTypes) {
            $description .= "\n - Allowed file types: $expectedFileTypes";
        }

        if ($cropVariants) {
            $description .= "\n - Available crop variants: $cropVariants";
        }

        $fieldBuilder->setType(TypeRegistry::file())->setDescription($description);
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
            if (count(array_filter($columnConfiguration['config']['items'],
                    static fn($x) => !MathUtility::canBeInterpretedAsInteger($x[1]))) === 0) {
                $fieldBuilder->setType(Type::int());

                return;
            }

            if (count(array_filter($columnConfiguration['config']['items'],
                    static fn($x) => !MathUtility::canBeInterpretedAsFloat($x[1]))) === 0) {
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
                    $builder->addValue($item, NamingUtility::generateName($item, false), $this->languageService->sL($label));
                }
                catch (InvalidArgument $e) {
                    $fieldBuilder->setType(Type::string());

                    return;
                }
            }

            $enumType = new EnumType($builder->build());

            $context->getTypeRegistry()->addType($enumType);

            // Check if this is a multi select field
            if ($columnConfiguration['config']['renderType'] === 'selectMultipleSideBySide') {
                $fieldBuilder->setType(Type::nonNull(Type::listOf(Type::nonNull($enumType))));
                $fieldBuilder->setResolver(static function ($value, $args, $context, ResolveInfo $info) {
                    // Access getter function with field name
                    $fieldValue = DefaultFieldResolver::defaultFieldResolver($value, $args, $context, $info);
                    if ($fieldValue === null) {
                        return [];
                    }

                    $fieldValue = trim($fieldValue);
                    
                    if ($fieldValue === '') {
                        return [];
                    }

                    return explode(',', $fieldValue);
                });

                return;
            }

            // Else we have a single select field
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
                // We do nothing here, because, the type should be configured in the YAML file
            }
        }

        // Resolve relations to referenced types
        if ($foreignTable !== 'sys_file_reference' && !in_array($context->getFieldName(), self::$translationFields, true)) {
            $columnConfiguration = $context->getColumnConfiguration();
            $schemaContext = $context;

            if (!empty($columnConfiguration['config']['MM'])) {
                $isLazy = false;

                foreach ($schemaContext->getFieldAnnotations() as $annotation) {
                    if ($annotation instanceof Lazy) {
                        $isLazy = true;
                    }
                }

                // We only need to set a custom resolver if the relation is lazy, and we want to paginate it
                if (!$isLazy) {
                    return;
                }

                /** @var ObjectStorage $root */
                $fieldBuilder->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use (
                    $foreignTable,
                    $schemaContext
                ) {
                    $facets = [];

                    if ($resolveInfo->getFieldSelection()['facets'] ?? false) {
                        $modelClassPath = $schemaContext->getTypeRegistry()->getModelClassPathByTableName($foreignTable);
                        $mmTable = $schemaContext->getColumnConfiguration()['config']['MM'];

                        $facets = $this->filterResolver->fetchFiltersWithRelationConstraintIncludingFacets($root,
                                                                                                           $args,
                                                                                                           $context,
                                                                                                           $resolveInfo,
                                                                                                           $foreignTable,
                                                                                                           $modelClassPath,
                                                                                                           $mmTable,
                                                                                                           $root->getUid());
                    }

                    $queryResult = $this->queryResolver->fetchForeignRecordsWithMM($root,
                                                                                   $args,
                                                                                   $context,
                                                                                   $resolveInfo,
                                                                                   $schemaContext,
                                                                                   $schemaContext->getColumnConfiguration()['config']['foreign_table']);
                    $queryResult->setFacets($facets);

                    return $queryResult;
                });
            }
        }
    }

    /**
     * @throws NotFoundException
     */
    public function handleCategoryType(Context $context, FieldBuilder $fieldBuilder): void
    {
        $type = $context->getTypeRegistry()->getTypeByTableName('sys_category');

        $fieldBuilder->setType($type);
    }
}
