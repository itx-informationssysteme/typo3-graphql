<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;

class DiscreteFilterInputType extends InputObjectType implements TypeNameInterface
{
    public ?string $description = 'A discrete filter.';

    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = InputFieldBuilder::create('path', Type::nonNull(Type::string()))->setDescription('The filter path')->build();

        $fields[] = InputFieldBuilder::create('options', Type::listOf(Type::nonNull(Type::string())))->setDefaultValue([])->setDescription('Selected filter options')->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'DiscreteFilterInput';
    }
}
