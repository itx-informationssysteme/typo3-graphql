<?php

namespace Itx\Typo3GraphQL\Schema;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Itx\Typo3GraphQL\Resolver\QueryResolver;
use Itx\Typo3GraphQL\Types\TCATypeMapper;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use Psr\Log\LoggerInterface;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class SchemaGenerator
{
    protected PersistenceManager $persistenceManager;
    protected TableNameResolver $tableNameResolver;
    protected LoggerInterface $logger;
    protected LanguageService $languageService;
    protected TCATypeMapper $typeMapper;
    protected QueryResolver $queryResolver;
    protected ConfigurationManager $configurationManager;

    public function __construct(PersistenceManager $persistenceManager, TableNameResolver $tableNameResolver, LoggerInterface $logger, LanguageService $languageService, TCATypeMapper $typeMapper, QueryResolver $queryResolver, ConfigurationManager $configurationManager)
    {
        $this->persistenceManager = $persistenceManager;
        $this->tableNameResolver = $tableNameResolver;
        $this->logger = $logger;
        $this->languageService = $languageService;
        $this->typeMapper = $typeMapper;
        $this->queryResolver = $queryResolver;
        $this->configurationManager = $configurationManager;
    }

    /**
     * @throws NameNotFoundException
     * @throws InvalidConfigurationTypeException
     */
    public function generate(): Schema
    {
        $queries = [];

        $typeRegistry = new TypeRegistry();

        $configuration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK, 'typo3_graphql');
        $models = array_keys($configuration['models'] ?? []);

        $globalDisabledFields = explode(',', trim($configuration['settings']['globalDisabledFields'] ?? ''));

        // Iterate over all tables/models
        foreach ($models as $modelClassPath) {
            if (($configuration['models'][$modelClassPath]['enabled'] ?? '1') === '0') {
                continue;
            }

            // Get the table name
            $tableName = $this->tableNameResolver->resolve($modelClassPath);

            $objectName = NamingUtility::generateName($this->languageService->sL($GLOBALS['TCA'][$tableName]['ctrl']['title']), false);

            // Type configuration
            $object = ObjectBuilder::create($objectName)->setDescription('TODO');

            // Build a ObjectType from the type configuration
            $objectType = new ObjectType($object->setFields(function() use ($globalDisabledFields, $typeRegistry, $modelClassPath, $tableName, $configuration) {
                $fields = [
                    FieldBuilder::create('uid', Type::nonNull(Type::int()))
                                ->setDescription('Unique identifier in table')
                                ->build(),
                    FieldBuilder::create('pid', Type::nonNull(Type::int()))->setDescription('Page id')->build(),
                ];

                $disabledFields = explode(',', trim($configuration['models'][$modelClassPath]['disabledFields'] ?? ''));
                $disabledFields = array_merge($disabledFields, $globalDisabledFields);

                // Add fields for all columns to type config
                foreach ($GLOBALS['TCA'][$tableName]['columns'] as $fieldName => $columnConfiguration) {
                    if (in_array($fieldName, $disabledFields, true)) {
                        continue;
                    }

                    try {
                        $context = new Context($modelClassPath, $tableName, $fieldName, $columnConfiguration, $typeRegistry);
                        $field = $this->typeMapper->buildField($context)
                                                  ->setDescription($this->languageService->sL($columnConfiguration['label']));

                        $fields[] = $field->build();
                    }
                    catch (UnsupportedTypeException $e) {
                        $this->logger->debug($e->getMessage());
                    }
                }

                return $fields;
            })->build());

            $typeRegistry->addObjectType($objectType, $tableName, $modelClassPath);

            // Add a query to fetch multiple records
            $queries[] = FieldBuilder::create(NamingUtility::generateNameFromClassPath($modelClassPath, true), Type::listOf($objectType))
                                     ->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use ($modelClassPath) {
                                         return $this->queryResolver->fetchMultipleRecords($root, $args, $context, $resolveInfo, $modelClassPath);
                                     })
                                     ->addArgument('language', Type::nonNull(Type::int()), 'Language field', 0)
                                     ->addArgument('pageIds', Type::listOf(Type::int()), 'List of storage page ids', [])
                                     ->build();

            $singleQueryName = NamingUtility::generateNameFromClassPath($modelClassPath, false);

            // Add a query to fetch a single record
            $queries[] = FieldBuilder::create($singleQueryName, $objectType)
                                     ->setResolver(function($root, $args, $context, ResolveInfo $resolveInfo) use (
                                         $modelClassPath
                                     ) {
                                         return $this->queryResolver->fetchSingleRecord($root, $args, $context, $resolveInfo, $modelClassPath);
                                     })
                                     ->addArgument('uid', Type::nonNull(Type::int()), "Get a $singleQueryName by it's uid")
                                     ->addArgument('language', Type::nonNull(Type::int()), 'Language field', 0)
                                     ->build();
        }

        $schemaConfig = SchemaConfig::create();

        $root = new ObjectType(ObjectBuilder::create('Query')->setFields($queries)->build());

        $typeRegistry->addType($root);

        $schemaConfig->setQuery($root)->setTypeLoader(static fn(string $name): Type => $typeRegistry->getType($name));

        return new Schema($schemaConfig);
    }
}
