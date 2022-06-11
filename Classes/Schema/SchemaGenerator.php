<?php

namespace Itx\Typo3GraphQL\Schema;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Psr\Log\LoggerInterface;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Persistence\ClassesConfiguration;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class SchemaGenerator
{
    protected PersistenceManager $persistenceManager;
    protected ClassesConfiguration $classesConfiguration;
    protected LoggerInterface $logger;
    protected LanguageService $languageService;

    protected array $tables = [
        Category::class
    ];

    public function __construct(PersistenceManager $persistenceManager, ClassesConfiguration $classesConfiguration, LoggerInterface $logger, LanguageService $languageService)
    {
        $this->persistenceManager = $persistenceManager;
        $this->classesConfiguration = $classesConfiguration;
        $this->logger = $logger;
        $this->languageService = $languageService;
    }

    public function generate(): Schema
    {
        $queries = [];

        // Iterate over all tables/models
        foreach ($this->tables as $table) {
            // Get the table name
            $classConfiguration = $this->classesConfiguration->getConfigurationFor($table);
            $tableName = $classConfiguration['tableName'];

            // Type configuration
            $object = ObjectBuilder::create($this->languageService->sL($GLOBALS['TCA'][$tableName]['ctrl']['title']))->setDescription('TODO');

            $fields = [
                FieldBuilder::create('uid', Type::int())->setDescription('Unique identifier in table')->build(),
                FieldBuilder::create('pid', Type::int())->setDescription('Page id')->build(),
            ];

            // Add fields for all columns to type config
            foreach ($GLOBALS['TCA'][$tableName]['columns'] as $fieldName => $columnConfiguration) {
                try {
                    $fields[] = FieldBuilder::create($fieldName, TCATypeMapper::map($columnConfiguration))->setDescription($this->languageService->sL($columnConfiguration['label']))->build();
                }
                catch (UnsupportedTypeException $e) {
                    $this->logger->debug($e->getMessage());
                }
            }

            // Build a ObjectType from the type configuration
            $objectType = new ObjectType($object->setFields($fields)->build());

            // Add a query to fetch multiple records
            // TODO better naming
            $queries[] = FieldBuilder::create("all_$tableName", Type::listOf($objectType))->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) {
                // TODO we can fetch only the field that we need by using the resolveInfo, but we need to make sure that the repository logic is kept
                return $this->persistenceManager->createQueryForType(Category::class)->execute(true);
            })->build();

            $name = $tableName;

            // Add a query to fetch a single record
            $queries[] = FieldBuilder::create($name, $objectType)->setResolver(function($root, array $args, $context, ResolveInfo $resolveInfo) use ($name) {
                $uid = $args['uid'];

                $query = $this->persistenceManager->createQueryForType(Category::class);
                $query->matching($query->equals('uid', $uid));

                $result = $query->execute(true)[0] ?? null;
                if ($result === null) {
                    throw new NotFoundException("No result for $name with uid $uid found");
                }

                return $result;
            })
                                                                      ->addArgument('uid', Type::int(), "Get a $name by it's uid")->build();
        }

        $schemaConfig = SchemaConfig::create();

        $schemaConfig->setQuery(new ObjectType(ObjectBuilder::create('Root')->setFields($queries)->build()));

        return new Schema($schemaConfig);
    }
}
