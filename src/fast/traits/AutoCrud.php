<?php

namespace aoma\fast\traits;

use aoma\Exporter;
use Exception;
use aoma\exception\BusinessException;
use support\Log;
use aoma\fast\BaseModel;
use think\Validate;
use support\Request;
use think\facade\Db;
use support\Response;
use aoma\fast\BaseController;
use think\db\exception\DbException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;

/**
 * @property Request $request
 * @mixin BaseController
 */
trait AutoCrud
{
    /**
     * @return void
     * @throws BusinessException
     */
    private function checkModel(): void
    {
        $key = $this->request->input('__model__', 'default');
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        if (!isset($this->__models__[$key])) {
            throw new BusinessException('Model not exists');
        }
    }

    /**
     * 读取模型信息
     *
     * @param string $key
     * @param mixed|string $default
     * @return mixed|string|string[]
     * @throws BusinessException
     */
    protected function getModelInfo(string $key, mixed $default = ''): mixed
    {
        $model = $this->getModel();
        return match ($key) {
            'order' => $model::$order ?? $default,
            'cache' => $model::$cache ?? $default,
            'page_size', 'pageSize', 'pagesize', 'per_page' => $model::$pageSize ?? $default,
            'list_with' => $model::$listWidth ?? [],
            'list_append' => $model::$listAppend ?? [],
            'detail_with' => $model::$detailWith ?? [],
            'detail_append' => $model::$detailAppend ?? [],
            default => $default,
        };
    }

    /**
     * @param string $name
     * @return array
     * @throws BusinessException
     */
    protected function getFields(string $name): array
    {
        $model = $this->getModel();
        return $this->dealFields($model::$fields[$name] ?? []);
    }

    /**
     * @param string $name
     * @param mixed $fields
     * @throws BusinessException
     */
    protected function setFields(string $name, mixed $fields): void
    {
        $model = $this->getModel();
        $model::$fields[$name] = $this->dealFields($fields);
    }

    /**
     * @return array
     * @throws BusinessException
     */
    protected function getMessages(): array
    {
        $model = $this->getModel();
        return $model::$messages ?? [];
    }

    /**
     * @param $name
     * @return array
     * @throws BusinessException
     */
    protected function getRules($name): array
    {
        $model = $this->getModel();
        return $model::$rules[$name] ?? [];
    }

    /**
     * @param string $name
     * @param $rules
     * @throws BusinessException
     */
    protected function setRules(string $name, $rules): void
    {
        $model = $this->getModel();
        $model::$rules[$name] = $rules;
    }

    /**
     * @param  mixed    $input
     * @return string[]
     */
    private function dealFields(mixed $input): array
    {
        if (is_array($input)) {
            return $input;
        } elseif (is_string($input)) {
            $fields = explode(',', $input);
            return array_filter($fields, function ($value) {
                $value = trim($value);
                return !empty($value);
            });
        }
        return [];
    }

    public function indexQuery($model)
    {
        return $model;
    }

    /**
     * 分页数据捕获，用于追加数据
     * @param        $item
     * @param        $key
     * @return mixed
     */
    public function pageEach($item, $key = ''): mixed
    {
        return $item;
    }

    /**
     * 输出到列表视图的数据捕获
     * @param        $data
     * @return mixed
     * @throws BusinessException|Exception
     */
    public function indexAssign($data): mixed
    {
        $total = $this->request->input('summary', []);
        if (empty($total) || !is_array($total)) {
            return $data;
        }
        $list = $data->toArray();
        $rows = $list['data'];
        if (empty($rows)) {
            return $data;
        }
        if (method_exists($this, 'extend_deal')) {
            $rows = $this->extend_deal($rows);
            $list['data'] = $rows;
        }
        $total_data = [];
        foreach ($total as $key) {
            $total_data[$key] = $this->sumField($rows, $key);
        }
        $list['summary'] = $total_data;
        return $list;
    }

    /**
     * 输出到新增视图的数据捕获
     * @param        $data
     * @return mixed
     */
    public function addAssign($data): mixed
    {
        $data['lists'] = [];
        return $data;
    }

    /**
     * 新增数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param        $data
     * @return mixed
     */
    protected function beforeCreate($data): mixed
    {
        return $data;
    }

    /**
     * 输出到编辑视图的数据捕获
     * @param        $data
     * @return mixed
     */
    public function editAssign($data): mixed
    {
        return $data;
    }

    /**
     * 编辑数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param        $data
     * @return mixed
     */
    protected function beforeUpdate($data): mixed
    {
        return $data;
    }

