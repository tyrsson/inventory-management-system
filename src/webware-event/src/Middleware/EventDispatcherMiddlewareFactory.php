<?php

declare(strict_types=1);

namespace Webware\Event\Middleware;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class EventDispatcherMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): EventDispatcherMiddleware
    {
        return new EventDispatcherMiddleware(
            eventDispatcher: $container->get(EventDispatcherInterface::class),
        );
    }
}
