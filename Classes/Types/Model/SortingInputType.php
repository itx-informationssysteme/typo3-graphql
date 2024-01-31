<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use Itx\Typo3GraphQL\Utility\NamingUtility;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;

class SortingInputType extends \GraphQL\Type\Definition\InputObjectType
{
    public $description = 'Sorting';

    /**
     * @throws NameNotFoundException
     */
    public function __construct(string $modelClassPath, NullableType $sortingFieldType)
    {
        $this->name = ucfirst(NamingUtility::generateNameFromClassPath($modelClassPath, false) . 'Sorting');
        $sortingObject = InputObjectBuilder::create($this->name);
        $sortingObject->setFields([
                                      InputFieldBuilder::create('field', Type::nonNull($sortingFieldType))->setDescription('Sort by field')->build(),
                                      InputFieldBuilder::create('order', Type::nonNull(TypeRegistry::sortingOrder()))->setDescription('Sort order')->build()
                                  ]);

        parent::__construct($sortingObject->build());
    }
}