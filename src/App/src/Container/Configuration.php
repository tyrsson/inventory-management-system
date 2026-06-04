<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Container;

use Webware\Core\Configuration as Config;

final readonly class Configuration extends Config
{
    public const string CONFIG_KEY = 'app';

    public const string ROUTE_SEGMENT_VALUE = self::CONFIG_KEY;

    public const string ROUTE_NAME_PREFIX_VALUE = self::CONFIG_KEY . '.';
}
