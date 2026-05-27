<?php

declare(strict_types=1);

namespace Webware\Acl;

use Laminas\Permissions\Acl\Role\RoleInterface;

interface RoleProviderInterface
{
    public function getRole(): RoleInterface;
}
