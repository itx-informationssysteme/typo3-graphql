<?php

namespace Itx\Typo3GraphQL\Domain\Repository;

use Itx\Typo3GraphQL\Domain\Model\Filter;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class FilterRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    protected $defaultOrderings = [
        'name' => QueryInterface::ORDER_ASCENDING,
    ];

    public function findAll(): QueryResultInterface
    {
        return $this->createQuery()->getQuerySettings()->setRespectStoragePage(false)->execute();
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
}
