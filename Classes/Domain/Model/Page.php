<?php

namespace Itx\Typo3GraphQL\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Page extends AbstractEntity
{
    protected string $title;

    protected string $slug;

    protected bool $isSiteroot;


}
