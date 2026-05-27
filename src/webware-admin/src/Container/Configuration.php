<?php

declare(strict_types=1);

namespace Webware\Admin\Container;

use Webware\Admin\AdminInterface;
use Webware\Core\Configuration as Config;

final readonly class Configuration extends Config
{
    public const string CONFIG_KEY = AdminInterface::class;
}
