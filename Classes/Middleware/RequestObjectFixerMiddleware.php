<?php

namespace Itx\Typo3GraphQL\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * See https://forge.typo3.org/issues/95580#note-3 for more information
 */
class RequestObjectFixerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (($GLOBALS['TYPO3_REQUEST'] ?? null) === null) {
            $GLOBALS['TYPO3_REQUEST'] = $request;
        }

        return $handler->handle($request);
    }
}
