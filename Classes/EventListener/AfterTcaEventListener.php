<?php

namespace Itx\Typo3GraphQL\EventListener;

use Itx\Typo3GraphQL\Service\ConfigurationService;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;

class AfterTcaEventListener
{
    protected ConfigurationService $configurationService;

    public function __construct(ConfigurationService $configurationService)
    {
        $this->configurationService = $configurationService;
    }

    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $tca = $event->getTca();

        $items = [];
        foreach ($this->configurationService->getModels() as $model => $modelConfiguration) {
            if ($modelConfiguration['enabled'] === false) {
                continue;
            }

            $splitModel = explode('\\', $model);

            $items[] = [$splitModel[array_key_last($splitModel)], $model];
        }

        $tca['tx_typo3graphql_domain_model_filter']['columns']['model']['config']['items'] = $items;

        $event->setTca($tca);
    }
}
