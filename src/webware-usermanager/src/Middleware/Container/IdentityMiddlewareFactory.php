<?php

declare(strict_types=1);

/**
 * This file is part of the Webware\UserManager package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\UserManager\Middleware\Container;

use Psr\Container\ContainerInterface;
use Webware\UserManager\Container\Configuration;
use Webware\UserManager\Middleware\IdentityMiddleware;
use Webware\UserManager\Repository\UserRepositoryInterface;
use Webware\UserManager\UserInterface;

final readonly class IdentityMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): IdentityMiddleware
    {
        return new IdentityMiddleware(
            repository: $container->get(UserRepositoryInterface::class),
            userFactory: $container->get(UserInterface::class),
            config: Configuration::getCredentialConfig($container, self::class)
        );
    }
}
