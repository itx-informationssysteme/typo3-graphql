<?php

namespace Itx\Typo3GraphQL\Domain\Repository;

use Itx\Typo3GraphQL\Domain\Model\Filter;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class FilterRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    protected $defaultOrderings = [
        'name' => QueryInterface::ORDER_ASCENDING,
    ];

    /** @var string[]  */
    protected array $filterBuffer = [];

    public function findAll(): QueryResultInterface
    {
        $query = $this->createQuery();

        $query->getQuerySettings()->setRespectStoragePage(false);

        return $query->execute();
    }

    /**
     * @return Filter[]|QueryResultInterface
     */
    public function findByModel($model): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('model', $model));

        return $query->execute();
    }

    private function bufferKey(string $model, string $filter, string $type): string
    {
        return $model . $filter . $type;
    }

    /**
     * @return Filter[]|QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findByModelAndPathsAndType($model, array $paths, string $type): QueryResultInterface|array
    {
        $filters = [];

        // Check if we have a cached result
        foreach ($paths as $path) {
            $key = $this->bufferKey($model, $path, $type);
            if (isset($this->filterBuffer[$key])) {
                $filters[] = $this->filterBuffer[$key];
            }
        }

        if (count($filters) === count($paths)) {
            return $filters;
        }

        $query = $this->createQuery();

        // TODO Language overlay?
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->logicalAnd($query->equals('model', $model), $query->in('filter_path', $paths), $query->equals('type_of_filter', $type)));

        $results = $query->execute();

        foreach ($results as $result) {
            $this->filterBuffer[$this->bufferKey($model, $result->getFilterPath(), $type)] = $result;
        }

        return $results;
    }
}
