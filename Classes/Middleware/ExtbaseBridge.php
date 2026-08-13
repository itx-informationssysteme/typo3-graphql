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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\Page\RootLineException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScriptFactory;
use TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Core\Bootstrap;

/**
 * Sets up TypoScript and Extbase, in order to use the Extbase persistence layer within the GraphQL resolvers.
 */
class ExtbaseBridge
{
    public function __construct(
        protected readonly Context $context,
        protected readonly FrontendTypoScriptFactory $frontendTypoScriptFactory,
        protected readonly SysTemplateRepository $sysTemplateRepository,
        #[Autowire(service: 'cache.typoscript')]
        protected readonly PhpFrontend $typoScriptCache,
    ) {}

    public function boot(ServerRequestInterface $request): ServerRequestInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $request;
        }

        if (!$request->getAttribute('frontend.typoscript') instanceof FrontendTypoScript) {
            $request = $this->initializeTypoScript($request, $site);
        }

        if (isset($GLOBALS['TSFE'])) {
            $GLOBALS['TSFE']->id = $site->getRootPageId();
        }

        $GLOBALS['TYPO3_REQUEST'] = $request;

        $this->bootExtbase($request);

        return $request;
    }

    protected function initializeTypoScript(ServerRequestInterface $request, Site $site): ServerRequestInterface
    {
        $pageId = $site->getRootPageId();

        $rootLine = $this->getRootLine($pageId);
        $sysTemplateRows = $this->getSysTemplateRows($request, $site, $rootLine);

        $fullRootLine = $rootLine;
        ksort($fullRootLine);

        $conditionMatcherVariables = [
            'request' => $request,
            'pageId' => $pageId,
            'page' => $rootLine[array_key_first($rootLine)] ?? [],
            'fullRootLine' => $fullRootLine,
            'localRootLine' => $this->getLocalRootLine($site, $rootLine, $sysTemplateRows),
            'site' => $site,
            'siteLanguage' => $request->getAttribute('language', $site->getDefaultLanguage()),
        ];

        $frontendTypoScript = $this->frontendTypoScriptFactory->createSettingsAndSetupConditions(
            $site,
            $sysTemplateRows,
            $conditionMatcherVariables,
            $this->typoScriptCache
        );

        // The GraphQL endpoint always needs the full setup array, there is no "fully cached page" shortcut here.
        $frontendTypoScript = $this->frontendTypoScriptFactory->createSetupConfigOrFullSetup(
            true,
            $frontendTypoScript,
            $site,
            $sysTemplateRows,
            $conditionMatcherVariables,
            '0',
            $this->typoScriptCache,
            $request
        );

        return $request->withAttribute('frontend.typoscript', $frontendTypoScript);
    }

    protected function getRootLine(int $pageId): array
    {
        try {
            return GeneralUtility::makeInstance(RootlineUtility::class, $pageId, '', $this->context)->get();
        } catch (RootLineException) {
            return [];
        }
    }

    protected function getSysTemplateRows(ServerRequestInterface $request, Site $site, array $rootLine): array
    {
        if ($rootLine === []) {
            return [];
        }

        if ($site->isTypoScriptRoot()) {
            $rootLineUntilSite = [];
            foreach ($rootLine as $index => $rootLinePage) {
                $rootLineUntilSite[$index] = $rootLinePage;
                if ((int)($rootLinePage['uid'] ?? 0) === $site->getRootPageId()) {
                    break;
                }
            }
            $rootLine = $rootLineUntilSite;
        }

        return $this->sysTemplateRepository->getSysTemplateRowsByRootline($rootLine, $request);
    }

    protected function getLocalRootLine(Site $site, array $rootLine, array $sysTemplateRows): array
    {
        $sysTemplateRowsIndexedByPid = array_combine(array_column($sysTemplateRows, 'pid'), $sysTemplateRows);
        $localRootLine = [];
        foreach ($rootLine as $rootLinePage) {
            array_unshift($localRootLine, $rootLinePage);
            $pageId = (int)($rootLinePage['uid'] ?? 0);
            if ($pageId === $site->getRootPageId() && $site->isTypoScriptRoot()) {
                break;
            }
            if ($pageId > 0 && (int)($sysTemplateRowsIndexedByPid[$pageId]['root'] ?? 0) === 1) {
                break;
            }
        }

        return $localRootLine;
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
