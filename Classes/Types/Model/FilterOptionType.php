<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;

class FilterOptionType extends \GraphQL\Type\Definition\ObjectType implements TypeNameInterface
{
    public ?string $description = 'A filter option object';

    public function __construct()
    {
        $objectBuilder = ObjectBuilder::create(self::getTypeName());

        $fields = [];
        $fields[] = FieldBuilder::create('value', Type::nonNull(Type::string()))
                                ->setDescription('The filter value')
                                ->build();

        $fields[] = FieldBuilder::create('resultCount', Type::nonNull(Type::int()))
                                ->setDescription('The result count for this filter option')
                                ->build();

        $fields[] = FieldBuilder::create('selected', Type::nonNull(Type::boolean()))
                                ->setDescription('Whether this filter option was selected through a filter argument.')
                                ->build();

        $fields[] = FieldBuilder::create('disabled', Type::nonNull(Type::boolean()))
                                ->setDescription('Whether this filter option is disabled, because of other filters.')
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }

    public static function getTypeName(): string
    {
        return 'FilterOption';
    }
}
