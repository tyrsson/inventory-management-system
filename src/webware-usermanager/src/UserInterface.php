<?php

declare(strict_types=1);

namespace Webware\UserManager;

use Laminas\Permissions\Acl\ProprietaryInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;

interface UserInterface extends
    RoleInterface,
    ResourceInterface,
    ProprietaryInterface
{
    /**
     * Get the unique user identity (id, username, email address …)
     */
    public function getIdentity(): string;

    /**
     * Get all user roles.
     *
     * @return iterable<int|string, string>
     */
    public function getRoles(): iterable;

    /**
     * Get a detail $name if present, $default otherwise.
     *
     * @param mixed $default
     * @return mixed
     */
    public function getDetail(string $name, mixed $default = null): mixed;

    /**
     * Get all the details.
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array;

    public function isGuest(): bool;
}
