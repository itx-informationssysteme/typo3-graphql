<?php

namespace Itx\Typo3GraphQL\Exception;

use GraphQL\Error\Error;

class NotImplementedException extends Error
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
