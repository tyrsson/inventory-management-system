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

namespace Webware\UserManager\Repository;

use PhpDb\ResultSet\ResultSetInterface;
use Webware\ResultSet\WithRowDataPrototypeInterface;
use Webware\ResultSet\WithRowDataResultSet;
use Webware\UserManager\UserInterface;

interface UserRepositoryInterface
{
    /**
     * Authenticate a user by credential and password.
     *
     * A successful authentication always returns a fully-hydrated User entity,
     * or null if the credential/password pair is not valid.
     */
    public function authenticate(string $credential, ?string $password = null): \Webware\UserManager\Auth\AuthenticationResult;

    /**
     * Find a user by their email address, or null if not found.
     */
    public function findByEmail(string $email): ?UserInterface;

    /**
     * Find a user by their primary key, or null if not found.
     */
    public function findById(int $id): ?UserInterface;

    /**
     * Return all users, optionally filtered to a specific store.
     */
    public function findAll(?int $storeId = null): (ResultSetInterface&WithRowDataResultSet)|null;

    /**
     * Persist a new user row and return the generated id.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int;

    public function checkStatus(int $id): bool;

    /**
     * Update an existing user row.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void;

    /**
     * Return the role identifier string for the given role name.
     */
    public function findRoleIdByName(string $roleName): string;

    /**
     * Find a user by their verification token, or null if not found.
     */
    public function findByVerificationToken(string $token): ?UserInterface;
}
