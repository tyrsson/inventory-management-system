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
use Webware\Acl\Admin\Command\SaveRoleCommand;
use Webware\Acl\Repository\RoleRepository;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;

use function array_keys;
use function assert;

final class SaveRoleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RoleRepository $roleRepository,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof SaveRoleCommand);

        // Resolve the synthetic integer PK sent by the form to the role_id string.
        // Both BuildAccessControlMiddleware and this handler call fetchAll() within the
        // same request, which returns rows in stable insertion order.
        $roleNames = array_keys($this->roleRepository->fetchAll());
        $parentId  = $roleNames[$command->parentPk] ?? null;
        $parents   = $parentId !== null ? [$parentId] : [];

        try {
            $this->roleRepository->save($command->roleId, $parents);
        } catch (\Throwable $e) {
            return new CommandResult($command, CommandStatus::Failure, $e);
        }

        return new CommandResult($command, CommandStatus::Success, null);
    }
}
