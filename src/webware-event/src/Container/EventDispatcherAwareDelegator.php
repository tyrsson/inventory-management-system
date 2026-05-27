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

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\Event\EventDispatcherAwareInterface;

use function assert;

final class EventDispatcherAwareDelegator
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        callable $callback,
    ): object {
        $serviceInstance = $callback();
        if (! $serviceInstance instanceof EventDispatcherAwareInterface) {
            return $serviceInstance;
        }
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        assert($eventDispatcher instanceof EventDispatcherInterface);
        $serviceInstance->setEventDispatcher($eventDispatcher);

        return $serviceInstance;
    }
}
