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

use DateTimeImmutable;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;
use Webware\ResultSet\WithRowDataPrototypeInterface;
use Webware\UserManager\Entity\GuestUser;
use Webware\UserManager\UserInterface;

/**
 * DI factory for the UserInterface::class callable service.
 *
 * Returns a callable that creates either a User or GuestUser instance.
 * When session details contain the fields written by LoginMiddleware, a fully-
 * hydrated User is returned. Otherwise a GuestUser is returned for unauthenticated
 * requests. The discriminator is the presence of 'id', 'role_id', and 'first_name'
 * in $details — fields that can only exist if LoginMiddleware wrote the session.
 */
final class UserFactory
{
    public function __invoke(ContainerInterface $container): callable
    {
        $prototype = $container->get(WithRowDataPrototypeInterface::class);
        $config    = Configuration::getCredentialConfig($container, self::class);
        return static function (array $withData) use ($prototype, $config): UserInterface {
            Assert::isMap($withData);

            if (isset($withData['id'], $withData[$config['username']])) {
                return new $prototype(...$withData);
            }

            return new GuestUser(firstName: 'Guest', roleId: [GuestUser::GUEST_ROLE]);
        };
    }
}
