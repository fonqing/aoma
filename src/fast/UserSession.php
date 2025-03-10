<?php

namespace aoma\fast;

use aoma\exception\BusinessException;

/**
 * UserSession class、
 *
 * @author Eric Wang, <fonqing@gmail.com>
 * @version 1.0.0
 */
class UserSession
{
    /**
     * @var BaseModel $user
     * @var UserInterface $user
     */
    private mixed $user;

    public function __construct(){}

    /**
     * @throws BusinessException
     */
    private function checkUserModel(mixed $user): void
    {
        if(empty($user)){
            return;
        }
        if (!($user instanceof BaseModel)) {
            throw new BusinessException("Model must be an instance of BaseModel");
        }
        if (!method_exists($user, 'getPrivileges')) {
            throw new BusinessException("Auth Model must implements \\aoma\\fast\\UserInterface");
        }
        if (!method_exists($user, 'isSuperAdmin')) {
            throw new BusinessException("Auth Model must implements \\aoma\\fast\\UserInterface");
        }
        $this->user = $user;
    }

    /**
     * Set current user model
     *
     * @param mixed $user
     * @throws BusinessException
     */
    public function set(mixed $user): void
    {
        $this->checkUserModel($user);
    }

    /**
     * Get the user model
     *
     * @return BaseModel|null
     */
    public function getUser(): ?BaseModel
    {
        return $this->user;
    }

    /**
     * Check if current user is logged in
     *
     * @return bool
     */
    public function isLogin(): bool
    {
        return !empty($this->user);
    }

    /**
     * 超级用户判断
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        if (!$this->isLogin()) {
            return false;
        }
        if(!$this->user) {
            return false;
        }
        return $this->user->isSuperAdmin() ?? false;
    }

    /**
     * Get current user's primary id
     * @throws BusinessException
     */
    public function getUserId(): string|int
    {
        if (!$this->isLogin()) {
            return 0;
        }
        $data = $this->getUserInfo();
        $pk = $this->user->getPk();
        if(is_string($pk)) {
            return $data[$pk] ?? 0;
        }
        throw new BusinessException('Unsupported Union primary key');
    }

    /**
     * Get current user's information array
     *
     * @return array
     */
    public function getUserInfo(): array
    {
        return $this->user->toArray();
    }

    /**
     * Get current user's privileges array
     *
     * @param string $module
     * @return array
     */
    public function getPrivileges(string $module = 'default'): array
    {
        return $this->user->getPrivileges($module);
    }
}