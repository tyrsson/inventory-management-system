<?php

declare(strict_types=1);

namespace Webware\Acl\Role;

use Override;
use Webware\UserManager\UserInterface;

final class SingleRoleUserProxy implements UserInterface
{
    public function __construct(
        private readonly UserInterface $user,
        private readonly string $roleId,
    ) {}

    #[Override]
    public function getRoleId(): string
    {
        return $this->roleId;
    }

    #[Override]
    public function getOwnerId(): int
    {
        return $this->user->getOwnerId();
    }

    #[Override]
    public function getIdentity(): string
    {
        return $this->user->getIdentity();
    }

    #[Override]
    public function getRoles(): ?array
    {
        return [$this->roleId];
    }

    #[Override]
    public function getDetail(string $name, mixed $default = null): mixed
    {
        return $this->user->getDetail($name, $default);
    }

    #[Override]
    public function getResourceId(): string
    {
        return $this->user->getResourceId();
    }

    #[Override]
    public function getDetails(): array
    {
        return $this->user->getDetails();
    }

    #[Override]
    public function isGuest(): bool
    {
        return $this->user->isGuest();
    }
}
