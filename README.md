# aoma

自用快速开发库，基于```workerman/webman```框架。

## 特性和功能

- 自动CRUD操作 (含Excel数据导出)
- 内置企业微信/钉钉/飞书 群机器人通知库

## 安装

```
composer require fonqing/aoma  
```

## 使用注意事项

- 使用本类库的前提是使用 webman + think-orm + think-cache
- 控制器继承 ```\aoma\fast\BaseController``` 类
- 模型继承 ```\aoma\fast\BaseModel``` 类
- 控制器内通过 ```use \aoma\fast\traits\AutoCrud;``` 启用自动CRUD操作
- 控制器内通过 ```use \aoma\fast\traits\Authorize;``` 启用权限控制
- 控制器内通过 ```setup```方法进行必要的配置

## 控制器示例

```php
<?php
namespace app\controller;

use aoma\fast\BaseController;
use aoma\fast\traits\AutoCrud;
use aoma\fast\traits\Authorize;
use app\model\News;
use app\model\User;


class NewsController extends BaseController
{
    use AutoCrud, Authorize;

    public function setup()
    {
        // 开启自动CRUD操作时,需要设置当前控制器操作的模型类
        $this->setModel(News::class);
        // 设置当前session的用户模型实例
        // 必须继承\aoma\fast\BaseModel类,
        // 且实现\aoma\fast\UserInterface接口
        $this->session->set(User::find(1));
    }

    // 启用自动CRUD后, 以下 action 即可自动工作
    /*
     news/create 
     news/update 
     news/index 
     news/delete 
     news/detail 
     news/export (需要在模型配置导出字段)
     */ 
     
     /**
      * 自定义创建数据前的操作
      * @param array $data 数据数组
      */
     public function beforeCreate($data) {
        $data['user_id'] = $this->session->getUserId();
        return $data;
     }
}
```

## 模型代码示例

在 think-orm 模型的基础上扩展增加了部分模型属性, 用于自动CRUD和导出

```php
<?php
namespace app\model;

use aoma\fast\BaseModel;

class News extends BaseModel {
    protected $table = 'news';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $dateFormat = 'Y-m-d H:i:s';
   /**
     * Model fields definition, used for search, create, update, index.
     * 
     * @var array
     */
    public static array $fields = [
        'create' => ['cate_id', 'title', 'cover_image', 'content'],// allow to create fields
        'update' => ['cate_id', 'title', 'cover_image', 'content'], // allow update fields
        'index' => ['id','cate_id', 'title', 'cover_image', 'content', 'create_time'], // allow index fields
    ];

    /**
     * Model data validation rules, used for create and update.
     *
     * @var array
     */
    public static array $rules = [
        'create' => [
            'cate_id' => 'required|integer',
            'title'   => 'required|max:240',
            'content' => 'required'
        ],
        'update' => [
            'cate_id' => 'required|integer',
            'title'   => 'required|max:240',
            'content' => 'required'
        ]
    ];

    /**
     * Validation messages
     *
     * @var array
     */
    public static array $messages = [
        'cate_id.required' => '请选择分类',
        'cate_id.integer'  => '请选择分类',
        'title.required'   => '请输入标题',
        'title.max'       => '标题不能超过240字',
        'content.required' => '请输入内容'
    ];

    /**
     * Order way, used for index.
     *
     * @var array
     */
    protected static array $order = [
       'id' => 'desc'
    ];
    
    /**
     * @var int
     */
    public static int $pageSize = 10; // 列表页每页显示条数

    /**
     * @var bool
     */
    public static bool $cache = false; // 列表页是否开启缓存
}
```

## 模型导出配置示例

1、先创建导出Trait
```php
<?php
namespace app\model\export;

trait NewsExport {
    public function getExportConfig(): array
    {
       return [
           'title'   => '文章列表',
           'name'    => '文章列表_' . date('YmdHis'),
           'columns' => [
               '_index'   => ['width' => 5, 'title' => '序号'], //
                'cate_id' => [
                    'width' => 10,
                    'title' => '分类',
                    'callback' => function ($row) {
                        $cates = [4 => '新闻', 2 => '通知', 3 => '公告'];
                        return $cates[$row['cate_id']] ?? '未知';
                    }
                ],
                'title' => ['width' => 30, 'title' => '标题'],
                'create_time' => ['width' => 20, 'title' => '发布时间'],
           ]
       ];
    }
}
```

2、在模型中引入

```php
<?php
namespace app\model;

use aoma\fast\BaseModel;
use app\model\export\NewsExport;

class News extends BaseModel {
    use NewsExport;
}

```

3、在控制器中调用

访问 ```http://yourdomain/news/export``` 即可下载文章列表导出文件
