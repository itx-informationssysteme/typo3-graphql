<?php

declare(strict_types=1);

namespace Itx\Typo3GraphQL\Middleware;

/*
 * This file is part of TYPO3 CMS-based extension "SlimPHP Bridge" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Sets up TSFE and Extbase, in order to use Extbase within a Slim Controller
 */
class ExtbaseBridge
{
    public function boot(ServerRequestInterface $request, RequestHandlerInterface $handler): void
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return;
        }

        if (($GLOBALS['TYPO3_REQUEST'] ?? null) === null) {
            $GLOBALS['TYPO3_REQUEST'] = $request;
        }

        if (isset($GLOBALS['TSFE'])) {
            $GLOBALS['TSFE']->id = $site->getRootPageId();
        }

        $this->bootExtbase($request);
    }

    protected function bootExtbase(ServerRequestInterface $request): void
    {
        GeneralUtility::makeInstance(Bootstrap::class)->initialize([
            'extensionName' => 'typo3_graphql',
            'vendorName' => 'Itx',
            'pluginName' => 'graphql',
        ], $request);
    }
}
