<?php

namespace Itx\Typo3GraphQL\Domain\Model;

use Itx\Typo3GraphQL\Annotation\Expose;
use Itx\Typo3GraphQL\Annotation\ExposeAll;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * @ExposeAll()
 */
class Page extends AbstractEntity
{
    /** @Expose() */
    protected string $title;

    protected string $slug;

    /** @Expose() */
    protected bool $isSiteroot;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     */
    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    /**
     * @return bool
     */
    public function isSiteroot(): bool
    {
        return $this->isSiteroot;
    }

    /**
     * @param bool $isSiteroot
     */
    public function setIsSiteroot(bool $isSiteroot): void
    {
        $this->isSiteroot = $isSiteroot;
    }
}
