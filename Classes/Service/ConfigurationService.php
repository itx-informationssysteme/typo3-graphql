<?php

namespace Itx\Typo3GraphQL\Service;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class ConfigurationService
{
    protected YamlFileLoader $yamlFileLoader;
    protected array $configuration;
    protected FrontendInterface $cache;

    protected const CONFIGURATION_FILE = 'Configuration/GraphQL.yaml';

    public function __construct(YamlFileLoader $yamlFileLoader, FrontendInterface $cache)
    {
        $this->yamlFileLoader = $yamlFileLoader;
        $this->cache = $cache;

        $cacheIdentifier = 'configuration';

        $value = $this->cache->get($cacheIdentifier);

        if ($value === false) {
            $value = $this->loadConfiguration();
            $tags = ['graphql'];
            $lifetime = 0;

            $this->cache->set($cacheIdentifier, $value, $tags, $lifetime);
        }

        $this->configuration = $value;
    }

    protected function loadConfiguration(): array
    {
        $configuration = [];

        $extensions = ExtensionManagementUtility::getLoadedExtensionListArray();

        // Load all extensions configuration files
        foreach ($extensions as $extension) {
            // Check if the extension has a configuration file
            $configurationFile = ExtensionManagementUtility::extPath($extension) . self::CONFIGURATION_FILE;
            if (!file_exists($configurationFile)) {
                continue;
            }

            $newConfiguration = $this->yamlFileLoader->load(ExtensionManagementUtility::extPath($extension) . self::CONFIGURATION_FILE);

            $configuration = self::array_merge_recursive_overwrite($configuration, $newConfiguration);
        }

        // Development settings override
        if (Environment::getContext()->isDevelopment()) {
            $configuration['settings'] = self::array_merge_recursive_overwrite($configuration['settings'] ?? [], $configuration['developmentSettingsOverrides'] ?? []);
        }

        return $configuration;
    }

    private static function array_merge_recursive_overwrite(array ...$arrays): array
    {
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
}
