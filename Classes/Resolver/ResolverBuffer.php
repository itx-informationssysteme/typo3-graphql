<?php

namespace Itx\Typo3GraphQL\Resolver;

use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class ResolverBuffer
{
    protected PersistenceManager $persistenceManager;

    // Contains modelClassPath => [$languageID => [$id => $model|null]]
    protected array $buffer = [];

    // Contains modelClassPath => [$languageID => isLoaded]
    protected array $bufferConfiguration = [];

    public function __construct(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function add(string $modelClassPath, int $id, int $languageID): void
    {
        if (!isset($this->buffer[$modelClassPath])) {
            $this->buffer[$modelClassPath] = [];
        }

        if (!isset($this->buffer[$modelClassPath][$languageID])) {
            $this->buffer[$modelClassPath][$languageID] = [];
        }

        $this->buffer[$modelClassPath][$languageID][$id] = null;
    }

    /**
     * @throws InvalidQueryException
     */
    public function loadBuffered(string $modelClassPath, int $languageID): void
    {
        // If the buffer is already loaded, do nothing
        if ($this->bufferConfiguration[$modelClassPath][$languageID] ?? false) {
            return;
        }

        // If the buffer is empty, do nothing
        if (!isset($this->buffer[$modelClassPath][$languageID])) {
            return;
        }

        // If the buffer is not empty, load it
        $query = $this->persistenceManager->createQueryForType($modelClassPath);
        $query->getQuerySettings()
              ->setRespectStoragePage(false)
              ->setLanguageUid($languageID)
              ->setLanguageOverlayMode(true);

        $query->matching($query->in('uid', array_keys($this->buffer[$modelClassPath][$languageID])));
        $result = $query->execute(true);

        foreach ($result as $model) {
            $this->buffer[$modelClassPath][$languageID][$model['uid']] = $model;
        }

        $this->bufferConfiguration[$modelClassPath][$languageID] = true;
    }

    public function get(string $modelClassPath, int $id, int $languageID): mixed
    {
        if (!($this->bufferConfiguration[$modelClassPath] ?? false)) {
            throw new \RuntimeException("Buffer for $modelClassPath is not loaded yet!");
        }

        return $this->buffer[$modelClassPath][$languageID][$id];
    }
}
