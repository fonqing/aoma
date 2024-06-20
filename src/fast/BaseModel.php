<?php
namespace aoma\fast;

use think\facade\Validate;
use think\Model;

/**
 * @method static where(mixed $field, $op = null, $condition = null)
 */
class BaseModel extends Model {

    /**
     * 模型字段配置
     *
     * @var array table fields setting
     */
    public static $fields = [
        'create' => [],//array or string joined by comma
        'update' => [],
        'index'  => [],
        'search' => [],
    ];

    /**
     * 模型验证规则
     *
     * @var array 验证规则
     */
    public static $rules = [
        'create' => [],
        'update' => []
    ];

    /**
     * @var array
     */
    public static $order = [];

    public static $messages = [];

    /**
     * @var int
     */
    public static $pageSize = 10;

    /**
     * @var bool
     */
    public static $cache = false;


    /**
     * @var array 模型验证错误信息
     */
    protected $error = [];

    /**
     * 获取错误信息
     *
     * @return array
     */
    public function getError(): array
    {
        return $this->error;
    }

    /**
     * 使用验证规则验证数据
     *
     * @param array $data
     * @param string $scene
     * @return bool
     */
    protected function isValid(array $data, string $scene): bool
    {
        $rules = static::$rules[$scene] ?? [];
        if (empty($rules)) {
            return true;
        }
        $validator = Validate::rule($rules);
        if ($validator->check($data)){
            return true;
        } else {
            $this->error = $validator->getError();
            return false;
        }
    }

    /**
     * 自动构造query string
     *
     * @param mixed $field     查询字段
     * @param mixed $op        查询表达式
     * @param mixed $condition 查询条件
     * @return BaseModel
     */
    public static function queryFilter($field, $op = null, $condition = null): BaseModel
    {
        $fields = static::$fields['search']??[];
        if (empty($fields)) {
            return static::where($field, $op, $condition);
        }
        $query = request()->get();
        if (empty($query)) {
            return static::where($field, $op, $condition);
        }
        $model = static::where($field, $op, $condition);
        foreach($fields as $field => $rule) {
            $value = $query[$field] ?? '';
            if(empty($value)) {
                continue;
            }
            switch ($rule) {
                case 'equal':
                case 'eq':
                    if (!is_string($value) && !is_numeric($value)) {
                        break;
                    }
                    $model->where($field, $query[$field]);
                    break;
                case 'like':
                    if (!is_string($value) && !is_numeric($value)) {
                        break;
                    }
                    $model->whereLike($field, '%' . $query[$field] . '%');
                    break;
                case 'datetime':
                    if (is_array($value) && sizeof($value) === 2 ) {
                        $model->whereBetweenTime($field, $value[0], $value[1]);
                    }
                    break;
            }
        }
        return $model;
    }
}