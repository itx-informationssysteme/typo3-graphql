<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;

class ConnectionType extends \GraphQL\Type\Definition\ObjectType
{
    public $description = 'A connection to a list of items';

    public function __construct(Type $node, EdgeType $edge, Type $pageInfoType)
    {
        $this->name = NamingUtility::generateName($node->name, true).'Connection';
        $objectBuilder = ObjectBuilder::create($this->name);

        $fields = [];
        $fields[] = FieldBuilder::create('totalCount', Type::nonNull(Type::int()))
                                ->setDescription('Total count of items')
                                ->build();

        $fields[] = FieldBuilder::create('pageInfo', Type::nonNull($pageInfoType))
                                ->setDescription('Information to navigate the pagination')
                                ->build();

        $fields[] = FieldBuilder::create('edges', Type::nonNull(Type::listOf($edge)))
                                ->setDescription('A list of the edges')
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }
}
