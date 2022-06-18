<?php

namespace Itx\Typo3GraphQL\Types;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;

class FileType extends \GraphQL\Type\Definition\ObjectType
{
    public $name = 'File';

    public $description = '';

    public function __construct()
    {
        $objectBuilder = ObjectBuilder::create($this->name);

        $fields = [];
        $fields[] = FieldBuilder::create('fileName', Type::string())->build();
        $fields[] = FieldBuilder::create('link', Type::string())->build();
        $fields[] = FieldBuilder::create('fileSize', Type::string())->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }
}
