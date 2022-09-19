<?php

namespace Itx\Typo3GraphQL\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class GraphQLFilter extends AbstractEntity
{
    protected string $name = '';
    protected string $model = '';
    protected string $filterPath = '';

    /** @var ObjectStorage  */
    protected ObjectStorage $categories;

    public function __construct()
    {
        $this->categories = new ObjectStorage();
    }
}
