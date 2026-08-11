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

namespace Webware\UserManager\Container;

use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;
use Webware\ResultSet\WithRowDataPrototypeInterface;
use Webware\UserManager\UserInterface;

/**
 * DI factory for the UserInterface::class callable service.
 *
 * Returns a callable that creates a UserInterface implementation from an array of data.
 * The callable is used by IdentityMiddleware to reconstruct the authenticated user from session data.
 */
final class UserFactory
{
    public function __invoke(ContainerInterface $container): callable
    {
        $prototype = $container->get(WithRowDataPrototypeInterface::class);
        $config    = Configuration::getCredentialConfig($container, self::class);
        return static function (array $withData) use ($prototype, $config): UserInterface {
            Assert::isMap($withData);
            return new $prototype(...$withData);
        };
    }
}
