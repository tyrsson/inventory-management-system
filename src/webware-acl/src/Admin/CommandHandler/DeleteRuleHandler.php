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
use Webware\Acl\Admin\Command\DeleteRuleCommand;
use Webware\Acl\Repository\RuleRepository;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;

use function assert;

final class DeleteRuleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RuleRepository $ruleRepository,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof DeleteRuleCommand);

        try {
            $deleted = $this->ruleRepository->delete($command->roleId, $command->resourceId);
        } catch (Throwable $e) {
            return new CommandResult($command, CommandStatus::Failure, $e);
        }

        return new CommandResult($command, $deleted ? CommandStatus::Success : CommandStatus::Failure, null);
    }
}
