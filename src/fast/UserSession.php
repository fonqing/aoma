<?php

namespace aoma\fast;

use support\exception\BusinessException;

/**
 * UserSession class、
 *
 * @author Eric Wang, <fonqing@gmail.com>
 * @version 1.0.0
 */
class UserSession
{
    private ?BaseModel $user;
    private array $userInfo = [];
    private string $idField;

    private array $privileges = [];

    public function __construct()
    {
    }

    /**
     * Set current user model
     *
     * @param mixed $user
     * @throws \Exception
     */
    public function set(mixed $user): void
    {
        if(!($user instanceof BaseModel)) {
            throw new BusinessException("Model must be an instance of BaseModel");
        }
        if(!method_exists($user, 'getPrivileges')) {
            throw new BusinessException("Auth Model must implements \\aoma\\fast\\UserInterface");
        }
        $this->user = $user;
        $this->userInfo = $user->toArray();
        $this->idField = $user->getPk();
        $this->privileges = $user->getPrivileges();
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
        return !is_null($this->user);
    }

    /**
     * Get current user's primary id value
     *
     * @throws \Exception
     */
    public function getUserId()
    {
        if (is_string($this->idField)) {
            return $this->userInfo[$this->idField] ?? '';
        }
        throw new BusinessException("Unsupported union primary key");
    }

    /**
     * Get current user's information array
     *
     * @return array
     */
    public function getUserInfo(): array
    {
        return $this->userInfo;
    }

    /**
     * Get current user's privileges array
     *
     * @param string $module
     * @return array
     */
    public function getPrivileges(string $module = 'default'): array
    {
        return $this->privileges[$module] ?? [];
    }
}