<?php

namespace Itx\Typo3GraphQL\Exception;

class BadInputException extends \TYPO3\CMS\Core\Exception
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
