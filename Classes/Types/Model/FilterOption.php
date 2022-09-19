<?php

namespace Itx\Typo3GraphQL\Types\Model;

use GraphQL\Type\Definition\Type;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

class FilterOption extends \GraphQL\Type\Definition\ObjectType
{
    public $name = 'FilterOption';

    public $description = 'A filter option object';

    public function __construct()
    {

        $objectBuilder = ObjectBuilder::create($this->name);

        $fields = [];
        $fields[] = FieldBuilder::create('value', Type::nonNull(Type::string()))
                                ->setDescription('The filter value')
                                ->build();
        $fields[] = FieldBuilder::create('resultCount', Type::nonNull(Type::string()))
                                ->setDescription('The result count for this filter option')
                                ->build();

        $objectBuilder->setFields($fields);

        parent::__construct($objectBuilder->build());
    }
}
