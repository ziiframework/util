<?php

declare(strict_types=1);

namespace Zii\Util;

use app\support\Db;
use app\models\BasicActiveRecord;
use app\support\Rule;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpNamespace;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\AttributeTypecastBehavior;
use yii\db\ActiveQuery;
use yii\db\ColumnSchema;
use yii\db\Exception;
use yii\db\TableSchema;
use yii\helpers\Inflector;

abstract class ModelCreateController extends BasicCommandController
{
    private static string $identityInterfaceImplement = 'yh';

    private PhpNamespace $_namespace;

    private ClassType $_class;

    // 表索引
    private array $_indexes = [];

    /**
     * 必需 eg:
     * [
     *   ['name' => NAME],
     *   ['name' => NAME],
     *   ['name' => NAME],
     * ]
     */
    private array $_ruleRequired = [];

    /**
     * 范围 eg:
     * [
     *   ['name' => NAME, 'range' => RANGE],
     *   ['name' => NAME, 'range' => RANGE],
     *   ['name' => NAME, 'range' => RANGE],
     * ]
     */
    private array $_ruleRange = [];

    /**
     * 布尔值 eg:
     * [
     *   ['name' => NAME],
     *   ['name' => NAME],
     *   ['name' => NAME],
     * ]
     */
    private array $_ruleBoolean = [];


    /**
     * 整数型 eg:
     * [
     *   ['name' => NAME, 'size' => SIZE],
     *   ['name' => NAME, 'size' => SIZE],
     *   ['name' => NAME, 'size' => SIZE],
     * ]
     */
    private array $_ruleInteger = [];

    /**
     * 字符串 eg:
     * [
     *   ['name' => NAME, 'size' => SIZE],
     *   ['name' => NAME, 'size' => SIZE],
     *   ['name' => NAME, 'size' => SIZE],
     * ]
     */
    private array $_ruleString = [];

    /**
     * 时间日期 eg:
     * [
     *   ['name' => NAME, 'format' => 'Y-m-d'],
     *   ['name' => NAME, 'format' => 'Y-m-d H:i:s'],
     *   ['name' => NAME, 'format' => 'Y'],
     * ]
     */
    private array $_ruleYmdHis = [];

    /**
     * 存在 eg:
     * [
     *   ['name' => NAME, 'targetClassName' => 'Member'],
     *   ['name' => NAME, 'targetClassName' => 'Member'],
     *   ['name' => NAME, 'targetClassName' => 'Member'],
     * ]
     */
    private array $_ruleExist = [];

    /**
     * 类型推断 eg:
     * [
     *   attr => targetClass,
     *   attr => targetClass,
     *   attr => targetClass,
     * ]
     */
    private array $_typeCastAttributes = [];

    /**
     * 时间日期格式
     */
    private static array $_dateFormat = [
        'year' => 'Y',
        'date' => 'Y-m-d',
        'time' => 'H:i:s',
        'datetime' => 'Y-m-d H:i:s',
        'timestamp' => 'Y-m-d H:i:s',
    ];

    /**
     * 特殊字符替换
     */
    private static array $_codeReplacements = [
        "'%" => '',
        "%'" => '',
        '"%' => '',
        '%"' => '',
        '""' => "''",
        '\\$' => '$',
        '\\n\\t' => "\n",
        '\\n' => "\n",
        "\t" => '    ',
        '0 => ' => '',
        '1 => ' => '',
    ];

    /**
     * @param bool $overwrite
     * @throws NotSupportedException
     * @throws Exception
     */
    public function actionAll(bool $overwrite = false): void
    {
        foreach (Yii::$app->db->getSchema()->getTableNames() as $tableName) {
            if (!in_array($tableName, [
                'dbcache',
                'dbsession',
                'auth_rule',
                'auth_item',
                'auth_item_child',
                'auth_assignment',
                'migration',
            ])) {
                $this->actionIndex($tableName, $overwrite);
            }
        }
    }

    private function resetAttributes(): void
    {
        $this->_indexes = [];
        $this->_ruleRequired = [];
        $this->_ruleRange = [];
        $this->_ruleBoolean = [];
        $this->_ruleInteger = [];
        $this->_ruleString = [];
        $this->_ruleYmdHis = [];
        $this->_ruleExist = [];
        $this->_typeCastAttributes = [];
    }

