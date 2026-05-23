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

namespace Webware\UserManager\Entity;

use Override;
use Webware\UserManager\UserInterface;

/**
 * Lightweight user object for unauthenticated sessions and session-restore.
 *
 * Implements the full Webware\UserManager\UserInterface contract (including
 * RoleInterface, ResourceInterface, and ProprietaryInterface) so it can flow
 * through ACL checks without special-casing.
 *
 * Ownership assertions always return false for a GuestUser because getOwnerId()
 * returns null — the fail-closed OwnershipAssertion denies null owners.
 */
final readonly class GuestUser implements UserInterface
{
    public const string GUEST_ROLE = 'Guest';
    /**
     * @param string   $identity The user's identity string (email or 'guest').
     * @param string[] $roles    Role strings; defaults to [self::GUEST_ROLE] if empty.
     * @param array<string, mixed> $details Arbitrary details from the session.
     */
    public function __construct(
        private string $identity = self::GUEST_ROLE,
        private array $roles = [self::GUEST_ROLE],
        private array $details = [],
    ) {}

    #[Override]
    public function getIdentity(): string
    {
        return $this->identity;
    }

    /** @return string[] */
    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    /** @param mixed $default */
    #[Override]
    public function getDetail(string $name, $default = null): mixed
    {
        return $this->details[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * RoleInterface — returns the first role string as the ACL role ID.
     */
    #[Override]
    public function getRoleId(): string
    {
        return $this->roles[0];
    }

    /**
     * ResourceInterface — identifies this object as the 'user' ACL resource.
     */
    #[Override]
    public function getResourceId(): string
    {
        return 'user';
    }

    /**
     * ProprietaryInterface — always null for unauthenticated users.
     * The fail-closed OwnershipAssertion denies any resource whose owner is null.
     */
    #[Override]
    public function getOwnerId(): mixed
    {
        return null;
    }

    #[Override]
    public function isGuest(): bool
    {
        return true;
    }
}
