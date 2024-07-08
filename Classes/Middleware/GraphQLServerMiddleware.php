<?php

namespace Itx\Typo3GraphQL\Middleware;

use GraphQL\Error\DebugFlag;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use Itx\Typo3GraphQL\Exception\NameNotFoundException;
use Itx\Typo3GraphQL\Exception\NotFoundException;
use Itx\Typo3GraphQL\Exception\UnsupportedTypeException;
use Itx\Typo3GraphQL\Resolver\DefaultFieldResolver;
use Itx\Typo3GraphQL\Resolver\ResolverContext;
use Itx\Typo3GraphQL\Schema\SchemaGenerator;
use Itx\Typo3GraphQL\Service\ConfigurationService;
use Itx\Typo3GraphQL\Types\TypeRegistry;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GraphQLServerMiddleware implements MiddlewareInterface
{
    protected SchemaGenerator $schemaGenerator;
    protected ConfigurationService $configurationService;

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

        $container = GeneralUtility::getContainer();
        // Get all di dependencies
        $this->schemaGenerator = $container->get(SchemaGenerator::class);
        $this->configurationService = $container->get(ConfigurationService::class);

        // Start Extbase
        $extbaseBridge = new ExtbaseBridge(GeneralUtility::makeInstance(Context::class));
        $extbaseBridge->boot($request, $handler);

        $typeRegistry = new TypeRegistry();

        $schema = $this->schemaGenerator->generate($typeRegistry);

        // Only check schema in development context
        if (Environment::getContext()->isDevelopment()) {
            $schema->assertValid();
        }

        $settings = $this->configurationService->getSettings();

        $maxQueryComplexity = $settings['maxQueryComplexity'] ?? 100;
        $isIntrospectionEnabled = $settings['isIntrospectionEnabled'] ?? true;

        $rules = [
            new QueryComplexity($maxQueryComplexity),
        ];

        if ($isIntrospectionEnabled === false) {
            $rules[] = new DisableIntrospection(true);
        }

        $serverConfig = [
            'schema' => $schema,
            'context' => new ResolverContext($this->configurationService, $typeRegistry),
            'validationRules' => $rules,
            'fieldResolver' => [DefaultFieldResolver::class, 'defaultFieldResolver'],
        ];

        // if fe debug is enabled, rethrow exceptions
        if ($GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] ?? false) {
            $serverConfig['debugFlag'] = DebugFlag::RETHROW_INTERNAL_EXCEPTIONS;
        }

        $server = new \GraphQL\Server\StandardServer($serverConfig);

        $response = new JsonResponse();

        return $server->processPsrRequest($request, $response, $response->getBody());
    }
}
