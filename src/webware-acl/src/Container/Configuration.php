<?php

declare(strict_types=1);

namespace Webware\Acl\Container;

use Webware\Acl\AclInterface;
use Webware\Core\Configuration as Config;

final readonly class Configuration extends Config
{
    public const string CONFIG_KEY = AclInterface::class;

    public const string ADMIN_ROUTE_SEGMENT_VALUE     = 'acl.manager';
    public const string ADMIN_ROUTE_NAME_PREFIX_VALUE = 'acl.manager.';
}
