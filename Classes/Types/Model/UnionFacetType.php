<?php

namespace Itx\Typo3GraphQL\Types\Model;

use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use PHPStan\ShouldNotHappenException;
use SimPod\GraphQLUtils\Builder\UnionBuilder;

class UnionFacetType extends \GraphQL\Type\Definition\UnionType implements TypeNameInterface
{
    /**
     * @throws NameNotFoundException
     */
    public function __construct()
    {
        $builder = UnionBuilder::create(self::getTypeName())
                               ->setTypes([TypeRegistry::rangeFacet(), TypeRegistry::facet()])
                               ->setResolveType(function($value) {
                                   return match ($value['type']) {
                                       \Itx\Typo3GraphQL\Enum\FacetType::RANGE => TypeRegistry::rangeFacet(),
                                       \Itx\Typo3GraphQL\Enum\FacetType::DISCRETE => TypeRegistry::facet(),
                                       default => throw new \RuntimeException('Could not find Type ' . $value['type']),
                                   };
                               });
        parent::__construct($builder->build());
    }

    public static function getTypeName(): string
    {
        return 'UnionFacet';
    }
}
