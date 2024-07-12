<?php

namespace Itx\Typo3GraphQL\Schema;

use Doctrine\Common\Annotations\AnnotationReader;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Itx\Typo3GraphQL\Annotation\Expose;
use Itx\Typo3GraphQL\Annotation\ExposeAll;
use Itx\Typo3GraphQL\Builder\FieldBuilder;
use Itx\Typo3GraphQL\Enum\RootQueryType;
use Itx\Typo3GraphQL\Events\CustomModelFieldEvent;
use Itx\Typo3GraphQL\Events\CustomQueryArgumentEvent;
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
use SimPod\GraphQLUtils\Exception\InvalidArgument;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;

class SchemaGenerator
{
    protected PersistenceManager $persistenceManager;
    protected TableNameResolver $tableNameResolver;
    protected LoggerInterface $logger;
    protected TCATypeMapper $typeMapper;
    protected QueryResolver $queryResolver;
    protected EventDispatcherInterface $eventDispatcher;
    protected FilterResolver $filterResolver;
    protected ConfigurationService $configurationService;
    protected ReflectionService $reflectionService;

    public function __construct(
        PersistenceManager $persistenceManager,
        TableNameResolver $tableNameResolver,
        LoggerInterface $logger,
        TCATypeMapper $typeMapper,
        QueryResolver $queryResolver,
        ConfigurationService $configurationService,
        EventDispatcherInterface $eventDispatcher,
        FilterResolver $filterResolver,
        ReflectionService $reflectionService
    ) {
        $this->persistenceManager = $persistenceManager;
        $this->tableNameResolver = $tableNameResolver;
        $this->logger = $logger;
        $this->typeMapper = $typeMapper;
        $this->queryResolver = $queryResolver;
        $this->eventDispatcher = $eventDispatcher;
        $this->filterResolver = $filterResolver;
        $this->configurationService = $configurationService;
        $this->reflectionService = $reflectionService;
    }

