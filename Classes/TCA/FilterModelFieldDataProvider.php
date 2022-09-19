<?php

namespace Itx\Typo3GraphQL\TCA;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;

class FilterModelFieldDataProvider
{
    // Get all configured models from typoscript
    /**
     * @throws InvalidConfigurationTypeException
     */
    public function getItems(array $config): array {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configuration = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK, 'typo3_graphql');

        $config['items'] = [];
        foreach ($configuration['models'] as $model => $modelConfiguration) {
            if ($modelConfiguration['enabled'] === '0') {
                continue;
            }

            $splitModel = explode('\\', $model);

            $config['items'][] = [$splitModel[array_key_last($splitModel)], $model];
        }

        return $config;
    }
}
