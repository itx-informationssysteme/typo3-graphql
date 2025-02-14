<?php

namespace Itx\Typo3GraphQL\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;

class GraphQLCacheMiddleware implements MiddlewareInterface
{
    protected LoggerInterface $logger;
    public function __construct(protected FrontendInterface $cache)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestMethod = $request->getMethod();

        // Only process requests with the POST and GET method
        if (!in_array($requestMethod, ['POST', 'GET']) || $request->getUri()->getPath() !== '/graphql') {
            return $handler->handle($request);
        }

        $body = $request->getBody()->getContents();
        $cacheKey = md5($body);

        if ($this->cache->has($cacheKey)) {
            $body = new Stream('php://temp', 'wb+');
            $body->write($this->cache->get($cacheKey));
            $response = new Response($body);
            $response = $response->withHeader('Content-Type', 'application/json');
            return $response;
        }

        $response = $handler->handle($request);

        $response->getBody()->rewind();
        $this->cache->set($cacheKey, $response->getBody()->getContents(), [], 3600);

        return $response;
    }
}