    /**
     * 成功添加数据后的数据捕获
     * 通过 fail($message);将错误信息返回到前端，并且回滚数据
     * @param  array|int|string $pk   添加后的主键值，多主键传入数组
     * @param  mixed            $data 接受的参数，包含追加的
     * @return void
     */
    protected function afterCreate(array|int|string $pk, mixed $data)
    {
    }

    /**
     * 成功编辑数据后的数据捕获
     * 通过 fail($message);将错误信息返回到前端，并且回滚数据
     * @param int|array|string $pk   编辑数据的主键值，多主键传入数组
     * @param mixed            $data 接受的参数，包含追加的
     */
    protected function afterUpdate(int|array|string $pk, mixed $data)
    {
    }

    /**
     * 成功删除数据后的数据捕获
     * 通过 fail($message);将错误信息返回到前端，并且回滚数据
     * @param  array|int|string $pk   要删除数据的主键值，多主键则传入数组
     * @param  mixed      $data
     * @return void
     */
    protected function afterDelete(array|int|string $pk, mixed $data)
    {
    }

    /**
     * 输出到详情视图的数据捕获
     * @param        $data
     * @return mixed
     */
    public function detailAssign($data): mixed
    {
        return $data;
    }

    /**
     * 输出到打印视图的数据捕获
     * @param        $data
     * @return mixed
     */
    public function printAssign($data): mixed
    {
        return $data;
    }

