<?php

declare(strict_types=1);

namespace Zii\Util\Supports;

use Yii;

class WebSupport
{
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
