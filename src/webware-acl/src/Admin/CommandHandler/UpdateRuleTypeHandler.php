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
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\Acl\Repository\RoleRepository;
use Webware\Acl\Repository\RuleRepository;
use Webware\Acl\RuleType;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;

use function assert;

final class UpdateRuleTypeHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RuleRepository $ruleRepository,
        private readonly RoleRepository $roleRepository,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof UpdateRuleTypeCommand);

        $roleId     = $command->roleId;
        $resourceId = $command->resourceId;
        $newType    = RuleType::from($command->newType);
        $oldType    = $newType === RuleType::Allow ? RuleType::Deny : RuleType::Allow;

        try {
            $updated = $this->ruleRepository->updateType($roleId, $resourceId, $newType->value);

            if (! $updated) {
                return new CommandResult($command, CommandStatus::Failure, null);
            }

            // Cascade: children with no explicit rule inherit the parent rule type.
            // Add an explicit old-type rule for each such child so they keep their access.
            foreach ($this->roleRepository->fetchDirectChildren($roleId) as $childRole) {
                if ($this->ruleRepository->findByRoleAndResource($childRole, $resourceId) === null) {
                    $this->ruleRepository->save($oldType->value, $childRole, $resourceId, []);
                }
            }
        } catch (Throwable $e) {
            return new CommandResult($command, CommandStatus::Failure, $e);
        }

        return new CommandResult($command, CommandStatus::Success, null);
    }
}
