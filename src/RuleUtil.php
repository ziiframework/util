<?php

declare(strict_types=1);

namespace Zii\Util;

use yii\helpers\Json;
use yii\validators\DateValidator;

class RuleUtil
{
    /**
     * 字符串值
     *
     * @param mixed $value
     * @return null|string
     */
    public static function strOrNull($value): ?string
    {
        if (is_numeric($value)) {
            $value = (string)$value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * JSON字符串值
     *
     * @param mixed $value
     * @return null|string
     */
    public static function strJsonFormat($value): ?string
    {
        if (is_array($value)) {
            return Json::encode($value);
        }
        if (is_string($value)) {
            $json = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return Json::encode($json);
            }
        }

        return null;
    }

    /**
     * 字符串值
     * @param mixed $value
     * @return null|string
     */
    public static function strWithoutTrim($value): ?string
    {
        if (is_numeric($value)) {
            $value = (string)$value;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * 数值
     * @param mixed $value
     * @return null|int
     */
    public static function intOrNull($value): ?int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }

    /**
     * 布尔值
     * @param mixed $value
     * @return null|bool
     */
    public static function boolOrNull($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (bool)($value * 1);
        }

        return null;
    }

    /**
     * 过滤日期值
     * @param $value
     * @param mixed $format
     * @return null|string
     */
    public static function dateOrNull($value, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            $validator = new DateValidator(['format' => "php:$format"]);
            if ($validator->validate($value)) {
                return $value;
            }

            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date($format, $timestamp);
            }
        }

        return null;
    }
}
