<?php

namespace Itx\Typo3GraphQL\TCA;

use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;

class FilterModelFieldDataProvider
{
    // Get all configured models from typoscript
    /**
     * @throws InvalidConfigurationTypeException
     */
    public function getItems(array $config): array {
        throw new \Exception('This method is not implemented yet');

        $config['items'] = [];
        foreach ($configuration['models'] as $model => $modelConfiguration) {
            if ($modelConfiguration['enabled'] === false) {
                continue;
            }

            $splitModel = explode('\\', $model);

            $config['items'][] = [$splitModel[array_key_last($splitModel)], $model];
        }

        return $config;
    }
}
