<?php

declare(strict_types=1);

namespace Ims\Manifest\Container;

use Webware\Core\Configuration as Config;

final readonly class Configuration extends Config
{
    public const string CONFIG_KEY = 'ims.manifest';

    public const string ROUTE_SEGMENT_VALUE     = self::CONFIG_KEY;
    public const string ROUTE_NAME_PREFIX_VALUE = self::CONFIG_KEY . '.';
}
