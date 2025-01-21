<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;
use Itx\Typo3GraphQL\Types\DateType;

class DateInputType extends InputObjectType implements TypeNameInterface
{

    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];

        $fields[] = InputFieldBuilder::create('min', DateType::$standardTypes)->build();
        $fields[] = InputFieldBuilder::create('max', DateType::$standardTypes)->build();

        $objectBuilder->setFields($fields);
        $objectBuilder->setDescription('Inclusive range of dates');
        parent::__construct($objectBuilder->build());
    }
    /**
     * @return string
     */
    public static function getTypeName(): string
    {
        return 'DateInput';
    }
}
