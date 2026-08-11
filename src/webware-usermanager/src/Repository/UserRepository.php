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
use Closure;
use DateTimeImmutable;
use Monolog\Level;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Exception\ExceptionInterface;
use PhpDb\ResultSet\ResultSetInterface;
use PhpDb\Sql;
use PhpDb\Sql\Predicate\PredicateInterface;
use PhpDb\TableGateway\TableGateway;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\CommandBus\CommandInterface;
use Webware\ResultSet\WithRowDataPrototypeInterface;
use Webware\ResultSet\WithRowDataResultSet;
use Webware\UserManager\Auth\AuthenticationResult;
use Webware\UserManager\Auth\AuthenticationStatus;
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
            adapter: $this->adapter,
            resultSetPrototype: new WithRowDataResultSet($this->userPrototype),
        );
    }

    #[\Override]
    public function authenticate(string $credential, ?string $password = null): AuthenticationResult
    {
        $user = $this->findByConfiguredCredential($this->credentialColumn, $credential);

        if ($user === null) {
            $this->dispatcher->dispatch(new LogEvent(LogChannel::Security, Level::Info)->setMessage(
                'Failed login attempt.',
            )
                ->setContext(['credential' => $credential]));
            return new AuthenticationResult(AuthenticationStatus::InvalidCredentials);
        }

        if (! $user->active) {
            $this->dispatcher->dispatch(new LogEvent(LogChannel::Security, Level::Info)->setMessage(
                'Failed login attempt for inactive user: ' . $user->getIdentity(),
            )
                ->setContext(['credential' => $credential]));
            return new AuthenticationResult(AuthenticationStatus::NotActive);
        }

        if (! password_verify($password ?? '', $user->passwordHash)) {
            $this->dispatcher->dispatch(new LogEvent(LogChannel::Security, Level::Info)->setMessage(
                'Failed login attempt for user: ' . $user->getIdentity(),
            )
                ->setContext(['credential' => $credential]));
            return new AuthenticationResult(AuthenticationStatus::InvalidCredentials);
        }

        $this->dispatcher->dispatch(
            new LogEvent(LogChannel::Security, Level::Info)->setMessage(
                $user->firstName . ' ' . $user->lastName . ' authenticated successfully.',
            )
                ->setContext(['identity' => $user->getIdentity()]),
        );

        return new AuthenticationResult(AuthenticationStatus::Success, $user);
    }

    #[\Override]
    public function checkStatus(int $id): bool
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->columns(['active'])->where(['user.id' => $id])->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();
        return (bool) ($row['active'] ?? false);
    }

    #[\Override]
    public function findAll(
        array $selectColumns = [Sql\Select::SQL_STAR],
        PredicateInterface|array|string|Closure|null $where = null,
        ?array $joins = null,
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): (ResultSetInterface&WithRowDataResultSet)|null {
        $sql    = $this->gateway->getSql();
        $select = $sql->select();
        if (null !== $selectColumns) {
            $select->columns($selectColumns);
        }
        if (null !== $where) {
            $select->where($where);
        }
        if (null !== $joins) {
            foreach ($joins as $join) {
                $select->join(
                    $join['table'],
                    $join['on'],
                    $join['columns'] ?? Sql\Select::SQL_STAR,
                    $join['type'] ?? Sql\Select::JOIN_INNER,
                );
            }
        }
        if (null !== $orderBy) {
            $select->order($orderBy);
        }
        if (null !== $limit) {
            $select->limit($limit);
        }
        if (null !== $offset) {
            $select->offset($offset);
        }
        return $this->gateway->selectWith($select);
    }

    #[\Override]
    public function findByEmail(string $email): ?UserInterface
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->where(['user.email' => $email])->limit(1);
        $row    = $this->gateway->selectWith($select)->current();
        return $row;
    }

    #[\Override]
    public function findById(int $id): ?UserInterface
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->where(['user.id' => $id])->limit(1);

        return $this->gateway->selectWith($select)->current();
    }

    #[\Override]
    public function findByVerificationToken(string $token): ?UserInterface
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->where(['user.verificationToken' => $token])->limit(1);

        return $this->gateway->selectWith($select)->current();
    }

    #[\Override]
    public function findRoleIdByName(string $roleName): string
    {
        return $roleName;
    }

    /** @param array<string, mixed> $data */
    #[\Override]
    public function insert(array $data): int
    {
        $sql    = $this->gateway->getSql();
        $insert = $sql->insert()->values($data);

        return $this->gateway->insertWith($insert);
    }

    /**
     * @throws ExceptionInterface
     */
    #[\Override]
    public function save(CommandInterface $command): int
    {
        if (! isset($command->id)) {
            return $this->gateway->insert((array) $command);
        }
        return $this->gateway->update((array) $command, ['id' => $command->id]);
    }

    /** @param array<string, mixed> $data */
    #[\Override]
    public function update(int $id, array $data): int
    {
        $sql    = $this->gateway->getSql();
        $update = $sql->update()->set($data);
        $update->where(['id' => $id]);

        return $this->gateway->updateWith($update);
    }

    private function findByConfiguredCredential(string $column, string $credential): ?UserInterface
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->where([$column => $credential])->limit(1);

        return $this->gateway->selectWith($select)->current();
    }

    private function getRowPrototype(): WithRowDataPrototypeInterface
    {
        return $this->gateway->getResultSetPrototype()->getRowPrototype();
    }
}
