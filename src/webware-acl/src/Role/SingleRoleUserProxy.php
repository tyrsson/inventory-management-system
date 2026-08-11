<?php

declare(strict_types=1);

namespace Webware\Acl\Role;

use Laminas\Permissions\Acl\Role\RoleInterface;
use Override;
use Webware\UserManager\UserInterface;

use function is_string;

final class SingleRoleUserProxy implements UserInterface
{
    public private(set) int|string|null $id = null {
        get => $this->id ?? null;
        set(int|string|null $value) {
            if ($value === null) {
                $this->id = null;
            } else {
                $this->id = is_string($value) ? (int) $value : $value;
            }
        }
    }

    public function __construct(
        private readonly UserInterface $user,
        private readonly RoleInterface|string $roleId,
    ) {}

    #[Override]
    public function exchangeArray(array $array): array
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function getDetail(string $name, mixed $default = null): mixed
    {
        return $this->user->getDetail($name, $default);
    }

    #[Override]
    public function getDetails(): array
    {
        return $this->user->getDetails();
    }

    #[Override]
    public function getIdentity(): string
    {
        return $this->user->getIdentity();
    }

    #[Override]
    public function getOwnerId(): int
    {
        return $this->user->getOwnerId();
    }

    #[Override]
    public function getResourceId(): string
    {
        return $this->user->getResourceId();
    }

    #[Override]
    public function getRoleId(): string
    {
        return is_string($this->roleId) ? $this->roleId : $this->roleId->getRoleId();
    }

    #[Override]
    public function getRoles(): ?array
    {
        return [is_string($this->roleId) ? $this->roleId : $this->roleId->getRoleId()];
    }

    #[Override]
    public function toArray(): array
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function withId(int|string|null $id): static
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function withRowData(array $withRowData): static
    {
        throw new \Exception('Not implemented');
    }
}
