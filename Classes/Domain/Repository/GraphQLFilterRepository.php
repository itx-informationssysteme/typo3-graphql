<?php

namespace Itx\Typo3GraphQL\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class GraphQLFilterRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    protected $defaultOrderings = [
        'name' => QueryInterface::ORDER_ASCENDING,
    ];

    public function findAll(): QueryResultInterface
    {
        return $this->createQuery()->getQuerySettings()->setRespectStoragePage(false)->execute();
    }

    public function findByModel($model): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('model', $model));
        return $query->execute();
    }
}
