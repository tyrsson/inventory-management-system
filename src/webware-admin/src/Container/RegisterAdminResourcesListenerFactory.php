<?php

declare(strict_types=1);


namespace Webware\Admin\Container;

use Psr\Container\ContainerInterface;
use Webware\Admin\Listener\RegisterAdminResourcesListener;

final class RegisterAdminResourcesListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterAdminResourcesListener
    {
        return new RegisterAdminResourcesListener(
            routeNamePrefix: Configuration::getAdminRouteNamePrefix($container, self::class),
        );
    }
}
