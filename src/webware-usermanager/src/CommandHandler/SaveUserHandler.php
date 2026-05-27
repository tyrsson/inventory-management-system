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

namespace Webware\UserManager\CommandHandler;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ramsey\Uuid\Uuid;
use Throwable;
use Webware\UserManager\Command\SaveUserCommand;
use Webware\UserManager\Event\SendVerificationEmailEvent;
use Webware\UserManager\Middleware\RegistrationMiddleware;
use Webware\UserManager\Repository\UserRepositoryInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;

use function json_encode;
use function password_hash;

final class SaveUserHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof SaveUserCommand);

        try {
            $roleId = $this->users->findRoleIdByName(RegistrationMiddleware::DEFAULT_ROLE);
            $token  = Uuid::uuid7()->toString();
            $now   = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $id = $this->users->insert([
                'store_id'           => $command->storeId,
                'role_id'            => json_encode([$roleId]),
                'first_name'         => $command->firstName,
                'last_name'          => $command->lastName,
                'email'              => $command->email,
                'password_hash'      => password_hash($command->password, PASSWORD_DEFAULT),
                'active'             => 0,
                'verification_token' => $token,
                'token_created_at'   => $now,
            ]);

            $this->eventDispatcher->dispatch(new SendVerificationEmailEvent($command, $token));

            return new CommandResult($command, CommandStatus::Success, $token);
        } catch (Throwable $e) {
            return new CommandResult($command, CommandStatus::Failure, $e->getMessage());
        }
    }
}
