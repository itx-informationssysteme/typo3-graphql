<?php

namespace Itx\Typo3GraphQL\Middleware;

use Itx\Typo3GraphQL\Service\ConfigurationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CorsMiddleware implements MiddlewareInterface
{
    protected ConfigurationService $configurationService;

    protected array $headers = ['Access-Control-Allow-Methods' => 'POST, GET, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With'];

    protected array $allowedOrigins = [];

    public function __construct(ConfigurationService $configurationService)
    {
        $this->configurationService = $configurationService;

        $settings = $this->configurationService->getSettings();

        $additionalCORSOrigins = $settings['allowedCORSOrigins'] ?? [];

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();

        $hosts = array_map(static function ($site) {
            return $site->getBase()->__toString();
        }, $sites);

        $hosts = array_merge($hosts, $additionalCORSOrigins);

        $this->allowedOrigins = array_unique($hosts);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestMethod = $request->getMethod();

        // Only process requests with the POST and GET method
        if (!in_array($requestMethod, ['POST', 'GET', 'OPTIONS']) || $request->getUri()->getPath() !== '/graphql') {
            return $handler->handle($request);
        }

        $requestOrigin = $request->getHeader('Origin')[0] ?? '';

        if (!Environment::getContext()->isDevelopment()) {
            if (in_array($requestOrigin, $this->allowedOrigins, true)) {
                $this->headers['Access-Control-Allow-Origin'] = $requestOrigin;
            }
        } else {
            $this->headers['Access-Control-Allow-Origin'] = '*';
        }

        if ($requestMethod === 'OPTIONS') {
            return new JsonResponse(['status' => 'ok'], 200, $this->headers);
        }

        $response = $handler->handle($request);

        foreach ($this->headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response;
    }
}
