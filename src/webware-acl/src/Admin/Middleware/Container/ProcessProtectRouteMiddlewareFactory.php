<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\Middleware\ProcessProtectRouteMiddleware;
use Webware\CommandBus\CommandBusInterface;

final class ProcessProtectRouteMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): ProcessProtectRouteMiddleware
    {
        return new ProcessProtectRouteMiddleware(
            $container->get(CommandBusInterface::class),
        );
    }
}
