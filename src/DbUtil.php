<?php

declare(strict_types=1);

namespace Zii\Util;

use Yii;
use yii\db\ColumnSchema;
use yii\db\Exception;
use yii\db\mysql\Schema;

class DbUtil
{
    public static function getTableComment(string $tb): ?string
    {
        $sql = "SHOW TABLE STATUS WHERE Name = '$tb'";
        $command = Yii::$app->db->createCommand($sql);

        try {
            $query = $command->queryOne();
        } catch (Exception $e) {
            $query = [];
        }

        if (isset($query['Comment'])) {
            return $query['Comment'];
        }

        return null;
    }

    public static function getTableIndexes(string $tb): array
    {
        $sql = 'SHOW INDEX FROM ' . Yii::$app->db->schema->getRawTableName($tb);
        $command = Yii::$app->db->createCommand($sql);

        try {
            return $command->queryAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 简单类型推断.
     * @param string $dbType
     * @return string
     */
    public static function castDataType(string $dbType): string
    {
        if (strpos($dbType, '(') === false) {
            return $dbType;
        }

        return explode('(', $dbType)[0];
    }

    /*
     * 获取列的最大值（只适用于数字类型）
     */
    public static function getColumnMaxValue(ColumnSchema $column): ?int
    {
        $dbType = self::castDataType($column->dbType);

        switch ($dbType) {
            case Schema::TYPE_TINYINT:
                $max = $column->size >= 3 ? 127 : str_repeat('9', $column->size);
                break;
            case Schema::TYPE_SMALLINT:
                $max = $column->size >= 5 ? 32767 : str_repeat('9', $column->size);
                break;
            case 'mediumint':
                $max = $column->size >= 7 ? 8388607 : str_repeat('9', $column->size);
                break;
            case 'int':
            case Schema::TYPE_INTEGER:
                $max = $column->size >= 10 ? 2147483647 : str_repeat('9', $column->size);
                break;
            case Schema::TYPE_BIGINT:
                $max = $column->size >= 19 ? 9223372036854775807 : str_repeat('9', $column->size);
                break;
            default:
                $max = null;
                break;
        }

        return (int)$max;
    }
}
