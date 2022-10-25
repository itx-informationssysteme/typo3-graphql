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

    /**
     * @return Filter[]|QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findByModelAndPaths($model, array $paths): QueryResultInterface|array
    {
        $query = $this->createQuery();

        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->logicalAnd($query->equals('model', $model), $query->in('filter_path', $paths)));

        return $query->execute();
    }
}
