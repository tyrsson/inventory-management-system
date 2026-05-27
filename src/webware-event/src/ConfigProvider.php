<?php

declare(strict_types=1);

namespace Webware\Event;

use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

final class ConfigProvider
{
    public const LISTENER_KEY          = 'listeners';
    public const LISTENER_PROVIDER_KEY = 'listener_providers';

    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases' => [
                EventDispatcherInterface::class  => EventDispatcher::class,
                ListenerProviderInterface::class => ListenerProviderAggregate::class,
            ],
            'factories' => [
                ListenerProviderAggregate::class           => Container\ListenerProviderAggregateFactory::class,
                Middleware\EventDispatcherMiddleware::class => Middleware\EventDispatcherMiddlewareFactory::class,
            ],
        ];
    }
}
