<?php

namespace aoma\fast;

use support\Request;
use think\Model;

/**
 * @mixin Model
 */
interface AdminInterface
{
    /**
     * 根据自己的需求实现解析各种token或者session等获取用户模型主键值
     *
     * @param Request $request
     * @return string|int
     */
    public function getAuthId(Request $request):string|int;
    public function getAuthInfo(Request $request):string|int;
}