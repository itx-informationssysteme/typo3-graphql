<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Itx\Typo3GraphQL\Exception\NotImplementedException;
use SimPod\GraphQLUtils\Builder\InputFieldBuilder;
use SimPod\GraphQLUtils\Builder\InputObjectBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class RangeInputType extends InputObjectType implements TypeNameInterface
{

    public function __construct()
    {
        $objectBuilder = InputObjectBuilder::create(self::getTypeName());

        $fields = [];

        $fields[] = InputFieldBuilder::create('min', Type::nonNull(Type::string()))->build();
        $fields[] = InputFieldBuilder::create('max', Type::nonNull(Type::string()))->build();

        $objectBuilder->setFields($fields);
        parent::__construct($objectBuilder->build());
    }
    /**
     * @return string
     */
    public static function getTypeName(): string
    {
        return 'RangeInput';
    }
}