    /**
     * 生成模型.
     * @param string $tableName
     * @param bool $overwrite
     */
    public function actionIndex(string $tableName, bool $overwrite = false): void
    {
        clearstatcache();

        $this->resetAttributes();

        $this->_namespace = new PhpNamespace('app\models');
        $this->_class = $this->_namespace->addClass(Inflector::camelize($tableName));
        $this->_class->setExtends(BasicActiveRecord::class);
        $this->_class->setFinal();
        if ($tableName === self::$identityInterfaceImplement) {
            // $this->_namespace->addUse('Yii');
            $this->_namespace->addUse(yii\web\IdentityInterface::class);
            $this->_class->addImplement(yii\web\IdentityInterface::class);
        }

        // 表结构
        $_schema = Yii::$app->db->getTableSchema($tableName, true);
        if (!($_schema instanceof TableSchema)) {
            echo "Table $tableName does not exist.\n";
            exit;
        }

        // 表注释
        $this->_class->addComment(Db::getTableComment($tableName). "\n");

        // 表索引
        foreach (Db::getTableIndexes($tableName) as $index) {
            $this->_indexes[$index['Column_name']] = (bool)($index['Non_unique'] * 1) ? 'indexed' : 'unique';
        }

        foreach ($_schema->columns as $column) {
            if (in_array($column->name, ['id', 'client_ip', 'created_at'], true)) {
                continue;
            }

            // 字段注释
            $this->_class->addComment(implode(' ', [
                '@property',
                mb_stripos($column->dbType, 'decimal') !== false ? 'float' : $column->phpType,
                '$' . $column->name,
                $column->comment . "[$column->dbType]" . ($column->allowNull ? '.' : '[NOT NULL].'),
                isset($this->_indexes[$column->name]) && $this->_indexes[$column->name] ? "This property is {$this->_indexes[$column->name]}." : '',
            ]));

            // 列处理
            $this->castColumn($column);
            ++$this->_columnIdx;
        }

        // public function attributeLabels
        $this->_class->addMethod('attributeLabels')
            ->setReturnType('array')
            ->addComment('@inheritdoc')
            ->setBody('return array_merge(parent::attributeLabels(), ?);', [
                array_diff_key(
                    array_combine(
                        array_column($_schema->columns, 'name'),
                        array_column($_schema->columns, 'comment')
                    ), [
                    'id' => 'ID',
                    'created_at' => '创建时间',
                ])
            ]);

        // public function extraFields
        $this->_class->addMethod('extraFields')
            ->setReturnType('array')
            ->addComment('@inheritdoc')
            ->setBody('return array_merge(parent::extraFields(), ?);', [
                array_map('lcfirst', array_column($this->_ruleExist, 'targetClassName')),
            ]);

        // rules
        $this->_class->addMethod('rules')
            ->setReturnType('array')
            ->addComment('@inheritdoc')
            ->setBody('return array_merge(parent::rules(), ?);', [$this->generateRules()]);

        // identity interface implement
        if ($tableName === self::$identityInterfaceImplement) {
            $this->_class->addMethod('findIdentity')
                ->setReturnType('?IdentityInterface')
                ->setStatic()
                ->addComment('@inheritdoc')
                ->setBody("return static::findOne(['id' => \$id]);")
                ->setParameters([
                    (new Parameter('id'))->setType('int'),
                ]);
            $this->_class->addMethod('findIdentityByAccessToken')
                ->setReturnType('?IdentityInterface')
                ->setStatic()
                ->addComment('@inheritdoc')
                ->setBody("return static::findOne(['access_token' => \$token]);")
                ->setParameters([
                    (new Parameter('token'))->setType('string'),
                    (new Parameter('type'))->setType('string')->setDefaultValue(null),
                ]);
            $this->_class->addMethod('getId')
                ->setReturnType('int')
                ->addComment('@inheritdoc')
                ->setBody('return $this->getPrimaryKey();');
            $this->_class->addMethod('getAuthKey')
                ->setReturnType('string')
                ->addComment('@inheritdoc')
                ->setBody('return $this->session_key;');
            $this->_class->addMethod('validateAuthKey')
                ->setReturnType('bool')
                ->addComment('@inheritdoc')
                ->setBody('return $this->getAuthKey() === $session_key;')
                ->addParameter('session_key');
        }

        $file = Yii::getAlias('@app/models/' . Inflector::camelize($tableName) . '.php');
        if ($overwrite === true || !file_exists($file)) {
            $objectBody = str_replace(
                array_keys(self::$_codeReplacements),
                array_values(self::$_codeReplacements),
                $this->_namespace
            );
            if (file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n" . $objectBody) !== false) {
                $fileContent = file_get_contents($file);
                $fileContent = str_replace(': \\?', ': ?', $fileContent);
                file_put_contents($file, $fileContent);
                echo '✔ Successfully created model ' . Inflector::camelize($tableName);
            } else {
                echo '✘ Failed to create model ' . Inflector::camelize($tableName);
            }
            echo "\n";
        } else {
            echo '✘ Create model ' . Inflector::camelize($tableName) . " aborted, file $file already exists\n";
        }
    }

    private int $_columnIdx = 0;

