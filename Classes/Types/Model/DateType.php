<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;

class DateType extends ObjectType implements TypeNameInterface
{
    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];

        $fields[] = InputFieldBuilder::create('min', Type::nonNull(Type::int()))->build();  // TODO: Date not available?
        $fields[] = InputFieldBuilder::create('max', Type::nonNull(Type::int()))->build();

        $objectBuilder->setFields($fields);
        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'Date';
    }
}
