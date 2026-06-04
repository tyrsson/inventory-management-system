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

namespace Ims\Manifest\Container;

use Ims\Manifest\RouteProvider;
use Psr\Container\ContainerInterface;

final class RouteProviderFactory
{
    public function __invoke(ContainerInterface $container): RouteProvider
    {
        return new RouteProvider(
            routeSegment: Configuration::getRouteSegment($container, self::class),
            routeNamePrefix: Configuration::getRouteNamePrefix($container, self::class),
        );
    }
}
