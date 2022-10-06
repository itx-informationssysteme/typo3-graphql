<?php

namespace Itx\Typo3GraphQL\Services;

use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class ConfigurationService
{
    protected YamlFileLoader $yamlFileLoader;
    protected array $configuration;

    protected const CONFIGURATION_FILE = 'Configuration/GraphQL.yaml';

    public function __construct(YamlFileLoader $yamlFileLoader)
    {
        $this->yamlFileLoader = $yamlFileLoader;
        $this->loadConfiguration();
    }

    protected function loadConfiguration(): void
    {
        $extensions = ExtensionManagementUtility::getLoadedExtensionListArray();

        $this->configuration = $this->yamlFileLoader->load(ExtensionManagementUtility::extPath('typo3_graphql') . 'Configuration/GraphQL.yaml');

        // Load all other extensions
        foreach ($extensions as $extension) {
            if ($extension === 'typo3_graphql') {
                continue;
            }

            // Check if the extension has a configuration file
            $configurationFile = ExtensionManagementUtility::extPath($extension) . self::CONFIGURATION_FILE;
            if (!file_exists($configurationFile)) {
                continue;
            }

            $configuration = $this->yamlFileLoader->load(ExtensionManagementUtility::extPath($extension) . self::CONFIGURATION_FILE);

            $this->configuration = self::array_merge_recursive_overwrite($this->configuration, $configuration);
        }

        // Development settings override
        if (Environment::getContext()->isDevelopment()) {
            $this->configuration['settings'] = self::array_merge_recursive_overwrite($this->configuration['settings'], $this->configuration['developmentSettingsOverrides'] ?? []);
        }
    }

    private static function array_merge_recursive_overwrite(array ...$arrays) : array {
        $merged = [];
        foreach ($arrays as $current) {
            foreach ($current as $key => $value) {
                if (is_string($key)) {
                    if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                        $merged[$key] = self::array_merge_recursive_overwrite($merged[$key], $value);
                    } else {
                        $merged[$key] = $value;
                    }
                } else {
                    $merged[] = $value;
                }
            }
        }

        return $merged;
    }

    public function getModels(): array
    {
        return $this->configuration['models'] ?? [];
    }

    public function getSettings(): array
    {
        return $this->configuration['settings'] ?? [];
    }

    public function getGlobalDisabledFields(): array
    {
        return $this->configuration['globalDisabledFields'] ?? [];
    }
}
