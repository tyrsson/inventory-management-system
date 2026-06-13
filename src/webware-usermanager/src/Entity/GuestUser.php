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

use DateTimeImmutable;
use Override;
use SensitiveParameter;
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
final class GuestUser implements UserInterface
{
    public const string GUEST_ROLE = 'Guest';

    /**
     * @param string $identity The user's identity string (email or 'guest').
     * @param string[] $roles Role strings; defaults to [self::GUEST_ROLE] if empty.
     * @param array<string, mixed> $details Arbitrary details from the session.
     */
    public function __construct(
        public private(set) int|string|null $id = null {
            get => $this->id ?? null;
            set(int|string|null $value) {
                if ($value === null) {
                    $this->id = null;
                } else {
                    $this->id = is_string($value) ? (int) $value : $value;
                }
            }
        },
        public private(set) string|array|null $roleId = null {
            get => $this->roleId ?? '';
            set(string|array|null $value) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $this->roleId = is_array($decoded) ? $decoded : [];
                } else {
                    $this->roleId = $value;
                }
            }
        },
        public private(set) ?string $firstName = null,
        public private(set) ?string $lastName = null,
        public private(set) ?string $email = null {
            get => $this->email ?? '';
            set(?string $value) {
                $this->email = $value !== null ? strtolower($value) : null;
            }
        },
        #[SensitiveParameter]
        public private(set) ?string $passwordHash = null,
        public private(set) ?bool $active = null {
            get => $this->active ?? false;
            set(?bool $value) {
                $this->active = $value;
            }
        },
        public private(set) DateTimeImmutable|string|null $createdAt = null {
            get => $this->createdAt ?? new DateTimeImmutable();
            set(DateTimeImmutable|string|null $value) {
                if (is_string($value)) {
                    $this->createdAt = new DateTimeImmutable($value);
                } else {
                    $this->createdAt = $value;
                }
            }
        },
        #[SensitiveParameter]
        public private(set) ?string $verificationToken = null,
        public private(set) DateTimeImmutable|string|null $tokenCreatedAt = null {
            get => $this->tokenCreatedAt ?? new DateTimeImmutable();
            set(DateTimeImmutable|string|null $value) {
                if (is_string($value)) {
                    $this->tokenCreatedAt = new DateTimeImmutable($value);
                } else {
                    $this->tokenCreatedAt = $value;
                }
            }
        },
        /** @var array<string, mixed>|null */
        public private(set) ?array $details = null {
            get => $this->details ?? [];
            set(?array $value) {
                $this->details = $value;
            }
        },
    ) {}

    #[Override]
    public function getIdentity(): string
    {
        return $this->email ?? 'guest';
    }

    /** @return string[] */
    #[Override]
    public function getRoles(): array
    {
        return [self::GUEST_ROLE];
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
        return $this->details ?? [];
    }

    /**
     * RoleInterface — returns the first role string as the ACL role ID.
     */
    #[Override]
    public function getRoleId(): string
    {
        return self::GUEST_ROLE;
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
