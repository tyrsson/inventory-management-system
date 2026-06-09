<?php

declare(strict_types=1);

namespace Ims\Store\Entity;

use Ims\Store\UserInterface;
use DateTimeImmutable;
use Override;
use SensitiveParameter;

use function is_array;
use function is_string;
use function json_decode;

final class User implements UserInterface
{
    public function __construct(
        public private(set) int|string $id {
            set(int|string $value) {
                $this->id = (int) $value;
            }
        },
        public private(set) int|string $storeId {
            set(int|string $value) {
                $this->storeId = (int) $value;
            }
        },
        public private(set) string $firstName,
        public private(set) string $lastName,
        public private(set) string $email,
        public private(set) string $passwordHash,
        public private(set) bool $active,
        public private(set) ?string $verificationToken = null,
        public private(set) ?DateTimeImmutable $tokenCreatedAt = null,
        public private(set) string|array $roles = [] {
            set(string|array $value) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $this->roles = is_array($decoded) ? $decoded : [];
                } else {
                    $this->roles = $value;
                }
            }
        },
        public private(set) array $details = [],
    ) {}

    #[Override]
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    #[Override]
    public function getIdentity(): string
    {
        return $this->email;
    }

    #[Override]
    public function getRoles(): iterable
    {
        return $this->roles;
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
    public function getRoleId(): string
    {
        return $this->roles[0] ?? '';
    }

    #[Override]
    public function getResourceId(): string
    {
        return 'user';
    }

    #[Override]
    public function getOwnerId(): int|string
    {
        return $this->id;
    }

}
