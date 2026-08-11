<?php

declare(strict_types=1);

namespace Webware\UserManager;

use DatetimeImmutable;
use Laminas\Permissions\Acl\ProprietaryInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Webware\ResultSet\WithRowDataPrototypeInterface;

interface UserInterface extends RoleInterface, ResourceInterface, ProprietaryInterface, WithRowDataPrototypeInterface
{
    final public const string GUEST_ROLE = 'Guest';
    public const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    public int|string|null $id { get; }

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
     * Create a new instance of this user with the given id.
     * Allows for a user to be created without an id,
     * and then have the id set after persisting to the database.
     *
     * @return static
     */
    public function withId(int|string|null $id): static;
}
