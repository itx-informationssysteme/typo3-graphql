<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;

class RangeFilterInputType extends InputObjectType implements TypeNameInterface
{
    /**
     * @throws NameNotFoundException
     */
    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = InputFieldBuilder::create('path', Type::nonNull(Type::string()))->setDescription('The filter path')->build();

        $fields[] = InputFieldBuilder::create('range', TypeRegistry::rangeInput())->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'RangeFilterInput';
    }
}
