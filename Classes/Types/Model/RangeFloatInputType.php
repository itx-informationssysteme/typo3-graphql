<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;

class RangeFloatInputType extends InputObjectType implements TypeNameInterface
{

    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];

        $fields[] = InputFieldBuilder::create('min', Type::float())->build();
        $fields[] = InputFieldBuilder::create('max', Type::float())->build();

        $objectBuilder->setFields($fields);
        $objectBuilder->setDescription('Inclusive range of float numbers');
        parent::__construct($objectBuilder->build());
    }
    /**
     * @return string
     */
    public static function getTypeName(): string
    {
        return 'RangeFloatInput';
    }
}
