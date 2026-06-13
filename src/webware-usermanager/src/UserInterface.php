<?php

declare(strict_types=1);

namespace Webware\UserManager;

use DatetimeImmutable;
use Laminas\Permissions\Acl\ProprietaryInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;

interface UserInterface extends RoleInterface, ResourceInterface, ProprietaryInterface
{
    public const string DATETIME_FORMAT = 'Y-m-d H:i:s';
    
    /**
     * Get the unique user identity (id, username, email address …)
     */
    public function getIdentity(): ?string;

    /**
     * Get all user roles.
     *
     * @return RoleInterface[]|string[]|null
     */
    public function getRoles(): ?array;

    /**
     * Get a detail $name if present, $default otherwise.
     */
    public function getDetail(string $name, mixed $default = null): mixed;

    /**
     * Get all the details.
     *
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array;

    public function isGuest(): bool;
}