    private function castColumn(ColumnSchema $column): void
    {
        // required
        if (!$column->allowNull && $column->defaultValue === null) {
            $this->_ruleRequired[] = ['name' => $column->name];
        }

        // tinyint
        if (Db::castDataType($column->dbType) === 'tinyint') {
            // $column->size === 1
            // Warning: #1681 Integer display width is deprecated and will be removed in a future release.
            if (preg_match('/^(is|has|can|enable)_/', $column->name)) {
                $this->_typeCastAttributes[$column->name] = AttributeTypecastBehavior::TYPE_BOOLEAN;
                $this->_ruleBoolean[] = ['name' => $column->name];
            } else {
                $this->_typeCastAttributes[$column->name] = AttributeTypecastBehavior::TYPE_INTEGER;
                $this->_ruleInteger[] = [
                    'name' => $column->name,
                    'max' => Db::getColumnMaxValue($column),
                ];
            }
        }

        // int
        if (in_array(Db::castDataType($column->dbType), ['smallint', 'mediumint', 'int', 'integer', 'bigint'], true)) {
            $this->_ruleInteger[] = [
                'name' => $column->name,
                'max' => Db::getColumnMaxValue($column),
            ];
        }

        // double、float、decimal TODO
        if (Db::castDataType($column->dbType) === 'double') {
            $this->_ruleInteger[] = [
                'name' => $column->name,
                'max' => Db::getColumnMaxValue($column),
            ];
            $this->_typeCastAttributes[$column->name] = AttributeTypecastBehavior::TYPE_INTEGER;
        }
        if (Db::castDataType($column->dbType) === 'float') {
            $this->_ruleInteger[] = [
                'name' => $column->name,
                'max' => Db::getColumnMaxValue($column),
            ];
            $this->_typeCastAttributes[$column->name] = AttributeTypecastBehavior::TYPE_INTEGER;
        }
        if (Db::castDataType($column->dbType) === 'decimal') {
            $this->_ruleInteger[] = [
                'name' => $column->name,
                'max' => Db::getColumnMaxValue($column),
            ];
            $this->_typeCastAttributes[$column->name] = AttributeTypecastBehavior::TYPE_INTEGER;
        }

        // varchar
        if (Db::castDataType($column->dbType) === 'varchar') {
            $this->_ruleString[] = [
                'name' => $column->name,
                'size' => $column->size,
            ];
        }

        // text
        if (Db::castDataType($column->dbType) === 'text') {
            $this->_ruleString[] = [
                'name' => $column->name,
                'size' => 65535,
            ];
        }

        // enum
        if (Db::castDataType($column->dbType) === 'enum') {
            $this->_ruleRange[] = [
                'name' => $column->name,
                'range' => $column->enumValues,
            ];
        }

        // set
        if (Db::castDataType($column->dbType) === 'set') {
            $isMatch = preg_match('/^([\w ]+)(?:\(([^)]+)\))?$/', $column->dbType, $matches);
            if ($isMatch !== false && !empty($matches[2])) {
                $values = preg_split('/\s*,\s*/', $matches[2]);
                foreach ($values as $i => $value) {
                    $values[$i] = trim($value, "'");
                }
                $this->_ruleRange[] = [
                    'name' => $column->name,
                    'range' => $values,
                    'allowArray' => true,
                ];
            }
        }

        // year、date、time、timestamp、datetime
        if (isset(self::$_dateFormat[Db::castDataType($column->dbType)])) {
            $this->_ruleYmdHis[] = [
                'name' => $column->name,
                'format' => self::$_dateFormat[Db::castDataType($column->dbType)],
            ];
        }

        // xxx_id
        if (preg_match('/^([a-z0-9]+)_id$/', $column->name, $matches)) {
            $getTableComment = Db::getTableComment($matches[1]);
            if ($getTableComment !== null) {
                $this->_ruleExist[] = [
                    'name' => $column->name,
                    'targetClassName' => ucfirst($matches[1]),
                    'targetClassComment' => $getTableComment,
                ];
            }
        }
    }

