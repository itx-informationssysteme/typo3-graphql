<?php

namespace Itx\Typo3GraphQL\Types\Model;

use SimPod\GraphQLUtils\Builder\EnumBuilder;

class SortingOrderType extends \GraphQL\Type\Definition\EnumType
{
    public $description = 'Sorting order';

    public function __construct()
    {
        $this->name = 'SortingOrder';
        $objectBuilder = EnumBuilder::create($this->name);

        $objectBuilder->addValue('ASC', 'Ascending');
        $objectBuilder->addValue('DESC', 'Descending');

        parent::__construct($objectBuilder->build());
    }
}
