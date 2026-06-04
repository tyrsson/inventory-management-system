<?php

declare(strict_types=1);

namespace Webware\Admin\Container;

use Psr\Container\ContainerInterface;
use Webware\Admin\RouteProvider;

final readonly class RouteProviderFactory
{
    public function __invoke(ContainerInterface $container): RouteProvider
    {
        $adminRouteSegment    = Configuration::getAdminRouteSegment($container, self::class);
        $adminRouteNamePrefix = Configuration::getAdminRouteNamePrefix($container, self::class);

        // The admin route segment is the base segment for all admin routes, e.g. 'admin'.
        // The admin route name prefix is the base prefix for all admin route names, e.g. 'admin.'.
        return new RouteProvider(
            $adminRouteSegment,
            $adminRouteNamePrefix
        );
    }
}
