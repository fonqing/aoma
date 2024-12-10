<?php

namespace aoma\fast\traits;

use aoma\fast\BaseController;
use support\exception\BusinessException;

/**
 * @mixin BaseController
 */
trait Authorize
{
    /**
     * @var array $anonymousRules 匿名可访问的 action
     */
    protected array $anonymousRules = [
        'default' => [
            'user/login', 'user/logout',
        ]
    ];

    /**
     * @var array $uncheckRules 不需要检查权限的 action
     */
    protected array $uncheckRules = [];

    /**
     * Authorize the action.
     *
     * Support authorization by module/controller/action with params.
     *
     * @throws BusinessException
     */
    protected function authorize(): bool
    {
        if ($this->session->isSuperAdmin()){
            return true;
        }
        // Get module/controller/action
        [$module, $contr, $action] = [
            $this->getModuleName(),
            $this->getControllerName(),
            $this->getActionName()
        ];

        // Parse action and get params
        $result = $this->parseAction($action);
        $action = $result['action'];
        $params = $result['params'];
        // Check anonymous access
        if ($this->isAllowed($contr, $action, $this->anonymousRules[$module] ?? $this->anonymousRules)) {
            return true;
        }
        // Check authorize whitelists
        if ($this->isAllowed($contr, $action, $this->uncheckRules[$module] ?? $this->uncheckRules)) {
            return true;
        }
        // Check login
        if ($this->session->isLogin()){
            // Get all user privileges
            $rules = $this->session->getPrivileges();
            // Check if the action is in rules
            if ($this->isAllowed($contr, $action, $rules[$module] ?? $rules)) {
                if (empty($params)) {
                    return true;
                }
                // Check if the params matched with the request
                if ($this->matchParams($params)) {
                    return true;
                }
            }
        }
        throw new BusinessException('无权访问');
    }

    /**
     * @param string $rule
     * @param string $module
     * @return void
     */
    public function setAnonymousRule(string $rule, string $module = 'default'): void
    {
        if(array_key_exists($module, $this->anonymousRules)) {
            $this->anonymousRules[] = $rule;
        }else{
            $this->anonymousRules[$module] = $rule;
        }
    }

    /**
     * @param array $rules
     * @param string $module
     * @return void
     */
    public function setAnonymousRules(array $rules, string $module = 'default'): void
    {
        foreach($rules as $rule) {
            $this->setAnonymousRule($rule, $module);
        }
    }

    /**
     * @param string $action
     * @param string $controller
     * @param string $module
     * @return void
     */
    public function setUncheckAction(string $action, string $controller = '', string $module = ''): void
    {
        $module = empty($module) ? $this->getModuleName() : $module;
        $contr = empty($controller) ? $this->getControllerName() : $controller;
        if(array_key_exists($module, $this->uncheckRules)){
            $this->uncheckRules[$module][]=$contr.'/'.$action;
        }else{
            $this->uncheckRules[$module] = [];
        }
    }

    /**
     * @param array $actions
     * @param string $controller
     * @param string $module
     * @return void
     */
    public function setUncheckActions(array $actions = [], string $controller = '', string $module = ''): void
    {
        $module = empty($module) ? $this->getModuleName() : $module;
        $contr = empty($controller) ? $this->getControllerName() : $controller;
        foreach($actions as $action) {
            $this->setUncheckAction($action, $contr, $module);
        }
    }

    /**
     * @param string $ca
     * @param array $params
     * @param string $module
     * @return bool
     */
    public function hasPrivilege(string $ca, array $params = [], string $module = 'default'): bool
    {
        if ($this->session->isSuperAdmin()){
            return true;
        }
        [$contr, $action] = explode('/', $ca);
        if ($this->isAllowed($contr, $action, $this->session->getPrivileges($module))) {
            if(empty($params)){
                return true;
            }
            return $this->matchParams($params);
        }
        return false;
    }

    /**
     * Check if the action is in rules.
     *
     * @param string $c
     * @param string $a
     * @param array $rules
     * @return bool
     */
    private function isAllowed(string $c, string $a, array $rules): bool
    {
        if (in_array($c. '/'.$a, $rules) || in_array($c.'/*', $rules)) {
            return true;
        }
        return false;
    }

    /**
     * Check if the params are in request.
     *
     * @param array $params
     * @return bool
     */
    private function matchParams(array $params): bool
    {
        foreach ($params as $key => $value) {
            $has = $this->request->input($key,'');
            if ($has !== $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * Parse action and get params.
     *
     * @param string $action
     * @return array
     */
    private function parseAction(string $action): array
    {
        if (empty($action)) {
            $action = 'index';
        }
        $params = [];
        $pos = strpos($action, '?');
        if ($pos !== false) {
            $action = substr($action, 0, $pos);
            $query = substr($action, $pos + 1);
            parse_str($query, $params);
        }
        return [
            'action' => $action,
            'params' => $params
        ];
    }
}