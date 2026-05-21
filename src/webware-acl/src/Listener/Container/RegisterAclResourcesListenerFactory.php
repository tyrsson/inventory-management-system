<?php

declare(strict_types=1);

namespace Webware\Acl\Listener\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Listener\RegisterAclResourcesListener;
use Webware\Acl\Container\Configuration;
use Webware\Admin\Container\Configuration as AdminConfiguration;

use function rtrim;

final readonly class RegisterAclResourcesListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterAclResourcesListener
    {
        $adminRouteNamePrefix = AdminConfiguration::getAdminRouteNamePrefix(
            $container,
            self::class
        );

        $moduleAdminRouteNamePrefix = Configuration::getAdminRouteNamePrefix(
            $container,
            self::class
        );

        $resourceId = rtrim(
            $adminRouteNamePrefix . $moduleAdminRouteNamePrefix,
            '.'
        );

         // The admin route name prefix is the base prefix for all admin route names, e.g. 'admin.'.
         // The module admin route name prefix is the prefix for this module's admin route names, e.g. 'admin.acl.'.
         // We want to use the module admin route name prefix if it is set, otherwise we fall back to the admin route name prefix.

        return new RegisterAclResourcesListener(
            $resourceId,
        );
    }
}
