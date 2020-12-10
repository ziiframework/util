<?php

declare(strict_types=1);

/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Zii\Util\Behaviors;

use yii\base\Behavior;
use yii\base\InvalidArgumentException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * PositionBehavior allows managing custom order for the records in the database.
 * Behavior uses the specific integer field of the database entity to set up position index.
 * Due to this the database entity, which the model refers to, must contain field {@see positionAttribute}.
 *
 * ```php
 * class Item extends ActiveRecord
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'positionBehavior' => [
 *                 'class' => PositionBehavior::className(),
 *                 'positionAttribute' => 'indexed_at',
 *             ],
 *         ];
 *     }
 * }
 * ```
 *
 * @property ActiveRecord $owner owner ActiveRecord instance.
 * @property bool $isFirst whether this record is the first in the list. This property is available since version 1.0.1.
 * @property bool $isLast whether this record is the last in the list. This property is available since version 1.0.1.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class ActiveRecordPositionBehavior extends Behavior
{
    /**
     * @var string name owner attribute, which will store position value.
     * This attribute should be an integer.
     */
    public string $positionAttribute = 'indexed_at';

    /**
     * @var array list of owner attribute names, which values split records into the groups,
     * which should have their own positioning.
     * Example: `['group_id', 'category_id']`
     */
    public array $groupAttributes = [];

    private array $_errors = [];

    public function getErrors(): array
    {
        return $this->_errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->_errors);
    }

    public function move_upstairs(): bool
    {
        $positionAttribute = $this->positionAttribute;

        $upstairs_Owner = $this->findRecordUsingOwnerPosition(1);

        if (!($upstairs_Owner instanceof ActiveRecord)) {
            $this->_errors[] = 'Cannot find upstairs Owner';
            return false;
        }

        // 交换位置：

        $affected_rows = $upstairs_Owner->updateAttributes([$positionAttribute => $this->owner->$positionAttribute]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move upstairs Owner failed, affected rows is $affected_rows";
            return false;
        }
        unset($affected_rows);

        $affected_rows = $this->owner->updateAttributes([$positionAttribute => $this->owner->$positionAttribute + 1]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move current Owner failed, affected rows is $affected_rows";
            return false;
        }
        unset($affected_rows);

        return true;
    }

    public function move_downstairs(): bool
    {
        $positionAttribute = $this->positionAttribute;

        $downstairs_Owner = $this->findRecordUsingOwnerPosition(-1);

        if (!($downstairs_Owner instanceof ActiveRecord)) {
            $this->_errors[] = 'Cannot find downstairs Owner';
            return false;
        }

        // 交换位置：

        $affected_rows = $downstairs_Owner->updateAttributes([$positionAttribute => $this->owner->$positionAttribute]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move downstairs Owner failed, affected rows is $affected_rows";
            return false;
        }
        unset($affected_rows);

        $affected_rows = $this->owner->updateAttributes([$positionAttribute => $this->owner->$positionAttribute - 1]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move current Owner failed, affected rows is $affected_rows";
            return false;
        }
        unset($affected_rows);

        return true;
    }

    public function move_roof(): bool
    {
        $positionAttribute = $this->positionAttribute;

        $roof_Owner = $this->findRecordUsingOwnerSort('DESC');

        if (!($roof_Owner instanceof ActiveRecord)) {
            $this->_errors[] = 'Cannot find roof Owner';
            return false;
        }

        $affected_rows = $this->owner->updateAttributes([$positionAttribute => $roof_Owner->$positionAttribute + 1]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move current Owner failed, affected rows is $affected_rows";
            return false;
        }
        unset($affected_rows);

        return true;
    }

    public function move_bottom(): bool
    {
        $positionAttribute = $this->positionAttribute;

        $bottom_Owner = $this->findRecordUsingOwnerSort('ASC');

        if (!($bottom_Owner instanceof ActiveRecord)) {
            $this->_errors[] = "Cannot find bottom Owner";
            return false;
        }

        $affected_rows = $this->owner->updateAttributes([$positionAttribute => $bottom_Owner->$positionAttribute - 1]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move current Owner failed, affected rows is $affected_rows";
            return false;
        }
        unset($affected_rows);

        return true;
    }

    private function createGroupCondition(): array
    {
        $condition = [];

        if (!empty($this->groupAttributes)) {
            foreach ($this->groupAttributes as $attribute) {
                $condition[$attribute] = $this->owner->$attribute;
            }
        }

        return $condition;
    }

    public function is_roof(): bool
    {
        $positionAttribute = $this->positionAttribute;

        $roof_Owner = $this->findRecordUsingOwnerSort('DESC');
        if (!($roof_Owner instanceof ActiveRecord)) {
            $this->_errors[] = "Cannot find roof Owner";
            return false;
        }

        $this_Owner_position = $this->owner->$positionAttribute;
        if (is_numeric($this_Owner_position)) {
            $this_Owner_position = (int)$this_Owner_position;
        }

        $roof_Owner_position = $roof_Owner->$positionAttribute;
        if (is_numeric($roof_Owner_position)) {
            $roof_Owner_position = (int)$roof_Owner_position;
        }

        return $this_Owner_position === $roof_Owner_position;
    }

    public function is_bottom(): bool
    {
        $positionAttribute = $this->positionAttribute;

        $bottom_Owner = $this->findRecordUsingOwnerSort('ASC');
        if ($bottom_Owner === null) {
            $this->_errors[] = "Cannot find bottom Owner";
            return false;
        }

        $this_Owner_position = $this->owner->$positionAttribute;
        if (is_numeric($this_Owner_position)) {
            $this_Owner_position = (int)$this_Owner_position;
        }

        $bottom_Owner_position = $bottom_Owner->$positionAttribute;
        if (is_numeric($bottom_Owner_position)) {
            $bottom_Owner_position = (int)$bottom_Owner_position;
        }

        return $this_Owner_position === $bottom_Owner_position;
    }

    private function createGroupQuery(): ActiveQuery
    {
        $query = $this->owner->find();

        $group_condition = $this->createGroupCondition();
        if (!empty($group_condition)) {
            $query->andWhere($group_condition);
        }

        return $query;
    }

    private function findRecordUsingOwnerPosition(int $step): ?ActiveRecord
    {
        $positionAttribute = $this->positionAttribute;

        $query = $this->createGroupQuery();

        if ($step > 0) {
            $query->andWhere(['>', $positionAttribute, $this->owner->$positionAttribute]);
        } else if ($step < 0) {
            $query->andWhere(['<', $positionAttribute, $this->owner->$positionAttribute]);
        } else {
            throw new InvalidArgumentException('Step cannot eq 0');
        }

        if ($step === 1) {
            $query->orderBy("$positionAttribute ASC");
        } else if ($step === -1) {
            $query->orderBy("$positionAttribute DESC");
        } else {
            // TODO test
            $query->orderBy(new Expression("ABS($positionAttribute - $step) DESC")); // find furthest
        }

        return $query->limit(1)->one();
    }

    private function findRecordUsingOwnerSort(string $sort): ?ActiveRecord
    {
        $query = $this->createGroupQuery();

        $query->orderBy("{$this->positionAttribute} {$sort}");

        return $query->limit(1)->one();
    }

//    private function findScalarUsingOwnerSort(string $sort): ?int
//    {
//        $query = (new Query())->from($this->owner->tableName());
//
//        if (!empty($this->createGroupCondition())) {
//            $query->andWhere($this->createGroupCondition());
//        }
//
//        $query->orderBy("{$this->positionAttribute} {$sort}");
//
//
//        $scalar = $query->select($this->positionAttribute)->scalar();
//
//        return is_numeric($scalar) ? (int)$scalar : null;
//    }

    public function find_upstairs(): ?ActiveRecord
    {
        if ($this->is_roof()) {
            return null;
        }

        return $this->findRecordUsingOwnerPosition(1);
    }

    public function find_downstairs(): ?ActiveRecord
    {
        if ($this->is_bottom()) {
            return null;
        }

        return $this->findRecordUsingOwnerPosition(-1);
    }

    public function find_bottom(): ?ActiveRecord
    {
        return $this->findRecordUsingOwnerSort('ASC');
    }

    public function find_roof(): ?ActiveRecord
    {
        return $this->findRecordUsingOwnerSort('DESC');
    }
}
