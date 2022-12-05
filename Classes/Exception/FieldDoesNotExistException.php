<?php

namespace Itx\Typo3GraphQL\Exception;

use GraphQL\Error\Error;

class FieldDoesNotExistException extends Error
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
