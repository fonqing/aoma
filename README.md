# aoma

自用快速开发库，基于```workerman/webman```框架。

## Features

- 自动CRUD操作 (含Excel数据导出)
- 内置企业微信/钉钉/飞书 群机器人通知库

## Installation

```
composer require fonqing/aoma  
```

## Usage

- 使用本类库的前提是使用 webman + think-orm + think-cache
- 控制器继承 ```\aoma\fast\BaseController``` 类
- 模型继承 ```\aoma\fast\BaseModel``` 类
- 控制器内通过 ```use \aoma\fast\traits\AutoCrud;``` 启用自动CRUD操作
- 控制器内通过 ```use \aoma\fast\traits\Authorize;``` 启用权限控制
- 控制器内通过 ```setup```方法进行必要的配置

## Controller Examples

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


