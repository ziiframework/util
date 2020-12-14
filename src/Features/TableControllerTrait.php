<?php

declare(strict_types=1);

namespace Zii\Util\Features;

use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\Sort;
use yii\db\ActiveQueryInterface;

trait TableControllerTrait
{
    use StatelessControllerTrait;

    protected function verbs(): array
    {
        $verbs = parent::verbs();

        foreach ($verbs as $action => $methods) {
            if (is_array($methods) && !in_array('OPTIONS', $methods, true)) {
                $verbs[$action][] = 'OPTIONS';
            }
        }

        return $verbs;
    }

    public function actions(): array
    {
        $actions = parent::actions();

        /** @see IndexAction::$dataFilter */
        $actions['index']['dataFilter'] = $this->dataFilterCallback();

        /** @see IndexAction::$prepareDataProvider */
        $actions['index']['prepareDataProvider'] = $this->prepareDataProviderCallback();

        return $actions;
    }

    protected function dataFilterCallback(): ?array
    {
        return null;
    }

    protected function prepareDataProviderCallback(): ?callable
    {
        return null;
    }

    public function checkAccess($action, $model = null, $params = []): void
    {
        parent::checkAccess($action, $model, $params);
    }

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
