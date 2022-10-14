<?php

namespace Itx\Typo3GraphQL\EventListener;

class AfterTcaEventListener
{
    public function __construct()
    {

    }

    public function addGraphQLModelsToFilterRecord()
    {
        // TODO Implement
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
