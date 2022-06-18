<?php

namespace Itx\Typo3GraphQL\Middleware;

use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Schema\SchemaGenerator;
use JsonException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

class GraphQLServerMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    protected SchemaGenerator $schemaGenerator;
    protected LoggerInterface $logger;

    public function __construct(SchemaGenerator $schemaGenerator, LoggerInterface $logger)
    {
        $this->schemaGenerator = $schemaGenerator;
        $this->logger = $logger;
    }

    /**
     * @throws JsonException
     * @throws NameNotFoundException
     */
    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
    {
        $requestMethod = $request->getMethod();

        // Only process requests with the POST and GET method
        if (!in_array($requestMethod, ['POST', 'GET']) || $request->getUri()->getPath() !== '/graphql') {
            return $handler->handle($request);
        }

        // Retrieve the query from the request body
        if ($requestMethod === 'POST') {
            $parsedBodyString = (string)file_get_contents('php://input');
            $parsedBody = json_decode($parsedBodyString, true, 512, JSON_THROW_ON_ERROR);
            $request = $request->withParsedBody($parsedBody);
        }

        $schema = $this->schemaGenerator->generate();

        // TODO only when not in cache
        $schema->assertValid();

        $server = new \GraphQL\Server\StandardServer([
                                                         'schema' => $schema,
                                                         'errorsHandler' => fn(array $errors) => array_map(static fn($a) => throw new \Exception($a), $errors),
                                                     ]);

        $response = new JsonResponse();

        return $server->processPsrRequest($request, $response, $response->getBody());
    }
}
