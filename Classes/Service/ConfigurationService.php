<?php

namespace Itx\Typo3GraphQL\Service;

use Itx\Typo3GraphQL\Domain\Model\Filter;
use RuntimeException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationService
{
    protected array $configuration;
    protected FrontendInterface $cache;

    protected const CONFIGURATION_FILE = 'Configuration/GraphQL.yaml';

    public function __construct(FrontendInterface $cache)
    {
        $this->cache = $cache;

        $cacheIdentifier = 'configuration';

        $value = $this->cache->get($cacheIdentifier);

        if ($value === false) {
            $value = $this::loadConfiguration();
            $tags = ['graphql'];
            $lifetime = 0;

            $this->cache->set($cacheIdentifier, $value, $tags, $lifetime);
        }

        $this->configuration = $value;
    }

    public static function loadConfiguration(): array
    {
        $configuration = [];

        $extensions = ExtensionManagementUtility::getLoadedExtensionListArray();

        /** @var YamlFileLoader $yamlFileLoader */
        $yamlFileLoader = GeneralUtility::makeInstance(YamlFileLoader::class);

        // Load all extensions configuration files
        foreach ($extensions as $extension) {
            // Check if the extension has a configuration file
            $configurationFile = ExtensionManagementUtility::extPath($extension) . self::CONFIGURATION_FILE;
            if (!file_exists($configurationFile)) {
                continue;
            }

            $newConfiguration = $yamlFileLoader->load(ExtensionManagementUtility::extPath($extension) . self::CONFIGURATION_FILE);

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

    /**
     * @param string $modelClassPath
     * @param string $filterType
     * @return array<Filter>
     * @throws RuntimeException
     */
    public function getFiltersForModel(string $modelClassPath, array $filterPaths, string $filterType): array
    {
        $filters = $this->configuration['models'][$modelClassPath]['filters'] ?? [];

        $filters = array_filter($filters, static fn(array $filter) => ($filter['type'] ?? '') === $filterType &&
            in_array(($filter['path'] ?? ''), $filterPaths, true));

        $result = [];
        foreach ($filters as $filter) {
            $filterModel = new Filter();
            $filterModel->setName($filter['name'] ?? '');
            if ($filter['type'] !== 'discrete' && $filter['type'] !== 'range') {
                throw new \RuntimeException("Filter type '$filterType' not supported");
            }

            if ($filter['path'] === '') {
                throw new \RuntimeException('Filer path is required');
            }

            $filterModel->setFilterPath($filter['path'] ?? '');
            $filterModel->setUnit($filter['unit'] ?? '');
            $filterModel->setModel($modelClassPath);

            $result[] = $filterModel;
        }

        return $result;
    }

    public function getSettings(): array
    {
        return $this->configuration['settings'] ?? [];
    }

    public function getMountPointsForModel(string $modelClassPath): array
    {
        return $this->configuration['models'][$modelClassPath]['mountPoints'] ?? [];
    }
}
