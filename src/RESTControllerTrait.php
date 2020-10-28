<?php

declare(strict_types=1);

namespace Zii\Util;

use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\Sort;
use yii\db\ActiveQueryInterface;

trait RESTControllerTrait
{
    use ControllerTrait;

    protected function getActiveDataProviderConfig(ActiveQueryInterface $query, array $query_sort = []): ActiveDataProvider
    {
        if (empty($query_sort)) {
            $query_sort = ['id' => SORT_DESC];
        }

        /** @var ActiveDataProvider $providerConfig */
        $providerConfig = Yii::createObject([
            'class' => ActiveDataProvider::class,
            'query' => $query,
            'pagination' => [
                'class' => Pagination::class,
                'params' => Yii::$app->getRequest()->getQueryParams(),
                'pageParam' => 'qpage',
                'pageSizeParam' => 'qpagesize',
                'pageSizeLimit' => [1, 20],
            ],
            'sort' => [
                'class' => Sort::class,
                'params' => Yii::$app->getRequest()->getQueryParams(),
                'sortParam' => 'qsort',
                'enableMultiSort' => true,
                'defaultOrder' => $query_sort,
            ],
        ]);

        return $providerConfig;
    }
}
