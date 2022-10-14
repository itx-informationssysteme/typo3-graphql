<?php

namespace Itx\Typo3GraphQL\Schema;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Events\CustomModelFieldEvent;
use Itx\Typo3GraphQL\Events\CustomQueryFieldEvent;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Itx\Typo3GraphQL\Resolver\FilterResolver;
use Itx\Typo3GraphQL\Resolver\QueryResolver;
use Itx\Typo3GraphQL\Service\ConfigurationService;
use Itx\Typo3GraphQL\Types\TCATypeMapper;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use Itx\Typo3GraphQL\Utility\PaginationUtility;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class SchemaGenerator
{
    protected PersistenceManager $persistenceManager;
    protected TableNameResolver $tableNameResolver;
    protected LoggerInterface $logger;
    protected LanguageService $languageService;
    protected TCATypeMapper $typeMapper;
    protected QueryResolver $queryResolver;
    protected EventDispatcherInterface $eventDispatcher;
    protected FilterResolver $filterResolver;
    protected ConfigurationService $configurationService;

    public function __construct(PersistenceManager $persistenceManager, TableNameResolver $tableNameResolver, LoggerInterface $logger, LanguageService $languageService, TCATypeMapper $typeMapper, QueryResolver $queryResolver, ConfigurationService $configurationService, EventDispatcherInterface $eventDispatcher, FilterResolver $filterResolver)
    {
        $this->persistenceManager = $persistenceManager;
        $this->tableNameResolver = $tableNameResolver;
        $this->logger = $logger;
        $this->languageService = $languageService;
        $this->typeMapper = $typeMapper;
        $this->queryResolver = $queryResolver;
        $this->eventDispatcher = $eventDispatcher;
        $this->filterResolver = $filterResolver;
        $this->configurationService = $configurationService;
    }

    /**
     * @throws NameNotFoundException
     * @throws UnsupportedTypeException
     * @throws NotFoundException
     */
    public function generate(): Schema
    {
        $queries = [];

        $typeRegistry = new TypeRegistry();

        $modelsConfiguration = $this->configurationService->getModels();
        $settings = $this->configurationService->getSettings();

        $modelClassPaths = array_keys($modelsConfiguration);

        $globalDisabledFields = $this->configurationService->getGlobalDisabledFields();

        // Iterate over all tables/models
        foreach ($modelClassPaths as $modelClassPath) {
            if (($modelsConfiguration[$modelClassPath]['enabled'] ?? true) === false) {
                continue;
            }

            // Get the table name
            $tableName = $this->tableNameResolver->resolve($modelClassPath);

            $objectName = NamingUtility::generateName($this->languageService->sL($GLOBALS['TCA'][$tableName]['ctrl']['title']), false);

            // Type configuration
            $object = ObjectBuilder::create($objectName)->setDescription('TODO');

            // Build a ObjectType from the type configuration
            $objectType = new ObjectType($object->setFields(function() use ($modelsConfiguration, $globalDisabledFields, $typeRegistry, $modelClassPath, $tableName) {
                $fields = [
                    FieldBuilder::create('uid')->setType(Type::nonNull(Type::int()))->setDescription('Unique identifier in table')->build(),
                    FieldBuilder::create('pid')->setType(Type::nonNull(Type::int()))->setDescription('Page id')->build(),
                ];

                $disabledFields = $modelsConfiguration[$modelClassPath]['disabledFields'] ?? [];
                $disabledFields = array_merge($disabledFields, $globalDisabledFields);

                // Add fields for all columns to type config
                foreach ($GLOBALS['TCA'][$tableName]['columns'] as $fieldName => $columnConfiguration) {
                    if (in_array($fieldName, $disabledFields, true)) {
                        continue;
                    }

                    try {
                        $context = new Context($modelClassPath, $tableName, $fieldName, $columnConfiguration, $typeRegistry);
                        $field = $this->typeMapper->buildField($context)->setDescription($this->languageService->sL($columnConfiguration['label']));

                        $fields[] = $field->build();
                    }
                    catch (UnsupportedTypeException $e) {
                        $this->logger->debug($e->getMessage());
                    }
                }

                // Add custom fields to model
                /** @var CustomModelFieldEvent $customEvent */
                $customEvent = $this->eventDispatcher->dispatch(new CustomModelFieldEvent($modelClassPath, $tableName, $typeRegistry));

                foreach ($customEvent->getFieldBuilders() as $field) {
                    $fields[] = $field->build();
                }

                return $fields;
            })->build());

            $typeRegistry->addModelObjectType($objectType, $tableName, $modelClassPath);

            $connectionType = PaginationUtility::generateConnectionTypes($objectType, $typeRegistry, $this->filterResolver, $tableName);

            // Add a query to fetch multiple records
            $multipleQuery = FieldBuilder::create(NamingUtility::generateNameFromClassPath($modelClassPath, true))->setType(Type::nonNull($connectionType))->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use ($modelClassPath, $tableName) {
                    return $this->queryResolver->fetchMultipleRecords($root, $args, $context, $resolveInfo, $modelClassPath, $tableName);
                })->addArgument(QueryArgumentsUtility::$language, Type::nonNull(Type::int()), 'Language field', 0)->addArgument(QueryArgumentsUtility::$pageIds, Type::listOf(Type::int()), 'List of storage page ids', []);

            $queries[] = PaginationUtility::addPaginationArgumentsToFieldBuilder($multipleQuery)->build();

            // Generate a name for the single query
            $singleQueryName = NamingUtility::generateNameFromClassPath($modelClassPath, false);

            // Add a query to fetch a single record
            $queries[] = FieldBuilder::create($singleQueryName)->setType($objectType)->setResolver(function($root, $args, $context, ResolveInfo $resolveInfo) use (
                    $modelClassPath
                ) {
                    return $this->queryResolver->fetchSingleRecord($root, $args, $context, $resolveInfo, $modelClassPath);
                })->addArgument(QueryArgumentsUtility::$uid, Type::nonNull(Type::int()), "Get a $singleQueryName by it's uid")->addArgument(QueryArgumentsUtility::$language, Type::nonNull(Type::int()), 'Language field', 0)->build();

            // Allow for custom new query fields
            /** @var CustomQueryFieldEvent $customEvent */
            $customEvent = $this->eventDispatcher->dispatch(new CustomQueryFieldEvent($modelClassPath, $tableName, $typeRegistry));

            foreach ($customEvent->getFieldBuilders() as $field) {
                $queries[] = $field->build();
            }
        }

        $schemaConfig = SchemaConfig::create();

        $root = new ObjectType(ObjectBuilder::create('Query')->setFields($queries)->build());

        $typeRegistry->addType($root);

        $schemaConfig->setQuery($root)->setTypeLoader(static fn(string $name): Type => $typeRegistry->getType($name));

        return new Schema($schemaConfig);
    }
}
