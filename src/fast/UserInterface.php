<?php

namespace aoma\fast;

interface UserInterface
{
    public function getPrivileges(string $module = 'default'): array;
}