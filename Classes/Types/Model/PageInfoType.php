<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;

class PageInfoType extends \GraphQL\Type\Definition\ObjectType implements TypeNameInterface
{
    public $description = 'Information to navigate the pagination';

    public function __construct()
    {
        $this->name = self::getTypeName();
        $objectBuilder = ObjectBuilder::create($this->name);

        $fields = [];
        $fields[] = FieldBuilder::create('endCursor', Type::nonNull(Type::string()))
                                ->setDescription('The last cursor, of the objects. This can be used to access the next page.')
                                ->build();

        $fields[] = FieldBuilder::create('hasNextPage', Type::nonNull(Type::boolean()))
                                ->setDescription('Whether there are more items available')
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'PageInfo';
    }
}