    /**
     * @throws NameNotFoundException
     * @throws UnsupportedTypeException
     * @throws NotFoundException
     * @throws InvalidArgument
     */
    public function generate(TypeRegistry $typeRegistry): Schema
    {
        $queries = [];

        $modelsConfiguration = $this->configurationService->getModels();

        $modelClassPaths = array_keys($modelsConfiguration);

        // Iterate over all tables/models
        foreach ($modelClassPaths as $modelClassPath) {
            if (($modelsConfiguration[$modelClassPath]['enabled'] ?? true) === false) {
                continue;
            }

            // Get the table name
            $tableName = $this->tableNameResolver->resolve($modelClassPath);

            $languageService = GeneralUtility::makeInstance(LanguageServiceFactory::class)?->create('en');
            $objectName =
                NamingUtility::generateName($languageService->sL($GLOBALS['TCA'][$tableName]['ctrl']['title']), false);

            // Type configuration
            $object = ObjectBuilder::create($objectName)->setDescription("The $objectName type");

            // Build a ObjectType from the type configuration
            $objectType = new ObjectType($object->setFields(function () use (
                $typeRegistry,
                $modelClassPath,
                $tableName
            ) {
                $fields = [
                    FieldBuilder::create('uid')
                                ->setType(Type::nonNull(Type::int()))
                                ->setDescription('Unique identifier in table')
                                ->build(),
                    FieldBuilder::create('pid')->setType(Type::nonNull(Type::int()))->setDescription('Page id')->build(),
                ];

                $schema = new \ReflectionClass($modelClassPath);
                $annotationReader = new AnnotationReader();

                $allowList = [];
                $exposeAllProperties = false;

                // First we check if the class has an @ExposeAll annotation
                $classAnnotation = $annotationReader->getClassAnnotation($schema, ExposeAll::class);
                if ($classAnnotation instanceof ExposeAll) {
                    $exposeAllProperties = true;
                }

                // Then we collect all properties that either have an @Expose annotation or are covered by its class's @ExposeAll annotation
                foreach ($schema->getProperties() as $property) {
                    if ($property->getName() === 'uid' || $property->getName() === 'pid' ||
                        str_starts_with($property->getName(), '_')) {
                        continue;
                    }

                    $annotation = $annotationReader->getPropertyAnnotation($property, Expose::class);

                    if ($exposeAllProperties || $annotation instanceof Expose) {
                        $allowList[] = $property->getName();
                    }
                }

                // Add fields for all columns to type config
                foreach ($allowList as $fieldName) {
                    $columnConfiguration =
                        $GLOBALS['TCA'][$tableName]['columns'][GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName)] ??
                        null;
                    if ($columnConfiguration === null) {
                        throw new NotFoundException(sprintf('Column %s not found in table %s', $fieldName, $tableName));
                    }

                    $fieldAnnotations = $annotationReader->getPropertyAnnotations($schema->getProperty($fieldName));

                    try {
                        $context = new Context(
                            $modelClassPath,
                            $tableName,
                            $fieldName,
                            $columnConfiguration,
                            $typeRegistry,
                            $fieldAnnotations
                        );
                        $field = $this->typeMapper->buildField($context);

                        $fields[] = $field->build();
                    } catch (UnsupportedTypeException $e) {
                        $this->logger->debug($e->getMessage());
                    }
                }

                // Add custom fields to model
                /** @var CustomModelFieldEvent $customEvent */
                $customEvent =
                    $this->eventDispatcher->dispatch(new CustomModelFieldEvent($modelClassPath, $tableName, $typeRegistry));

                foreach ($customEvent->getFieldBuilders() as $field) {
                    $fields[] = $field->build();
                }

                return $fields;
            })->build());

            $typeRegistry->addModelObjectType($objectType, $tableName, $modelClassPath);

            // We only continue here if the type is marked as queryable: true
            if (($modelsConfiguration[$modelClassPath]['queryable'] ?? false) === false) {
                continue;
            }

            $connectionType = PaginationUtility::generateConnectionTypes($objectType, $typeRegistry);

            // Add a query to fetch multiple records
            $multipleQuery = FieldBuilder::create(NamingUtility::generateNameFromClassPath($modelClassPath, true))
                                         ->setType(Type::nonNull($connectionType))
                                         ->setResolver(function ($root, array $args, $context, ResolveInfo $resolveInfo) use (
                                             $modelClassPath,
                                             $tableName
                                         ) {
                                             $facets = [];

                                             // See if requested page ids are allowed
                                             $allowedMountPoints =
                                                 $this->configurationService->getMountPointsForModel($modelClassPath);

                                             if (count($allowedMountPoints) > 0) {
                                                 // Check if mount points from arguments are contained in allowed mount points
                                                 $requestedMountPoints = $args[QueryArgumentsUtility::$pageIds] ?? [];
                                                 $invalidMountPoints = array_diff($requestedMountPoints, $allowedMountPoints);

                                                 if (count($invalidMountPoints) > 0) {
                                                     throw new \InvalidArgumentException(sprintf(
                                                         'Requested mount points "%s" are not allowed',
                                                         implode(
                                                             ', ',
                                                             $invalidMountPoints
                                                         )
                                                     ));
                                                 }

                                                 // If no mount points are requested, we use the allowed mount points
                                                 if (count($requestedMountPoints) === 0) {
                                                     $args[QueryArgumentsUtility::$pageIds] = $allowedMountPoints;
                                                 }
                                             }

                                             // Query facets if requested
                                             if ($resolveInfo->getFieldSelection()['facets'] ?? false) {
                                                 $facets = $this->filterResolver->fetchFiltersIncludingFacets(
                                                     $root,
                                                     $args,
                                                     $context,
                                                     $resolveInfo,
                                                     $tableName,
                                                     $modelClassPath
                                                 );
                                             }

                                             // Query actual records
                                             $queryResult = $this->queryResolver->fetchMultipleRecords(
                                                 $root,
                                                 $args,
                                                 $context,
                                                 $resolveInfo,
                                                 $modelClassPath,
                                                 $tableName
                                             );
                                             $queryResult->setFacets($facets);

                                             return $queryResult;
                                         })
                                         ->addArgument(
                                             QueryArgumentsUtility::$language,
                                             Type::int(),
                                             'Language field'
                                         )
                                         ->addArgument(
                                             QueryArgumentsUtility::$pageIds,
                                             Type::listOf(Type::int()),
                                             'List of storage page ids',
                                             []
                                         );

            /** @var CustomQueryArgumentEvent $event */
            $event = $this->eventDispatcher->dispatch(new CustomQueryArgumentEvent(
                RootQueryType::Multiple,
                $multipleQuery,
                $modelClassPath,
                $tableName,
                $typeRegistry
            ));
            $multipleQuery = $event->getFieldBuilder();

            $queries[] =
                $this->typeMapper->addPaginationArgumentsToFieldBuilder($multipleQuery, $modelClassPath, $typeRegistry)->build();

            // Generate a name for the single query
            $singleQueryName = NamingUtility::generateNameFromClassPath($modelClassPath, false);

            // Add a query to fetch a single record
            $singleQuery = FieldBuilder::create($singleQueryName)->setType($objectType)->setResolver(function (
                $root,
                $args,
                $context,
                ResolveInfo $resolveInfo
            ) use (
                $modelClassPath
            ) {
                return $this->queryResolver->fetchSingleRecord(
                    $root,
                    $args,
                    $context,
                    $resolveInfo,
                    $modelClassPath
                );
            })->addArgument(
                QueryArgumentsUtility::$uid,
                Type::nonNull(Type::int()),
                "Get a $singleQueryName by it's uid"
            )->addArgument(
                QueryArgumentsUtility::$language,
                Type::int(),
                'Language field'
            );

            /** @var CustomQueryArgumentEvent $event */
            $event = $this->eventDispatcher->dispatch(new CustomQueryArgumentEvent(
                RootQueryType::Single,
                $singleQuery,
                $modelClassPath,
                $tableName,
                $typeRegistry
            ));
            $singleQuery = $event->getFieldBuilder();

            $queries[] = $singleQuery->build();
        }

        // Allow for custom new query fields
        /** @var CustomQueryFieldEvent $customEvent */
        $customEvent = $this->eventDispatcher->dispatch(new CustomQueryFieldEvent($typeRegistry));

        foreach ($customEvent->getFieldBuilders() as $field) {
            $queries[] = $field->build();
        }

        $schemaConfig = SchemaConfig::create();

        $root = new ObjectType(ObjectBuilder::create('Query')->setFields($queries)->build());

        $typeRegistry->addType($root);

        $schemaConfig->setQuery($root)->setTypeLoader(static fn(string $name): Type => $typeRegistry->getType($name));

        return new Schema($schemaConfig);
    }
}
