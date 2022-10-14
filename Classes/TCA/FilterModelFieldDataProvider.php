<?php

namespace Itx\Typo3GraphQL\TCA;

use TYPO3\CMS\Backend\Form\FormDataProvider\AbstractItemProvider;
use TYPO3\CMS\Extbase\Object\Exception;

class FilterModelFieldDataProvider
{
    // Get all configured models from typoscript
    /**
     * @throws Exception
     */
    public function getItems(array $config, AbstractItemProvider $abstractItemProvider): array {
        // Initialize ObjectManager and get ConfigurationService
        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        $configurationService = $objectManager->get(\Itx\Typo3GraphQL\Service\ConfigurationService::class);

        $config['items'] = [];
        foreach ($configurationService->getModels() as $model => $modelConfiguration) {
            if ($modelConfiguration['enabled'] === false) {
                continue;
            }

            $splitModel = explode('\\', $model);

            $config['items'][] = [$splitModel[array_key_last($splitModel)], $model];
        }

        return $config;
    }
}
