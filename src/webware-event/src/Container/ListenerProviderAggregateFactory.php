<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Event package.
 *
 * Copyright (c) 2026 Joey (aka Tyrsson) Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Event\Container;

use Phly\EventDispatcher\ListenerProvider\AttachableListenerProvider;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Phly\EventDispatcher\ListenerProvider\PrioritizedListenerProvider;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Webware\Event\ConfigProvider;

use function is_array;
use function is_callable;
use function is_string;

/**
 * @internal
 */
final class ListenerProviderAggregateFactory
{
    public function __invoke(ContainerInterface $container): ListenerProviderAggregate
    {
        /** @var array{listeners?: array<class-string, array<int, array{listener: callable|class-string, priority?: int}>>, listener_providers?: array<class-string>} $config */
        $config              = $container->get('config');
        $listeners           = $config[ConfigProvider::LISTENER_KEY]          ?? [];
        $listenerProviders   = $config[ConfigProvider::LISTENER_PROVIDER_KEY] ?? [];
        $prioritizedProvider = $container->get(PrioritizedListenerProvider::class);
        $attachableProvider  = $container->get(AttachableListenerProvider::class);
        $aggregate           = new ListenerProviderAggregate();

        /** @var string $eventType */
        /** @var array<int, array{listener: callable|class-string, priority?: int}|callable|class-string> $spec */
        foreach ($listeners as $eventType => $spec) {
            foreach ($spec as $listener) {
                if (is_string($listener)) {
                    if ($container->has($listener)) {
                        $attachableProvider->listen($eventType, $container->get($listener));
                    } elseif (is_callable($listener)) {
                        $attachableProvider->listen($eventType, $listener);
                    }
                    continue;
                }

                if (is_array($listener)) {
                    $resolvedListener = null;
                    if ($container->has($listener['listener'])) {
                        $resolvedListener = $container->get($listener['listener']);
                    } elseif (is_callable($listener['listener'])) {
                        $resolvedListener = $listener['listener'];
                    } else {
                        continue;
                    }

                    if (isset($listener['priority'])) {
                        $prioritizedProvider->listen($eventType, $resolvedListener, $listener['priority']);
                    } else {
                        $attachableProvider->listen($eventType, $resolvedListener);
                    }
                    continue;
                }
            }
        }

        /** @var string $provider */
        foreach ($listenerProviders as $provider) {
            $providerInstance = $container->has($provider) ? $container->get($provider) : null;
            if ($providerInstance instanceof ListenerProviderInterface) {
                $aggregate->attach($providerInstance);
            }
        }

        $aggregate->attach($prioritizedProvider);
        $aggregate->attach($attachableProvider);

        return $aggregate;
    }
}
