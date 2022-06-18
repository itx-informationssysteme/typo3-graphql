<?php

namespace Itx\Typo3GraphQL\Middleware;

use GeorgRinger\News\Domain\Model\News;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Itx\Typo3GraphQL\Schema\SchemaGenerator;
use JsonException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Persistence\ClassesConfiguration;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

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
                                                     ]);

        $response = new JsonResponse();

        return $server->processPsrRequest($request, $response, $response->getBody());
    }
}
