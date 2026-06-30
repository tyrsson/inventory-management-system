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
use PhpDb\ResultSet\ResultSetInterface;
use PhpDb\Sql\Where;
use PhpDb\TableGateway\TableGateway;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\ResultSet\WithRowDataPrototypeInterface;
use Webware\ResultSet\WithRowDataResultSet;
use Webware\UserManager\Auth\AuthenticationResult;
use Webware\UserManager\Auth\AuthenticationStatus;
use Webware\UserManager\Entity\User;
use Webware\UserManager\UserInterface;

use function password_verify;

final class UserRepository implements UserRepositoryInterface
{
    private readonly TableGateway $gateway;

    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly WithRowDataPrototypeInterface $userPrototype,
        private readonly string $credentialColumn,
    ) {
        $this->gateway = new TableGateway(
            table: Schema::User->table(),
            adapter: $adapter,
            resultSetPrototype: new WithRowDataResultSet($userPrototype),
        );
    }

    public function authenticate(string $credential, ?string $password = null): AuthenticationResult
    {
        $user = $this->findByConfiguredCredential($this->credentialColumn, $credential);

        if ($user === null) {
            $this->dispatcher->dispatch(new LogEvent(LogChannel::Security, Level::Info)
                ->setMessage('Failed login attempt.')
                ->setContext(['credential' => $credential]));
            return new AuthenticationResult(AuthenticationStatus::InvalidCredentials);
        }

        if (!$user->active) {
            $this->dispatcher->dispatch(new LogEvent(LogChannel::Security, Level::Info)
                ->setMessage('Failed login attempt for inactive user: ' . $user->getIdentity())
                ->setContext(['credential' => $credential]));
            return new AuthenticationResult(AuthenticationStatus::NotActive);
        }

        if (!password_verify($password ?? '', $user->passwordHash)) {
            $this->dispatcher->dispatch(new LogEvent(LogChannel::Security, Level::Info)
                ->setMessage('Failed login attempt for user: ' . $user->getIdentity())
                ->setContext(['credential' => $credential]));
            return new AuthenticationResult(AuthenticationStatus::InvalidCredentials);
        }

        $this->dispatcher->dispatch(new LogEvent(LogChannel::Security, Level::Info)
            ->setMessage($user->firstName . ' ' . $user->lastName . ' authenticated successfully.')
            ->setContext(['identity' => $user->getIdentity()]));

        return new AuthenticationResult(AuthenticationStatus::Success, $user);
    }

    public function findByEmail(string $email): ?UserInterface
    {
        $sql = $this->gateway->getSql();
        $select = $sql->select()->where(['user.email' => $email])->limit(1);
        $row = $this->gateway->selectWith($select)->current();
        return $row;
    }

    public function findById(int $id): ?UserInterface
    {
        $sql = $this->gateway->getSql();
        $select = $sql->select()->where(['user.id' => $id])->limit(1);

        return $this->gateway->selectWith($select)->current();
    }

    public function findAll(?int $storeId = null): (ResultSetInterface&WithRowDataResultSet)|null
    {
        $sql = $this->gateway->getSql();
        $select = $sql->select()->order('user.lastName ASC');

        if ($storeId !== null) {
            $select->where(['user.storeId' => $storeId]);
        }

        return $this->gateway->selectWith($select);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $sql = $this->gateway->getSql();
        $insert = $sql->insert()->values($data);

        $sql->prepareStatementForSqlObject($insert)->execute();

        return (int) $this->gateway->getAdapter()->getDriver()->getConnection()->getLastGeneratedValue();
    }

    public function save(UserInterface $user): bool
    {
        $result = null;
        if (isset($user->id)) {
            // Update existing user
            return (bool) $this->gateway->update($user->toArray(), ['id' => $user->id]);
        } else {
            // Insert new user
            return (bool) $this->gateway->insert($user->toArray());
        }
    }

    public function checkStatus(int $id): bool
    {
        $sql = $this->gateway->getSql();
        $select = $sql->select()->columns(['active'])->where(['user.id' => $id])->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();
        return (bool) ($row['active'] ?? false);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $sql = $this->gateway->getSql();
        $update = $sql->update()->set($data)->where(['id' => $id]);

        $sql->prepareStatementForSqlObject($update)->execute();
    }

    public function findByVerificationToken(string $token): ?UserInterface
    {
        $sql = $this->gateway->getSql();
        $select = $sql->select()->where(['user.verificationToken' => $token])->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();
        if ($row === null) {
            return null;
        }

        return $row;
    }

    public function findRoleIdByName(string $roleName): string
    {
        return $roleName;
    }

    private function findByConfiguredCredential(string $column, string $credential): ?UserInterface
    {
        $sql = $this->gateway->getSql();
        $select = $sql->select()->where([$column => $credential])->limit(1);

        return $this->gateway->selectWith($select)->current();
    }
}
