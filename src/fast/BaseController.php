<?php
declare (strict_types = 1);

namespace aoma\fast;

use support\Request;
use support\Response;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var Request
     */
    protected Request $request;

    protected ?AdminInterface $authModel;

    /**
     * @var array
     */
    protected array $__models__ = [];

    /**
     * Request
     *
     * @var array
     */
    protected array $unblock = [];

    /**
     * Current model name
     *
     * @var string
     */
    protected string $modelName = '';

    /**
     * Auto query filter based on config
     *
     * @var boolean
     */
    protected bool $autoQueryFilter = true;

    /**
     * @throws ModelNotFoundException
     */
    public function __construct()
    {
        $this->request = request();
        $this->initialize();
    }

    /**
     * Setup method
     */
    protected function setup()
    {
    }

    /**
     * Accessible check method
     */
    protected function authorize()
    {
    }


    // 初始化

    /**
     * @throws ModelNotFoundException
     */
    protected function initialize(): void
    {
        if(!empty($this->modelName)){
            $this->setModel($this->modelName);
        }
        // 初始化钩子
        if(method_exists($this, 'setup')){
            $this->setup();
        }
        // 检查授权
        if(method_exists($this, 'authorize')){
            $this->authorize();
        }
    }

    /**
     * Register model
     *
     * @param string $name
     * @param mixed $class
     * @throws ModelNotFoundException
     */
    protected function setModel(mixed $class, string $name = 'default'): void
    {
        if (isset($this->__models__[$name])) {
            throw new ModelNotFoundException('model overwritten');
        }
        if (is_string($class) &&
            class_exists($class) &&
            is_subclass_of($class, BaseModel::class)) {
            $this->__models__[$name] = new $class();
            return;
        }
        if (!is_scalar($class) && is_a($class, BaseModel::class)) {
            $this->__models__[$name] = $class;
            return;
        }
        throw new ModelNotFoundException('invalid model', $class);
    }

    /**
     * Get model instance
     *
     * @return BaseModel
     * @throws ModelNotFoundException
     */
    protected function getModel(): BaseModel
    {
        $name = $this->request->input('__model__', 'default');
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$name);
        if (!isset($this->__models__[$name])) {
            throw new ModelNotFoundException('model not set', '');
        }
        return $this->__models__[$name];
    }

    /**
     * 控制器中返回 HTTP JSON RESPONSE
     *
     * @param mixed|null $data
     * @param string $msg
     * @param integer $code
     * @return Response
     */
    protected function success(mixed $data = null, string $msg = 'success', int $code = 1): Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    /**
     * 控制器中返回 HTTP JSON RESPONSE
     *
     * @param string $msg
     * @param mixed|null $data
     * @param integer $code
     * @return Response
     */
    protected function error(string $msg = 'error', mixed $data = null, int $code = 0): Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ]);
    }
}
