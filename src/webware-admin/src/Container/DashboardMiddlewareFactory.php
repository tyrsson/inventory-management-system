<?php

declare(strict_types=1);

namespace Webware\Admin\Container;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\Acl\AclInterface;
use Webware\Admin\Middleware\DashboardMiddleware;

final class DashboardMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): DashboardMiddleware
    {
        return new DashboardMiddleware(
            dispatcher: $container->get(EventDispatcherInterface::class),
            acl: $container->get(AclInterface::class),
        );
    }
}