    private function generateRules(): array
    {
        $rules = [];

        $this->_namespace->addUse(Rule::class);
        // 字符串类型
        if (!empty($this->_ruleString) || !empty($this->_ruleRange)) {
            $closure = new Closure();
            $closure->setBody('return Rule::strOrNull($value);')
                ->setReturnType('?string')
                ->addParameter('value');
            $rules[] = [
                $this->arrayOrString(array_unique(array_merge(
                    array_column($this->_ruleString, 'name'),
                    array_column($this->_ruleRange, 'name')
                ))),
                'filter',
                'filter' => "%$closure%",
            ];
        }
        // 时间日期格式
        if (!empty($this->_ruleYmdHis)) {
            $groupByFormat = [];
            foreach ($this->_ruleYmdHis as $column) {
                $groupByFormat[$column['format']][] = $column['name'];
            }
            foreach ($groupByFormat as $format => $names) {
                $closure = new Closure();
                $closure->setBody("return Rule::dateOrNull(\$value, '{$format}');")
                    ->setReturnType('?string')
                    ->addParameter('value');
                $rules[] = [
                    $this->arrayOrString($names),
                    'filter',
                    'filter' => "%$closure%",
                ];
            }
        }
        // 字符串类型&长度
        if (!empty($this->_ruleString)) {
            $groupBySize = [];
            foreach ($this->_ruleString as $column) {
                $groupBySize[$column['size']][] = $column['name'];
            }
            foreach ($groupBySize as $size => $names) {
                $rules[] = [
                    $this->arrayOrString($names),
                    'string',
                    'min' => 1,
                    'max' => (int)$size,
                    'message' => '{attribute}必须是合法的字符',
                    'tooShort' => '{attribute}不能少于1个字符',
                    'tooLong' => "{attribute}不能超过{$size}个字符",
                ];
            }
        }
        // 整数类型&长度
        if (!empty($this->_ruleInteger)) {
            $groupBySize = [];
            foreach ($this->_ruleInteger as $column) {
                $groupBySize[$column['max']][] = $column['name'];
            }
            foreach ($groupBySize as $size => $names) {
                $rules[] = [
                    $this->arrayOrString($names),
                    'integer',
                    'integerOnly' => true,
                    'min' => 0,
                    'max' => (int)$size,
                    'message' => '{attribute}必须是整数',
                    'tooSmall' => '{attribute}不能小于0',
                    'tooBig' => "{attribute}不能大于{$size}",
                ];
            }
        }
        // 布尔型
        if (!empty($this->_ruleBoolean)) {
            $rules[] = [
                $this->arrayOrString(array_column($this->_ruleBoolean, 'name')),
                'boolean',
                'trueValue' => '1',
                'falseValue' => '0',
                'message' => '{attribute}不是有效的值',
            ];
        }
        // 范围
        if (!empty($this->_ruleRange)) {
            foreach ($this->_ruleRange as $item) {
                $rules[] = [
                    $this->arrayOrString($item['name']),
                    'in',
                    'range' => $item['range'],
                    'strict' => true,
                    'allowArray' => $item['allowArray'] ?? false,
                    'message' => '{attribute}不是有效的值',
                ];
            }
        }
        // 必要
        if (!empty($this->_ruleRequired)) {
            $rules[] = [
                $this->arrayOrString(array_column($this->_ruleRequired, 'name')),
                'required',
                'strict' => true,
                'message' => '{attribute}不能为空',
            ];
        }
        // 存在
        if (!empty($this->_ruleExist)) {
            $this->_namespace->addUse(ActiveQuery::class);
            foreach ($this->_ruleExist as $item) {
                $rules[] = [
                    $this->arrayOrString($item['name']),
                    'exist',
                    'targetClass' => '%' . $item['targetClassName'] . '::class%',
                    'targetAttribute' => 'id',
                    'message' => '{attribute}不存在',
                ];
                // 类的字段注释
                $this->_class->addComment(implode(' ', [
                    '@property',
                    $item['targetClassName'],
                    '$' . lcfirst($item['targetClassName']),
                    '关联' . str_replace('表', '', $item['targetClassComment']) . '[ActiveRecord].',
                ]));
                // 增加表之间的关联方法
                $this->_class->addMethod("get{$item['targetClassName']}")
                    ->setReturnType('?ActiveQuery')
                    ->addComment("关联{$item['targetClassComment']}")
                    ->addComment("@return null|ActiveQuery|{$item['targetClassName']}")
                    ->setBody("return \$this->hasOne({$item['targetClassName']}::class, ['id' => '" . lcfirst($item['targetClassName']) . "_id']);");
            }
        }
        // 唯一
        if (!empty($this->_indexes)) {
            $uniqueFields = [];
            foreach ($this->_indexes as $indexName => $indexType) {
                if ($indexName !== 'id' && $indexType === 'unique') {
                    $uniqueFields[] = $indexName;
                }
            }
            if ($uniqueFields !== []) {
                $rules[] = [
                    $this->arrayOrString($uniqueFields),
                    'unique',
                    'message' => '{attribute}不能重复',
                ];
            }
        }

        return $rules;
    }

    /**
     * 优先返回一个字符串，用于规则中的目标字段
     * 以下情况将返回字符串：
     * 1. 参数是索引数组，且数组中只有一个元素.
     *
     * @param mixed $value
     * @return mixed
     */
    private function arrayOrString($value)
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $value = array_merge($value);
            if (count($value, COUNT_RECURSIVE) === 1 && isset($value[0])) {
                return $value[0];
            }
        }

        return $value;
    }
}
