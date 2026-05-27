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
use Webware\UserManager\UserInterface;
use DateTimeImmutable;
use Webmozart\Assert\Assert;
use Webware\UserManager\Entity\GuestUser;
use Webware\UserManager\Entity\User;

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
        return static function (
            string $identity,
            array $roles = [],
            array $details = [],
        ): UserInterface {
            Assert::allString($roles);
            Assert::isMap($details);

            if (isset($details['id'], $details['store_id'], $details['first_name'])) {
                return new User(
                    id:                $details['id'],
                    storeId:           $details['store_id'],
                    firstName:         $details['first_name'],
                    lastName:          $details['last_name'],
                    email:             $identity,
                    passwordHash:      $details['password_hash'],
                    active:            $details['active'],
                    createdAt:         new DateTimeImmutable($details['created_at']),
                    verificationToken: $details['verification_token'] ?? null,
                    tokenCreatedAt:    isset($details['token_created_at'])
                        ? new DateTimeImmutable($details['token_created_at'])
                        : null,
                    roles:             $roles,
                    details:           $details,
                );
            }

            return new GuestUser($identity, $roles ?: [GuestUser::GUEST_ROLE], $details);
        };
    }
}
