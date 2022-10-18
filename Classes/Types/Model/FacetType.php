<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;

class FacetType extends ObjectType implements TypeNameInterface
{
    public $description = 'A filter facet';

    /**
     * @throws NameNotFoundException
     */
    public function __construct()
    {
        $objectBuilder = ObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = FieldBuilder::create('label', Type::nonNull(Type::string()))
                                ->setDescription('The filter label')
                                ->build();

        $fields[] = FieldBuilder::create('path', Type::nonNull(Type::string()))
                                ->setDescription('The filter path')
                                ->build();

        $fields[] = FieldBuilder::create('options', Type::nonNull(Type::listOf(TypeRegistry::filterOption())))
                                ->setDescription('The result count for this filter option')
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'Facet';
    }
}
