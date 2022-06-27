<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;

class EdgeType extends ObjectType
{
    public $description = 'An edge in a connection';

    /**
     * @param $node Type
     */
    public function __construct(mixed $node)
    {
        $this->name = $node->name.'Edge';
        $objectBuilder = ObjectBuilder::create($this->name);

        $fields = [];

        $fields[] = FieldBuilder::create('node', $node)
                                ->setDescription('Contains the concrete object')
                                ->build();
        $fields[] = FieldBuilder::create('cursor', Type::string())
                                ->setDescription('A cursor for pagination')
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }
}
