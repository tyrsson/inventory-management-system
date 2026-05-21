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
use Webware\Acl\Admin\Command\DeleteRoleCommand;
use Webware\Acl\AclInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;

final class DeleteRoleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly array $config,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof DeleteRoleCommand);

        // @todo Implement config-driven role delete via ConfigSaveEvent

        return new CommandResult($command, CommandStatus::Success, null);
    }
}
