<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\CommandBus\Middleware\Container;

use App\CommandBus\Middleware\CommandLoggingMiddleware;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class CommandLoggingMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): CommandLoggingMiddleware
    {
        return new CommandLoggingMiddleware(
            $container->get(EventDispatcherInterface::class),
        );
    }
}
