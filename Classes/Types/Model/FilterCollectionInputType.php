<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use Itx\Typo3GraphQL\Utility\QueryArgumentsUtility;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;

class FilterCollectionInputType extends InputObjectType implements TypeNameInterface
{
    public $description = 'Filter collection';

    /**
     * @throws NameNotFoundException
     */
    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = InputFieldBuilder::create(QueryArgumentsUtility::$discreteFilters, Type::listOf(Type::nonNull(TypeRegistry::discreteFilterInput())))->setDefaultValue([])->setDescription('Discrete filters')->build();
        $fields[] = InputFieldBuilder::create('rangeFilters', Type::listOf(TypeRegistry::rangeFilterInput()))->setDescription('Range Filters')->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'FilterCollectionInput';
    }
}
