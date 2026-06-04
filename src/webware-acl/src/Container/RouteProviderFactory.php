<?php

declare(strict_types=1);

namespace Webware\Acl\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\RouteProvider;
use Webware\Admin\Container\Configuration as AdminConfiguration;

final readonly class RouteProviderFactory
{
    public function __invoke(ContainerInterface $container): RouteProvider
    {
        $adminBaseRouteSegment = AdminConfiguration::getAdminRouteSegment(
            $container,
            self::class
        );
        $moduleAdminRouteSegment = Configuration::getAdminRouteSegment(
            $container,
            self::class
        );
        $adminBaseRouteNamePrefix = AdminConfiguration::getAdminRouteNamePrefix(
            $container,
            self::class
        );
        $moduleAdminRouteNamePrefix = Configuration::getAdminRouteNamePrefix(
            $container,
            self::class
        );

        // The admin route segment is the base segment for all admin routes, e.g. 'admin'.
        // The module admin route segment is the segment for this module's admin routes, e.g. 'acl'.
        // The admin route name prefix is the base prefix for all admin route names, e.g. 'admin.'.
        // The module admin route name prefix is the prefix for this module's admin route names, e.g. 'admin.acl.'.
        return new RouteProvider(
            $adminBaseRouteSegment . '/' . $moduleAdminRouteSegment,
            $adminBaseRouteNamePrefix . $moduleAdminRouteNamePrefix
        );
    }
}
