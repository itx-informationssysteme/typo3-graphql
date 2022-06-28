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
        $multipleName = NamingUtility::generateName($node->name, true);
        $this->name = $multipleName . 'Connection';
        $objectBuilder = ObjectBuilder::create($this->name);

        $fields = [];
        $fields[] = FieldBuilder::create('totalCount', Type::nonNull(Type::int()))
                                ->setDescription('Total count of object in this connection.')
                                ->build();

        $fields[] = FieldBuilder::create('pageInfo', Type::nonNull($pageInfoType))
                                ->setDescription('Information to navigate the pagination.')
                                ->build();

        $fields[] = FieldBuilder::create('edges', Type::nonNull(Type::listOf($edge)))
                                ->setDescription('A list of the edges.')
                                ->build();

        $fields[] = FieldBuilder::create('items', Type::nonNull(Type::listOf($node)))
                                ->setDescription('A list of all of the objects returned in the connection. This is a convenience field provided for quickly exploring the API; rather than querying for "{ edges { node } }" when no edge data is needed, this field can be be used instead. Note that when clients like Relay need to fetch the "cursor" field on the edge to enable efficient pagination, this shortcut cannot be used, and the full "{ edges { node } }" version should be used instead.')
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }
}
