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

namespace Webware\UserManager\Middleware\Container;

use Laminas\InputFilter\InputFilterPluginManager;
use Psr\Container\ContainerInterface;
use Webware\CommandBus\CommandBusInterface;
use Webware\UserManager\InputFilter\UserDataFilter;
use Webware\UserManager\Middleware\ProcessUpdateUserMiddleware;

final class ProcessUpdateUserMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): ProcessUpdateUserMiddleware
    {
        $manager = $container->get(InputFilterPluginManager::class);
        return new ProcessUpdateUserMiddleware(
            commandBus: $container->get(CommandBusInterface::class),
            filter: $manager->get(UserDataFilter::class),
        );
    }
}
