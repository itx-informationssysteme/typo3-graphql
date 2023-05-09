<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;

class RangeFilterInputType extends InputObjectType implements TypeNameInterface
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $range;

    public function __construct(ObjectBuilder $objectBuilder)
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = InputFieldBuilder::create('path', Type::nonNull(Type::string()))->setDescription('The filter path')->build();

        $fields[] = InputFieldBuilder::create('range', Type::nonNull(TypeRegistry::rangeInput()));

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'RangeFilterInputType';
    }
}
