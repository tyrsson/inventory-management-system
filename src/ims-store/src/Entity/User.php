<?php

declare(strict_types=1);

namespace Ims\Store\Entity;

use Ims\Store\UserInterface;
use DateTimeImmutable;
use Override;
use SensitiveParameter;
use Webware\UserManager\Entity\User as WebwareUser;

use function is_array;
use function is_string;
use function json_decode;

final class User implements UserInterface
{
    public private(set) string|int|null $id = null {
        get => $this->id !== null ? (int)$this->id : null;
        set(int|string|null $value) {
            $this->id = $value === null ? null : (int) $value;
        }
    }

    public private(set) ?string $firstName = null {
        get => $this->firstName ?? null;
        set(?string $value) {
            $this->firstName = $value;
        }
    }

    public private(set) ?string $lastName = null {
        get => $this->lastName ?? null;
        set(?string $value) {
            $this->lastName = $value;
        }
    }

    public private(set) ?string $email = null {
        get => $this->email ?? null;
        set(?string $value) {
            $this->email = $value;
        }
    }

    public private(set) ?string $passwordHash = null {
        set(?string $value) {
            $this->passwordHash = $value === null ? null : (string) $value;
        }
    }

    public private(set) bool $active = false;

    public private(set) ?string $verificationToken = null;
    
    public private(set) ?DateTimeImmutable $tokenCreatedAt = null {
        set(DateTimeImmutable|string|null $value) {
            if ($value === null) {
                $this->tokenCreatedAt = null;
            } else {
                $this->tokenCreatedAt = $value instanceof DateTimeImmutable
                    ? $value
                    : new DateTimeImmutable($value);
            }
        }
    }

    public private(set) DateTimeImmutable|string|null $createdAt {
        set(DateTimeImmutable|string|null $value) {
            if ($value === null) {
                $this->createdAt = null;
            } else {
                $this->createdAt = $value instanceof DateTimeImmutable
                    ? $value
                    : new DateTimeImmutable($value);
            }
        }
    }

    public private(set) string|array $roleId = [] {
        set(string|array $value) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $this->roleId = is_array($decoded) ? $decoded : [];
            } else {
                $this->roleId = $value;
            }
        }
    }

    public private(set) array $details = [];

    public function __construct(
        int|string|null $id,
        int|string|null $storeId,
        string $firstName,
        string $lastName,
        string $email,
        #[SensitiveParameter]
        string $passwordHash,
        bool $active,
        DateTimeImmutable|string|null $createdAt,
        #[SensitiveParameter]
        ?string $verificationToken,
        DateTimeImmutable|string|null $tokenCreatedAt,
        string|array $roles,
        array $details,
    ) {}

    #[Override]
    public function getIdentity(): string
    {
        return $this->email;
    }

    #[Override]
    public function getRoles(): array
    {
        return $this->roleId;
    }

    #[Override]
    public function getDetail(string $name, ?mixed $default = null): mixed
    {
        return $this->details[$name] ?? $default;
    }

    #[Override]
    public function getDetails(): array
    {
        return $this->details;
    }

    #[Override]
    public function isGuest(): bool
    {
        return false;
    }

    #[Override]
    public function getRoleId()
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function getResourceId()
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function getOwnerId()
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function withStoreId(int $storeId): self
    {
        return new self(
            id: $this->id,
            roleId: $this->roleId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            active: $this->active,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            createdAt: $this->createdAt,
            details: $this->details,
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
