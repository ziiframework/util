<?php

declare(strict_types=1);

namespace Zii\Util\Features;

use yii\base\InvalidConfigException;
use yii\base\Model;
use Yii;
use yii\filters\AccessRule;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;
use yii\helpers\ArrayHelper;
use Zii\Util\Supports\WebSupport;

trait ControllerTrait
{
    public array $behaviorRules = [];

    protected ?Model $validator = null;

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

        // 如果未定义当前action对应的访问权限，则抛出异常（但需要排除Options请求）
        $t_is_listed = false;
        foreach ($rules as $t_rule) {
            foreach ($t_rule['controllers'] as $rule_controller) {
                foreach ($t_rule['actions'] as $rule_action) {
                    if ("$rule_controller/$rule_action" === "$module/$controller/$action") {
                        $t_is_listed = true;
                        break 3;
                    }
                }
            }
        }

        if (!$t_is_listed && !Yii::$app->getRequest()->getIsOptions()) {
            throw new InvalidConfigException("Current action [$action] does not listed in behavior rules");
        }

        // TODO 检测空数组，如 'test_action' => []

        return $rules;
    }

    private function resolveAuthorizationMethods(): array
    {
        $HttpAuthorization = WebSupport::extractAuthorizationToken_fromBearer();
        if ($HttpAuthorization !== null) {
            if (method_exists($this, 'beforeAuthorizationMethodResolved')) {
                return $this->beforeAuthorizationMethodResolved(HttpBearerAuth::class, $HttpAuthorization);
            }

            return [['class' => HttpBearerAuth::class]];
        }

        $QueryAccessToken = WebSupport::extractAuthorizationToken_fromQuery();
        if ($QueryAccessToken !== null) {
            if (method_exists($this, 'beforeAuthorizationMethodResolved')) {
                return $this->beforeAuthorizationMethodResolved(QueryParamAuth::class, $QueryAccessToken);
            }

            return [['class' => QueryParamAuth::class, 'tokenParam' => 'AccessToken']];
        }

        return [];
    }
}
