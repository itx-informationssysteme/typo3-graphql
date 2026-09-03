<?php

namespace Itx\Typo3GraphQL\Domain\Model;

use Itx\Typo3GraphQL\Annotation\Expose;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class PageContent extends AbstractEntity
{
    /** @Expose */
    protected string $header = '';

    /** @Expose */
    protected int $headerLayout = 0;

    /** @Expose */
    protected string $bodytext = '';

    /**
     * @Expose
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $image;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->image = new ObjectStorage();
    }

    public function getHeader(): string
    {
        return $this->header;
    }

    public function setHeader(string $header): void
    {
        $this->header = $header;
    }

    public function getHeaderLayout(): int
    {
        return $this->headerLayout;
    }

    public function setHeaderLayout(int $headerLayout): void
    {
        $this->headerLayout = $headerLayout;
    }

    public function getBodytext(): string
    {
        return $this->bodytext;
    }

    public function setBodytext(string $bodytext): void
    {
        $this->bodytext = $bodytext;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getImage(): ObjectStorage
    {
        return $this->image;
    }

    /**
     * @param ObjectStorage<FileReference> $image
     */
    public function setImage(ObjectStorage $image): void
    {
        $this->image = $image;
    }
}
