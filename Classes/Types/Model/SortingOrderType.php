<?php

namespace Itx\Typo3GraphQL\Types\Model;

use SimPod\GraphQLUtils\Builder\EnumBuilder;
use SimPod\GraphQLUtils\Exception\InvalidArgument;

class SortingOrderType extends \GraphQL\Type\Definition\EnumType
{
    public $description = 'Sorting order';

    /**
     * @throws InvalidArgument
     */
    public function __construct()
    {
        $this->name = 'SortingOrder';
        $objectBuilder = EnumBuilder::create($this->name);

        $objectBuilder->addValue('ASC', 'Ascending');
        $objectBuilder->addValue('DESC', 'Descending');

        parent::__construct($objectBuilder->build());
    }
}
