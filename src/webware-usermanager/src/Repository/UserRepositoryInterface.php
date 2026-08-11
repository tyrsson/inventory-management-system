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

use Closure;
use PhpDb\ResultSet\ResultSetInterface;
use PhpDb\Sql;
use PhpDb\Sql\Predicate\PredicateInterface;
use Webware\CommandBus\CommandInterface;
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
    public function authenticate(
        string $credential,
        ?string $password = null,
    ): \Webware\UserManager\Auth\AuthenticationResult;

    /**
     * Check if a user is active.
     */
    public function checkStatus(int $id): bool;

    /**
     * Return all users, optionally filtered to a specific store.
     */
    public function findAll(
        array $selectColumns = [Sql\Select::SQL_STAR],
        PredicateInterface|array|string|Closure|null $where = null,
        ?array $joins = null,
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): (ResultSetInterface&WithRowDataResultSet)|null;

    /**
     * Find a user by their email address, or null if not found.
     */
    public function findByEmail(string $email): ?UserInterface;

    /**
     * Find a user by their primary key, or null if not found.
     */
    public function findById(int $id): ?UserInterface;

    /**
     * Find a user by their verification token, or null if not found.
     */
    public function findByVerificationToken(string $token): ?UserInterface;

    /**
     * Return the role identifier string for the given role name.
     */
    public function findRoleIdByName(string $roleName): string;

    /**
     * Persist a new user row and return the generated id.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int;

    public function save(CommandInterface $command): int;

    /**
     * Update an existing user row.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): int;
}
