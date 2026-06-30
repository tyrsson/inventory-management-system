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
use Throwable;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\UserManager\Command\UpdateUserCommand;
use Webware\UserManager\Repository\UserRepositoryInterface;

use function json_encode;

final class UpdateUserHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof UpdateUserCommand);

        $user = $this->users->findById($command->id);

        if ($user === null) {
            return new CommandResult($command, CommandStatus::Failure, 'User not found.');
        }

        try {
            $this->users->update($command->id, [
                'firstName' => $command->firstName,
                'lastName' => $command->lastName,
                'email' => $command->email,
                'roleId' => json_encode($command->roleId),
                'active' => $command->active ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            return new CommandResult($command, CommandStatus::Failure, $e->getMessage());
        }

        return new CommandResult($command, CommandStatus::Success, $this->users->findById($command->id));
    }
}
