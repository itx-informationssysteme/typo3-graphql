<?php

namespace Itx\Typo3GraphQL\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class PageContent extends AbstractEntity
{
    protected string $header = '';
    protected string $headerLayout = '';
    protected string $bodytext = '';

    protected ObjectStorage $image;
    protected string $imageCaption;
}
