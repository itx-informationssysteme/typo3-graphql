<?php

namespace Itx\Typo3GraphQL\Resolver;

use Itx\Typo3GraphQL\Service\ConfigurationService;
use Itx\Typo3GraphQL\Types\TypeRegistry;

class ResolverContext
{
    protected ConfigurationService $configurationService;

    protected TypeRegistry $typeRegistry;

    public function __construct(ConfigurationService $configurationService, TypeRegistry $typeRegistry)
    {
        $this->configurationService = $configurationService;
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @return ConfigurationService
     */
    public function getConfigurationService(): ConfigurationService
    {
        return $this->configurationService;
    }

    /**
     * @param ConfigurationService $configurationService
     */
    public function setConfigurationService(ConfigurationService $configurationService): void
    {
        $this->configurationService = $configurationService;
    }

    /**
     * @return TypeRegistry
     */
    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry;
    }

    /**
     * @param TypeRegistry $typeRegistry
     */
    public function setTypeRegistry(TypeRegistry $typeRegistry): void
    {
        $this->typeRegistry = $typeRegistry;
    }
}
