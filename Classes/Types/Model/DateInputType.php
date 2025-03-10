<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;
use Itx\Typo3GraphQL\Types\DateType;
use Itx\Typo3GraphQL\Types\TypeRegistry;

class DateInputType extends InputObjectType implements TypeNameInterface
{

    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];

        $fields[] = InputFieldBuilder::create('min', TypeRegistry::dateTime())->build();
        $fields[] = InputFieldBuilder::create('max', TypeRegistry::dateTime())->build();

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
