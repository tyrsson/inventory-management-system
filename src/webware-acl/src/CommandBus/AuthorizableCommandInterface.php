<?php

declare(strict_types=1);

namespace Webware\Acl\CommandBus;

use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Webware\Acl\PrivilegeInterface;
use Webware\Acl\RoleProviderInterface;
use Webware\CommandBus\CommandInterface;

interface AuthorizableCommandInterface extends
    CommandInterface,
    RoleProviderInterface,
    ResourceInterface,
    PrivilegeInterface {}
