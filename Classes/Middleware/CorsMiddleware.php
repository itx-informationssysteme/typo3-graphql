<?php

namespace Itx\Typo3GraphQL\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    protected ConfigurationManagerInterface $configurationManager;

    protected array $headers = ['Access-Control-Allow-Methods' => 'POST, GET, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With'];

    protected array $allowedOrigins = [];

    /**
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    public function __construct(ConfigurationManager $configurationManager) {
        $this->configurationManager = $configurationManager;

        $configuration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK, 'typo3_graphql');

        $additionalCORSOriginsString = trim($configuration['settings']['allowedCORSOrigins'] ?? '');
        $additionalCORSOrigins = GeneralUtility::trimExplode(',', $additionalCORSOriginsString, true);

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();

        $hosts = array_map(static function($site) {
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
