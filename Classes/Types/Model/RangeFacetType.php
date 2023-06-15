<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;

class RangeFacetType extends ObjectType implements TypeNameInterface
{

    /**
     * @throws NameNotFoundException
     */
    public function __construct()
    {
        $objectBuilder = ObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = FieldBuilder::create('label', Type::nonNull(Type::string()))->setDescription('The filter label')->build();
        $fields[] = FieldBuilder::create('range', TypeRegistry::range())->build();
        $fields[] = FieldBuilder::create('selectedRange', TypeRegistry::range())->build();
        $fields[] = FieldBuilder::create('isSelected', Type::boolean())->build();
        $fields[] = FieldBuilder::create('unit', Type::string())->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'RangeFacet';
    }
}
