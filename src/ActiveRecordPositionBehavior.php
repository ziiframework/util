<?php

declare(strict_types=1);

/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Zii\Util;

use yii\base\Behavior;
use yii\base\InvalidArgumentException;
use yii\base\ModelEvent;
use yii\base\NotSupportedException;
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

    /**
     * @var int|null position value, which should be applied to the model on its save.
     * Internal usage only.
     */
    private ?int $positionOnSave = null; // temp value before event update


    private array $_errors = [];

    public function getErrors(): array
    {
        return $this->_errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->_errors);
    }

    /**
     * Moves owner record by one position towards the start of the list.
     * @return bool movement successful.
     */
    public function move_upstairs(): bool
    {
        $positionAttribute = $this->positionAttribute;

        $upstairs_Owner = $this->findRecordUsingOwnerPosition(1);

        if (!($upstairs_Owner instanceof ActiveRecord)) {
            $this->_errors[] = 'Cannot find upstairs Owner';
            return false;
        }

        $affected_rows = $upstairs_Owner->updateAttributes([$positionAttribute => $this->owner->$positionAttribute]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move upstairs Owner failed, affected rows is $affected_rows";
            return false;
        }

        $affected_rows = $this->owner->updateAttributes([$positionAttribute => $this->owner->$positionAttribute + 1]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move current Owner failed, affected rows is $affected_rows";
            return false;
        }

        return true;
    }

    /**
     * Moves owner record by one position towards the end of the list.
     * @return bool movement successful.
     */
    public function move_downstairs(): bool
    {
        $positionAttribute = $this->positionAttribute;

        $downstairs_Owner = $this->findRecordUsingOwnerPosition(-1);

        if (!($downstairs_Owner instanceof ActiveRecord)) {
            $this->_errors[] = 'Cannot find downstairs Owner';
            return false;
        }

        $affected_rows = $downstairs_Owner->updateAttributes([$positionAttribute => $this->owner->$positionAttribute]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move downstairs Owner failed, affected rows is $affected_rows";
            return false;
        }

        $affected_rows = $this->owner->updateAttributes([$positionAttribute => $this->owner->$positionAttribute - 1]);
        if ($affected_rows !== 1) {
            $this->_errors[] = "Move current Owner failed, affected rows is $affected_rows";
            return false;
        }

        return true;
    }

    /**
     * Moves owner record to the start of the list.
     * @return bool movement successful.
     */
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

        return true;
    }

    /**
     * Moves owner record to the end of the list.
     * @return bool movement successful.
     */
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

        return true;
    }

    /**
     * Moves owner record to the specific position.
     * If specified position exceeds the total number of records,
     * owner will be moved to the end of the list.
     * @param int $position number of the new position.
     * @return bool movement successful.
     */
    public function moveToPosition(int $position): bool
    {
        throw new NotSupportedException('TODO');
        if ($position < 1) {
            $this->_errors[] = "Cannot set position < 1";
            return false;
        }

        $positionAttribute = $this->positionAttribute;

        $oldRecord = $this->owner->findOne($this->owner->getPrimaryKey());
        if (!($oldRecord instanceof ActiveRecord)) {
            $this->_errors[] = "Cannot find old Owner";
            return false;
        }

        $oldRecordPosition = $oldRecord->$positionAttribute;

        if ($oldRecordPosition === $position || $oldRecordPosition === ((string)$position)) {
            return true;
        }

        if ($position < $oldRecordPosition) {
            // Move Up:

            $affected_rows = $this->owner->updateAllCounters(
                [$positionAttribute => 1], // +1
                [
                    'and',
                    $this->createGroupConditionAttributes(),
                    ['>=', $positionAttribute, $position],
                    ['<', $positionAttribute, $oldRecord->$positionAttribute],
                ]
            );
            if ($affected_rows === 0) {
                $this->_errors[] = "Move-Up other Owners failed, affected rows is $affected_rows";
                return false;
            }

            $affected_rows = $this->owner->updateAttributes([$positionAttribute => $position]);
            if ($affected_rows !== 1) {
                $this->_errors[] = "Move current Owner failed, affected rows is $affected_rows";
                return false;
            }
        } else {
            // Move Down:

            $last_Owner = $this->findLast();
            if ($last_Owner === null) {
                $this->_errors[] = "Cannot find last Owner";
                return false;
            }

            $last_Owner_position = $last_Owner->$positionAttribute;

            if ($position >= $last_Owner_position) {
                return $this->move_bottom();
            }

            $affected_rows = $this->owner->updateAllCounters(
                [$positionAttribute => -1],
                [
                    'and',
                    $this->createGroupConditionAttributes(),
                    ['>', $positionAttribute, $oldRecord->$positionAttribute],
                    ['<=', $positionAttribute, $position],
                ]
            );
            if ($affected_rows === 0) {
                $this->_errors[] = "Move-Down other Owners failed, affected rows is $affected_rows";
                return false;
            }

            $affected_rows = $this->owner->updateAttributes([$positionAttribute => $position]);
            if ($affected_rows !== 1) {
                $this->_errors[] = "Move current Owner failed, affected rows is $affected_rows";
                return false;
            }
        }

        return true;
    }

    /**
     * Creates array of group attributes with their values.
     * @see groupAttributes
     * @return array attribute conditions.
     */
    protected function createGroupConditionAttributes(): array
    {
        $condition = [];

        if (!empty($this->groupAttributes)) {
            foreach ($this->groupAttributes as $attribute) {
                $condition[$attribute] = $this->owner->$attribute;
            }
        }

        return $condition;
    }

    /**
     * Finds the number of records which belongs to the group of the owner.
     * @see groupAttributes
     * @return int records count.
     */
    protected function countGroupRecords(): int
    {
        return $this->createGroupQuery()->count();
    }

    /**
     * Checks whether this record is the first in the list.
     * @return bool whether this record is the first in the list.
     * @since 1.0.1
     */
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

    /**
     * Checks whether this record is the the last in the list.
     * Note: each invocation of this method causes a DB query execution.
     * @return bool whether this record is the last in the list.
     * @since 1.0.1
     */
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

        if (!empty($this->groupAttributes)) {
            $query->andWhere($this->createGroupConditionAttributes());
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

    /**
     * Finds record previous to this one.
     * @return ActiveRecord|null previous record, `null` - if not found.
     * @since 1.0.1
     */
    public function find_upstairs(): ?ActiveRecord
    {
        if ($this->is_roof()) {
            return null;
        }

        return $this->findRecordUsingOwnerPosition(1);
    }

    /**
     * Finds record next to this one.
     * @return ActiveRecord|null next record, `null` - if not found.
     * @since 1.0.1
     */
    public function find_downstairs(): ?ActiveRecord
    {
        if ($this->is_bottom()) {
            return null;
        }

        return $this->findRecordUsingOwnerPosition(-1);
    }

    /**
     * Finds the first record in the list.
     * If this record is the first one, method will return its self reference.
     * @return ActiveRecord|null next record, `null` - if not found.
     * @since 1.0.1
     */
    public function find_bottom(): ?ActiveRecord
    {
        return $this->findRecordUsingOwnerSort('ASC');
    }

    /**
     * Finds the last record in the list.
     * @return ActiveRecord|null next record, `null` - if not found.
     * @since 1.0.1
     */
    public function find_roof(): ?ActiveRecord
    {
        return $this->findRecordUsingOwnerSort('DESC');
    }

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'event_afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'event_afterSave',
        ];
    }

    /**
     * This event raises after owner inserted or updated.
     * It applies previously set {@see positionOnSave}.
     * This event supports other functionality.
     * @param ModelEvent $event event instance.
     */
    public function event_afterSave(ModelEvent $event): void
    {
        throw new NotSupportedException('TODO');
    }
}
