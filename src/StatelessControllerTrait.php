<?php

declare(strict_types=1);

namespace Zii\Util;

use Yii;
use yii\base\Model;
use yii\base\UnknownClassException;
use yii\filters\AccessControl;
use yii\filters\Cors;
use yii\helpers\Inflector;
use yii\web\Response;

trait StatelessControllerTrait
{
    use ControllerTrait;

    protected function prepareValidatorObject(): void
    {
        $php_url_path = parse_url(Yii::$app->request->getAbsoluteUrl(), PHP_URL_PATH);
        $php_url_part = explode('/', $php_url_path);

        if (isset($php_url_part[3]) && !isset($this->actions()[$php_url_part[3]])) {
            $validatorClass = "\\app\\modules\\{$this->module->id}\\models\\" . Inflector::id2camel($this->id) . 'Validator';
            if (!class_exists($validatorClass)) {
                throw new UnknownClassException("Class $validatorClass does not exist");
            }

            $validatorObject = Yii::createObject($validatorClass);

            /** @var Model $validatorObject */
            $this->validatorObject = $validatorObject;
            $this->validatorObject->setScenario(Inflector::id2camel($php_url_part[3]));
            $this->validatorObject->setAttributes(Yii::$app->request->get());
            if (Yii::$app->request->getIsPost()) {
                $this->validatorObject->setAttributes(Yii::$app->request->post());
            }
        }
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['cors'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => [$_SERVER['HTTP_ORIGIN'] ?? '*'],
                'Access-Control-Request-Method' => ['GET', 'HEAD', 'OPTIONS', 'POST', 'PUT', 'DELETE', 'PATCH'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => true,
                'Access-Control-Max-Age' => 86400,
                'Access-Control-Expose-Headers' => [
                    'X-Pagination-Current-Page',
                    'X-Pagination-Total-Count',
                    'X-Pagination-Per-Page',
                    'X-Pagination-Page-Count',
                ],
            ],
        ];

        // make sure put `authenticator` behavior after `cors` behavior
        // see issue: https://github.com/yiisoft/yii2/issues/14754
        if (isset($behaviors['authenticator'])) {
            $original_authenticator_settings = $behaviors['authenticator'];
            unset($behaviors['authenticator']);
            $behaviors['authenticator'] = $original_authenticator_settings;
        }

        $behaviors['authenticator']['authMethods'] = $this->resolveAuthorizationMethods();
        $behaviors['authenticator']['except'] = ['options'];

        $behaviors['contentNegotiator']['formats'] = [
            'application/json' => Response::FORMAT_JSON,
            'text/json' => Response::FORMAT_JSON,
            'application/json;charset=utf-8' => Response::FORMAT_JSON,
            'application/json; charset=utf-8' => Response::FORMAT_JSON,
            'application/json;charset=UTF-8' => Response::FORMAT_JSON,
            'application/json; charset=UTF-8' => Response::FORMAT_JSON,
        ];

        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => $this->resolveBehaviorRules($this->module->id, $this->id, $this->action->id),
        ];

        return $behaviors;
    }

    // Note: beforeAction()先后两次执行，behaviors()中间执行
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // for TableControllerTrait checkAccess()
        if (method_exists($this, 'checkAccess')) {
            if (!isset($this->actions()[$action->id])) {
                $this->checkAccess($action->id);
            }
        }

        $this->prepareValidatorObject();
        if (($this->validatorObject instanceof Model) && (!$this->validatorObject->validate())) {
            Yii::$app->response->data = ['err' => $this->validatorObject->getErrors()];
            return false;
        }

        return true;
    }
}
