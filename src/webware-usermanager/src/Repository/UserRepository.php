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

use Axleus\Log\Event\LogEvent;
use Axleus\Log\LogChannel;
use DateTimeImmutable;
use Monolog\Level;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\TableGateway\TableGateway;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\UserManager\Entity\User;
use Webware\UserManager\UserInterface;

use function password_verify;

final class UserRepository implements UserRepositoryInterface
{
    private readonly TableGateway $gateway;

    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
        $this->gateway = new TableGateway('user', $adapter);
    }

    public function authenticate(string $credential, ?string $password = null): (User&UserInterface)|null
    {
        $user = $this->findByEmail($credential);

        if ($user === null || ! $user->active) {
            return null;
        }

        if (! password_verify($password ?? '', $user->passwordHash)) {
            return null;
        }

        $authenticatedUser = $user;

        $this->dispatcher->dispatch(
            (new LogEvent(LogChannel::Security, Level::Info))
                ->setMessage($user->displayName() . ' authenticated successfully.')
                ->setContext(['identity' => $user->getIdentity()])
        );

        return $authenticatedUser;
    }

    public function findByEmail(string $email): ?User
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()
            ->where(['user.email' => $email])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();
        if ($row === null) {
            return null;
        }

        return $this->hydrate((array) $row);
    }

    public function findById(int $id): ?User
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()
            ->where(['user.id' => $id])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();
        if ($row === null) {
            return null;
        }

        return $this->hydrate((array) $row);
    }

    /** @return User[] */
    public function findAll(?int $storeId = null): array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()
            ->order('user.lastName ASC');

        if ($storeId !== null) {
            $select->where(['user.storeId' => $storeId]);
        }

        $users = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $users[] = $this->hydrate((array) $row);
        }

        return $users;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $sql    = $this->gateway->getSql();
        $insert = $sql->insert()->values($data);

        $sql->prepareStatementForSqlObject($insert)->execute();

        return (int) $this->gateway->getAdapter()->getDriver()->getConnection()->getLastGeneratedValue();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $sql    = $this->gateway->getSql();
        $update = $sql->update()->set($data)->where(['id' => $id]);

        $sql->prepareStatementForSqlObject($update)->execute();
    }

    public function findByVerificationToken(string $token): ?User
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()
            ->where(['user.verificationToken' => $token])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();
        if ($row === null) {
            return null;
        }

        return $this->hydrate((array) $row);
    }

    public function findRoleIdByName(string $roleName): string
    {
        return $roleName;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            storeId: (int) $row['storeId'],
            firstName: (string) $row['firstName'],
            lastName: (string) $row['lastName'],
            email: (string) $row['email'],
            passwordHash: (string) $row['passwordHash'],
            active: (bool) $row['active'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            verificationToken: isset($row['verificationToken']) ? (string) $row['verificationToken'] : null,
            tokenCreatedAt: isset($row['tokenCreatedAt']) ? new DateTimeImmutable((string) $row['tokenCreatedAt']) : null,
            roles: $row['roleId'],
            details: [
                'id'      => (int) $row['id'],
                'storeId' => (int) $row['storeId'],
            ],
        );
    }
}
