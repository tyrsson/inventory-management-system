<?php

declare(strict_types=1);

/**
 * This file is part of the Webware\Acl package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Acl\Admin\CommandHandler;

use Override;
use Throwable;
use Webware\Acl\Admin\Command\DeleteRoleCommand;
use Webware\Acl\Repository\RoleRepository;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;

use function array_keys;
use function assert;

final class DeleteRoleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RoleRepository $roleRepository,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof DeleteRoleCommand);

        // Resolve the synthetic integer PK to the role_id string.
        $roleNames = array_keys($this->roleRepository->fetchAll());
        $roleId    = $roleNames[$command->rolePk] ?? null;

        if ($roleId === null) {
            return new CommandResult($command, CommandStatus::Failure, null);
        }

        try {
            $this->roleRepository->delete($roleId);
        } catch (Throwable $e) {
            return new CommandResult($command, CommandStatus::Failure, $e);
        }

        return new CommandResult($command, CommandStatus::Success, null);
    }
}
