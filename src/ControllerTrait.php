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

    /** @var callable|null  */
    public $beforeAuthorizationMethodResolved;

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
        $HttpAuthorization = self::extractAuthorizationToken_fromBearer();
        if ($HttpAuthorization !== null) {
            // call_user_func
            if (is_callable($this->beforeAuthorizationMethodResolved)) {
                call_user_func($this->beforeAuthorizationMethodResolved, HttpBearerAuth::class, $HttpAuthorization);
            }

            return [['class' => HttpBearerAuth::class]];
        }

        $QueryAccessToken = self::extractAuthorizationToken_fromQuery();
        if ($QueryAccessToken !== null) {
            // call_user_func
            if (is_callable($this->beforeAuthorizationMethodResolved)) {
                call_user_func($this->beforeAuthorizationMethodResolved, QueryParamAuth::class, $QueryAccessToken);
            }

            return [['class' => QueryParamAuth::class]];
        }

        return [];
    }

    public static function extractAuthorizationToken(): ?string
    {
        $HttpAuthorization = self::extractAuthorizationToken_fromBearer();
        if ($HttpAuthorization !== null) {
            return $HttpAuthorization;
        }

        $QueryAccessToken = self::extractAuthorizationToken_fromQuery();
        if ($QueryAccessToken !== null) {
            return $QueryAccessToken;
        }

        return null;
    }

    public static function extractAuthorizationToken_fromBearer(): ?string
    {
        $HttpAuthorization = Yii::$app->getRequest()->getHeaders()->get('Authorization', '');
        if (preg_match('/^Bearer\s+(.*?)$/', $HttpAuthorization, $matches) && !empty($matches[1])) {
            if (!empty(trim($matches[1]))) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    public static function extractAuthorizationToken_fromQuery(): ?string
    {
        $QueryAccessToken = Yii::$app->getRequest()->get('AccessToken', '');
        if (is_string($QueryAccessToken) && !empty(trim($QueryAccessToken))) {
            return trim($QueryAccessToken);
        }

        return null;
    }
}
