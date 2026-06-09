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

namespace Webware\UserManager\Entity;

use DateTimeImmutable;
use Override;
use SensitiveParameter;
use Webware\UserManager\UserInterface;

use function array_merge;
use function array_values;
use function is_array;
use function is_string;
use function json_decode;

final class User implements UserInterface
{
    public function __construct(
        public readonly int $id,
        public readonly int $storeId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        #[SensitiveParameter]
        public readonly string $passwordHash,
        public readonly bool $active,
        public readonly DateTimeImmutable $createdAt,
        #[SensitiveParameter]
        public readonly ?string $verificationToken = null,
        public readonly ?DateTimeImmutable $tokenCreatedAt = null,
        /** @var string[] */
        public string|array $roles = [] {
            set(string|array $value) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $this->roles = is_array($decoded) ? $decoded : [];
                } else {
                    $this->roles = $value;
                }
            }
        },
        /** @var array<string, mixed> */
        private readonly array $details = [],
    ) {}

    public function displayName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    #[Override]
    public function getIdentity(): string
    {
        return $this->email;
    }

    /**
     * Implements ResourceInterface — identifies this object as the 'user' ACL resource.
     * Allows $acl->isAllowed($role, $userEntity, $privilege) calls.
     */
    #[Override]
    public function getResourceId(): string
    {
        return 'user';
    }

    /**
     * Implements ProprietaryInterface — used by the Laminas Ownership assertion.
     * Returns the user's primary key so the assertion can compare
     * $role->getOwnerId() === $resource->getOwnerId().
     */
    #[Override]
    public function getOwnerId(): int
    {
        return $this->id;
    }

    #[Override]
    public function getRoleId(): string
    {
        return $this->roles[0] ?? '';
    }

    /** @return string[] */
    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[Override]
    public function isGuest(): bool
    {
        return false;
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

    public function withStoreId(int $storeId): self
    {
        return new self(
            $this->id,
            $storeId,
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->passwordHash,
            $this->active,
            $this->createdAt,
            $this->verificationToken,
            $this->tokenCreatedAt,
            $this->roles,
            $this->details,
        );
    }

    public function withFirstName(string $firstName): self
    {
        return new self(
            $this->id,
            $this->storeId,
            $firstName,
            $this->lastName,
            $this->email,
            $this->passwordHash,
            $this->active,
            $this->createdAt,
            $this->verificationToken,
            $this->tokenCreatedAt,
            $this->roles,
            $this->details,
        );
    }

    public function withLastName(string $lastName): self
    {
        return new self(
            $this->id,
            $this->storeId,
            $this->firstName,
            $lastName,
            $this->email,
            $this->passwordHash,
            $this->active,
            $this->createdAt,
            $this->verificationToken,
            $this->tokenCreatedAt,
            $this->roles,
            $this->details,
        );
    }

    public function withEmail(string $email): self
    {
        return new self(
            $this->id,
            $this->storeId,
            $this->firstName,
            $this->lastName,
            $email,
            $this->passwordHash,
            $this->active,
            $this->createdAt,
            $this->verificationToken,
            $this->tokenCreatedAt,
            $this->roles,
            $this->details,
        );
    }

    public function withPasswordHash(string $passwordHash): self
    {
        return new self(
            $this->id,
            $this->storeId,
            $this->firstName,
            $this->lastName,
            $this->email,
            $passwordHash,
            $this->active,
            $this->createdAt,
            $this->verificationToken,
            $this->tokenCreatedAt,
            $this->roles,
            $this->details,
        );
    }

    public function withActive(bool $active): self
    {
        return new self(
            $this->id,
            $this->storeId,
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->passwordHash,
            $active,
            $this->createdAt,
            $this->verificationToken,
            $this->tokenCreatedAt,
            $this->roles,
            $this->details,
        );
    }

    /** @param string[] $roles */
    public function withRoles(array $roles): self
    {
        return new self(
            $this->id,
            $this->storeId,
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->passwordHash,
            $this->active,
            $this->createdAt,
            $this->verificationToken,
            $this->tokenCreatedAt,
            array_values($roles),
            $this->details,
        );
    }

    public function withDetail(string $name, mixed $value): self
    {
        return new self(
            $this->id,
            $this->storeId,
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->passwordHash,
            $this->active,
            $this->createdAt,
            $this->verificationToken,
            $this->tokenCreatedAt,
            $this->roles,
            array_merge($this->details, [$name => $value]),
        );
    }
}
