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

use Mezzio\Authentication\UserInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;
use Webware\UserManager\Entity\GuestUser;

/**
 * DI factory for the UserInterface::class callable service.
 *
 * Returns a callable that creates GuestUser instances. Unauthenticated users
 * always receive the Guest role — this is not configurable. GuestUser owns
 * the default role via its constructor default.
 */
final class UserFactory
{
    public function __invoke(ContainerInterface $container): callable
    {
        return static function (
            string $identity,
            array $roles = [],
            array $details = [],
        ): UserInterface {
            Assert::allString($roles);
            Assert::isMap($details);

            return new GuestUser($identity, $roles ?: [GuestUser::GUEST_ROLE], $details);
        };
    }
}
