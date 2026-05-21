<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\CommandHandler\Container;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\Acl\Admin\CommandHandler\ProtectRouteHandler;
use Webware\Acl\AclInterface;

final class ProtectRouteHandlerFactory
{
    public function __invoke(ContainerInterface $container): ProtectRouteHandler
    {
        $config = $container->get('config');

        $events = $container->has(EventDispatcherInterface::class)
            ? $container->get(EventDispatcherInterface::class)
            : null;

        return new ProtectRouteHandler(
            config: $config[AclInterface::class] ?? [],
            events: $events,
        );
    }
}
