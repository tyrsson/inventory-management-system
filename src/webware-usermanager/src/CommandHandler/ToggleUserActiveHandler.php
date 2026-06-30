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
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\UserManager\Command\ToggleUserActiveCommand;
use Webware\UserManager\Repository\UserRepositoryInterface;

final class ToggleUserActiveHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof ToggleUserActiveCommand);

        $user = $this->users->findById($command->id);

        if ($user === null) {
            return new CommandResult($command, CommandStatus::Failure, 'User not found.');
        }

        $this->users->update($command->id, [
            'active' => $user->active ? 0 : 1,
        ]);

        return new CommandResult($command, CommandStatus::Success, $this->users->findById($command->id));
    }
}
