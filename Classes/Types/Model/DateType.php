<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;
use Itx\Typo3GraphQL\Types\DateType as DateTypeScalar;

class DateType extends ObjectType implements TypeNameInterface
{
    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];

        $fields[] = InputFieldBuilder::create('min', Type::nonNull(DateTypeScalar::$standardTypes))->build();
        $fields[] = InputFieldBuilder::create('max', Type::nonNull(DateTypeScalar::$standardTypes))->build();

        $objectBuilder->setFields($fields);
        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'Date';
    }
}
