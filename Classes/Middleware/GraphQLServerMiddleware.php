<?php

namespace Itx\Typo3GraphQL\Middleware;

use GraphQL\Error\DebugFlag;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Itx\Typo3GraphQL\Schema\SchemaGenerator;
use Itx\Typo3GraphQL\Services\ConfigurationService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

class GraphQLServerMiddleware implements MiddlewareInterface
{
    protected SchemaGenerator $schemaGenerator;
    protected LoggerInterface $logger;
    protected ConfigurationService $configurationService;

    public function __construct(SchemaGenerator $schemaGenerator, LoggerInterface $logger, ConfigurationService $configurationService)
    {
        $this->schemaGenerator = $schemaGenerator;
        $this->logger = $logger;
        $this->configurationService = $configurationService;
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws JsonException
     * @throws NameNotFoundException
     * @throws NotFoundException
     * @throws UnsupportedTypeException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
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

        $settings = $this->configurationService->getSettings();

        $maxQueryComplexity = $settings['maxQueryComplexity'] ?? 100;
        $isIntrospectionEnabled = $settings['isIntrospectionEnabled'] ?? true;

        $rules = [
            new QueryComplexity($maxQueryComplexity),
        ];

        if ($isIntrospectionEnabled === false) {
            $rules[] = new DisableIntrospection();
        }

        $server = new \GraphQL\Server\StandardServer([
                                                         'schema' => $schema,
                                                         // Todo make configurable, maybe based on TYPO3 debug configuration
                                                         'debugFlag' => DebugFlag::RETHROW_INTERNAL_EXCEPTIONS,
                                                         'validationRules' => $rules
                                                     ]);

        $response = new JsonResponse();

        return $server->processPsrRequest($request, $response, $response->getBody());
    }
}
