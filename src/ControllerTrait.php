<?php

declare(strict_types=1);

namespace Zii\Util;

use yii\base\InvalidConfigException;
use yii\base\Model;
use Yii;
use yii\filters\AccessRule;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;
use yii\helpers\ArrayHelper;

trait ControllerTrait
{
    public array $behaviorRules = [];

    protected ?Model $validatorObject = null;

    private function resolveBehaviorRules(string $module, string $controller, string $action): array
    {
        $rules = [];
        foreach ($this->behaviorRules as $key => $value) {
            if (is_int($key)) {
                if (!ArrayHelper::isAssociative($value, true)) {
                    throw new InvalidConfigException("value of $key must be associative");
                }
                $rule = $value;
            } else if (is_string($key)) {
                $rule = [
                    'class' => AccessRule::class,
                    'controllers' => ["$module/$controller"],
                    'actions' => [$key],
                    'allow' => true,
                    'roles' => (array)$value,
                ];
            } else {
                throw new InvalidConfigException("value of $key must be int or string");
            }

            $rules[] = $rule;
        }

        // 如果未定义当前action对应的访问权限，则抛出异常
        $islisted = false;
        foreach ($rules as $t_rule) {
            foreach ($t_rule['controllers'] as $rule_controller) {
                foreach ($t_rule['actions'] as $rule_action) {
                    if ("$rule_controller/$rule_action" === "$module/$controller/$action") {
                        $islisted = true;
                        break 3;
                    }
                }
            }
        }
        if (!$islisted) {
            throw new InvalidConfigException("current action $action does not listed in behavior rules");
        }

        return $rules;
    }

    private function resolveAuthorizationMethods(): array
    {
        $Authorization = Yii::$app->getRequest()->getHeaders()->get('Authorization', '');
        if (preg_match('/^Bearer\s+(.*?)$/', $Authorization, $matches) && !empty($matches[1])) {
            return [['class' => HttpBearerAuth::class]];
        }

        $AccessToken = Yii::$app->getRequest()->get('AccessToken', '');
        if (is_string($AccessToken) && !empty(trim($AccessToken))) {
            return [['class' => QueryParamAuth::class]];
        }

        return [];
    }
}
