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

namespace Webware\UserManager\CommandHandler\Container;

use Psr\Container\ContainerInterface;
use Webware\UserManager\CommandHandler\UpdateUserHandler;
use Webware\UserManager\Repository\UserRepositoryInterface;

final class UpdateUserHandlerFactory
{
    public function __invoke(ContainerInterface $container): UpdateUserHandler
    {
        return new UpdateUserHandler(users: $container->get(UserRepositoryInterface::class));
    }
}