    /**
     * 列表页
     * @param Request $request
     * @return Response
     */
    public function index($request): Response
    {
        try {
            $this->checkModel();
            $pageSize = $request->input('page_size', 10) ?: $this->getModelInfo('page_size', 10);
            $pageSize = max(1, (int) $pageSize);
            $model = $this->getModel();
            if ($this->autoQueryFilter) {
                $model->queryFilter();
            }
            $with = $this->getModelInfo('list_with');
            if(!empty($with)) {
                $model->with($with);
            }
            $append = $this->getModelInfo('list_append');
            if(!empty($append)) {
                $model->append($append);
            }
            $sql = $model->field($this->getFields('index'));
            if ($this->getModelInfo('cache', false)) {
                $modelName = class_basename($model);
                $sql->cache(true, 0, $modelName . '_cache_data');
            }
            $list = $this->indexQuery($sql)
                ->order($this->getModelInfo('order', null))
                ->paginate($pageSize)
                ->each(function ($item, $key) {
                    return $this->pageEach($item, $key);
                });
            return $this->success($this->indexAssign($list));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 新增数据页
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function create($request): Response
    {
        if ($request->method() == 'POST') {
            $fields = $this->getFields('create');
            $rules = $this->getRules('create');
            $params = empty($fields) ? $request->post() : $request->only($fields);
            $addData = $this->beforeCreate($params);
            if (!empty($rules)) {
                $validate = new Validate();
                $message = $this->getMessages();
                $result = $validate->message($message)->check($addData, $rules);
                if (!$result) {
                    $error = $validate->getError();
                    $errorMessage = is_array($error) ? ($error[0] ?? "") : $error;
                    return $this->error($errorMessage);
                }
            }
            $model = $this->getModel();
            // 验证通过
            Db::startTrans();
            try {
                $model->save($addData);
                $pk = $model->getPk();
                $pkValue = $this->getPkValue($model, $pk);
                $this->afterCreate($pkValue, $addData);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                Log::error($e->getFile() . ':' . $e->getLine() . ':' . $e->getMessage());
                return $this->error($e->getMessage());
            }
            if (!is_array($pkValue)) {
                $pkValue = [$pk => $pkValue];
            }
            return $this->success($pkValue);
        }
        return $this->success($this->addAssign([]));
    }

    /**
     * 获取模型的主键值
     * @param mixed|null $pk
     * @param  BaseModel   $model
     * @return array|mixed
     */
    protected function getPkValue($model, mixed $pk = null): mixed
    {
        if (is_null($pk)) {
            $pk = $model->getPk();
        }
        if (is_array($pk)) {
            $pkValue = [];
            foreach ($pk as $key) {
                $pkValue[$key] = $model->getData($key);
            }
        } else {
            $pkValue = $model->getData($pk);
        }
        return $pkValue;
    }

    /**
     * 编辑数据页
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function update($request): Response
    {
        $model = $this->getModel();
        $pk = $model->getPk();
        $pkValue = $request->only(is_string($pk) ? [$pk] : $pk);
        $pkArr = $pk;
        if (is_string($pkArr)) {
            $pkArr = [$pkArr];
        }
        foreach ($pkArr as $key) {
            if (empty($pkValue[$key])) {
                return $this->error('参数有误，缺少' . $key);
            }
        }
        if ($request->method() == 'POST') {
            $fields = $this->getFields('update');
            $rules = $this->getRules('update');
            $params = empty($fields) ? $request->post() : $request->only($fields);
            $params = array_merge($params, $pkValue);
            $editData = $this->beforeUpdate($params);
            if (!empty($rules)) {
                $message = $this->getMessages();
                $validate = new Validate();
                $result = $validate->message($message)->check($editData, $rules);
                if (!$result) { // 验证不通过
                    $error = $validate->getError();
                    $errorMessage = is_array($error) ? ($error[0] ?? "") : $error;
                    return $this->error($errorMessage);
                }
            }
            // 验证通过
            Db::startTrans();
            try {
                $data = $model->find($pkValue);
                if (empty($fields)) {
                    $model->save($editData);
                } else {
                    $data->allowField($fields)->save($editData);
                }
                if (is_string($pk)) {
                    $pkValue = $pkValue[$pk];
                }
                $this->afterUpdate($pkValue, $editData);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                return $this->error($e->getMessage());
            }
            return $this->success();
        }
        $data = $model->findOrEmpty($pkValue);
        if ($data->isEmpty()) {
            return $this->error("无法加载更新数据");
        }
        $res = [];
        foreach ($pkValue as $key => $value) {
            $res[$key] = $value;
        }
        $res['data'] = $data;
        return $this->success($this->editAssign($res));
    }

    /**
     * 删除
     * @param Request $request
     * @return Response
     */
    public function delete($request): Response
    {
        try {
            $model = $this->getModel();
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
        $pk = $model->getPk();
        $pkValue = $request->only(is_array($pk) ? $pk : [$pk]);
        $pkArr = $pk;
        if (is_string($pkArr)) {
            $pkArr = [$pkArr];
        }
        foreach ($pkArr as $key) {
            if (empty($pkValue[$key])) {
                return $this->error('参数有误，缺少' . $key);
            }
        }
        $data = $model->findOrEmpty($pkValue);
        if ($data->isEmpty()) {
            return $this->error('要删除的信息不存在');
        }
        Db::startTrans();
        try {
            if (is_string($pk)) {
                $pkValue = $pkValue[$pk];
            }
            $this->afterDelete($pkValue, $data);
            $data->delete();
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            return $this->error($e->getMessage());
        }
        return $this->success();
    }

    /**
     * 详情
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function detail($request): Response
    {
        $model = $this->getModel();
        $pk = $model->getPk();
        $pkValue = $request->only(is_string($pk) ? [$pk] : $pk);
        $pkArr = $pk;
        if (is_string($pkArr)) {
            $pkArr = [$pkArr];
        }
        foreach ($pkArr as $key) {
            if (empty($pkValue[$key])) {
                return $this->error('参数有误，缺少' . $key);
            }
        }
        try {
            $append = $this->getModelInfo('detail_append');
            if(empty($append)) {
                $data = $model->findOrFail($pkValue);
            }else{
                $data = $model->append($append)->findOrEmpty($pkValue);
            }

        } catch (DataNotFoundException | DbException $e) {
            return $this->error($e->getMessage());
        }
        return $this->success($this->detailAssign($data));
    }

    public function export(): Response
    {
        if (empty($this->exportConfig['columns'])) {
            return $this->error('导出未配置');
        }
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        try {
            $model = $this->getModel();
            $sql = $model->field($this->getFields('index'));
            if ($this->autoQueryFilter) {
                $sql = $sql->queryFilter();
            }
            $list = $this->indexQuery($sql)->order($this->getModelInfo('order', null))->select();
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }

        foreach ($list as $index => $item) {
            $list[$index] = $this->pageEach($item, $index);
        }
        $title = 'untitled_' . date('Ymd_His');
        if (!empty($this->exportConfig['title'])) {
            $title = $this->exportConfig['title'];
        }
        try {
            $excel = Exporter::loadDriver(($this->exportConfig['fast'] ?? false) ? "xls_writer" : "php_spreadsheet");
            return $excel->setColumns($this->exportConfig['columns'])
                ->setTitle($title)
                ->setFileName($this->exportConfig['name'])
                ->setDataQuery($list)
                ->setControllerContext($this)
                ->export();
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
