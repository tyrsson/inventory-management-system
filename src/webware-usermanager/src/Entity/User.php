<?php

declare(strict_types=1);

/**
 * This file is part of the Webware UserManager package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\UserManager\Entity;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Override;
use SensitiveParameter;
use Webware\CommandBus\Command\NamedCommandInterface;
use Webware\CommandBus\Command\NamedCommandTrait;
use Webware\UserManager\UserInterface;

use function array_merge;
use function array_values;
use function is_array;
use function is_string;
use function json_decode;
use function json_validate;
use function password_get_info;
use function password_hash;

use const PASSWORD_DEFAULT;

class User implements UserInterface, NamedCommandInterface
{
    use NamedCommandTrait;

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
        public private(set) array|string|null $roleId = null {
            get => $this->roleId ?? null;
            set(array|string|null $value) {
                if (is_string($value)) {
                    if (json_validate($value)) {
                        $decoded      = json_decode($value, true);
                        $this->roleId = is_array($decoded) ? $decoded : [];
                    } else {
                        $this->roleId = [$value];
                    }
                } else {
                    $this->roleId = $value;
                }
            }
        },
        public private(set) ?string $firstName = null,
        public private(set) ?string $lastName = null,
        public private(set) ?string $email = null {
            get => $this->email;
            set(?string $value) {
                $this->email = $value !== null ? strtolower($value) : null;
            }
        },
        #[SensitiveParameter] public private(set) ?string $passwordHash = null,
        public private(set) int|bool|null $active = null {
            get => $this->active ?? false;
            set(int|bool|null $value) {
                $this->active = (bool) $value;
            }
        },
        public private(set) DateTimeImmutable|array|string|null $createdAt = null {
            get => $this->createdAt ?? new DateTimeImmutable();
            set(DateTimeImmutable|array|string|null $value) {
                if (is_array($value) && isset($value['date'])) {
                    $this->createdAt = new DateTimeImmutable(
                        $value['date'],
                        new DateTimeZone($value['timezone']),
                    );
                } elseif (is_string($value)) {
                    $this->createdAt = new DateTimeImmutable($value);
                } else {
                    $this->createdAt = $value;
                }
            }
        },
        #[SensitiveParameter] public private(set) ?string $verificationToken = null,

        public private(set) DateTimeImmutable|array|string|null $tokenCreatedAt = null {
            get => $this->tokenCreatedAt ?? new DateTimeImmutable();
            set(DateTimeImmutable|array|string|null $value) {
                if (is_array($value) && isset($value['date'])) {
                    $this->tokenCreatedAt = DateTimeImmutable::createFromFormat(
                        self::DATETIME_FORMAT,
                        $value['date'],
                        new DateTimeZone($value['timezone'] ?? 'UTC'),
                    );
                } elseif (is_string($value)) {
                    $this->tokenCreatedAt = new DateTimeImmutable($value);
                } else {
                    $this->tokenCreatedAt = $value;
                }
            }
        },
        /** @var array<string, mixed>|null */
        public private(set) array|string|null $details = null {
            get => $this->details ?? [];
            set(array|string|null $value) {
                if (is_string($value)) {
                    if (json_validate($value)) {
                        $decoded       = json_decode($value, true);
                        $this->details = is_array($decoded) ? $decoded : [];
                    } else {
                        $this->details = [$value];
                    }
                } elseif (is_array($value) || $value === null) {
                    $this->details = $value;
                } else {
                    throw new InvalidArgumentException('$details must be an array, JSON string, or null');
                }
            }
        },
    ) {}

    public function exchangeArray(array $data): array
    {
        throw new \RuntimeException('User entity does not support exchangeArray()');
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

    #[Override]
    public function getIdentity(): ?string
    {
        return $this->email;
    }

    /**
     * Implements ProprietaryInterface — used by the Laminas Ownership assertion.
     * Returns the user's primary key so the assertion can compare
     * $role->getOwnerId() === $resource->getOwnerId().
     */
    #[Override]
    public function getOwnerId(): ?int
    {
        return $this->id;
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

    #[Override]
    public function getRoleId(): array|string|null
    {
        return $this->roleId;
    }

    /** @return RoleInterface[]|null */
    #[Override]
    public function getRoles(): ?array
    {
        return $this->roleId;
    }

    public function toArray(): array
    {
        return (array) $this;
    }

    public function withActive(bool $active): static
    {
        return new static(
            id: $this->id,
            roleId: $this->roleId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            active: $active,
            createdAt: $this->createdAt,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            details: $this->details,
        );
    }

    public function withDetail(string $name, mixed $value): static
    {
        return new static(
            id: $this->id,
            roleId: $this->roleId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            active: $this->active,
            createdAt: $this->createdAt,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            details: array_merge($this->details, [$name => $value]),
        );
    }

    public function withEmail(string $email): static
    {
        return new static(
            id: $this->id,
            roleId: $this->roleId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $email,
            passwordHash: $this->passwordHash,
            active: $this->active,
            createdAt: $this->createdAt,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            details: $this->details,
        );
    }

    public function withFirstName(string $firstName): static
    {
        return new static(
            id: $this->id,
            roleId: $this->roleId,
            firstName: $firstName,
            lastName: $this->lastName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            active: $this->active,
            createdAt: $this->createdAt,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            details: $this->details,
        );
    }

    public function withId(int|string|null $id): static
    {
        return new static(
            id: $id,
            roleId: $this->roleId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            active: $this->active,
            createdAt: $this->createdAt,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            details: $this->details,
        );
    }

    public function withLastName(string $lastName): static
    {
        return new static(
            id: $this->id,
            roleId: $this->roleId,
            firstName: $this->firstName,
            lastName: $lastName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            active: $this->active,
            createdAt: $this->createdAt,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            details: $this->details,
        );
    }

    public function withPasswordHash(string $passwordHash): static
    {
        if (password_get_info($passwordHash)['algo'] === null) {
            $passwordHash = password_hash($passwordHash, PASSWORD_DEFAULT);
        }

        return new static(
            id: $this->id,
            roleId: $this->roleId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $this->email,
            passwordHash: $passwordHash,
            active: $this->active,
            createdAt: $this->createdAt,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            details: $this->details,
        );
    }

    /** @param RoleInterface[]|string[]|string $roleId */
    public function withRoleId(array $roleId): static
    {
        if (is_string($roleId)) {
            $roleId = [$roleId];
        }

        return new static(
            id: $this->id,
            roleId: array_merge($this->roleId, array_values($roleId)),
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            active: $this->active,
            createdAt: $this->createdAt,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            details: $this->details,
        );
    }

    public function withRowData(array $withRowData): static
    {
        return new static(...$withRowData);
    }

    public function __invoke(): UserInterface
    {
        return new static();
    }
}
