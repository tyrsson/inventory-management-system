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
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\Acl\AclInterface;
use Webware\Acl\Container\Configuration;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\ConfigManager\Event\ConfigBustCacheEvent;
use Webware\ConfigManager\Event\ConfigSaveEvent;

use function array_key_exists;
use function assert;
use function in_array;

final class UpdateRuleTypeHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly array $config,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof UpdateRuleTypeCommand);

        $roleId     = $command->roleId;
        $resourceId = $command->resourceId;
        $newType    = $command->newType;
        $oldType    = $newType === 'allow' ? 'deny' : 'allow';

        $aclConfig  = $this->config;

        // Determine the assertions that were on the old rule (preserve them)
        $assertions = $aclConfig[$oldType][$roleId][$resourceId] ?? [];

        // Remove the old rule entry
        unset($aclConfig[$oldType][$roleId][$resourceId]);
        if (empty($aclConfig[$oldType][$roleId])) {
            unset($aclConfig[$oldType][$roleId]);
        }

        // Add the new rule entry
        $aclConfig[$newType][$roleId][$resourceId] = $assertions;

        // Cascade: for each direct child of $roleId that has no explicit rule
        // for this resource, add an explicit $oldType rule so they keep their access.
        $roles = $aclConfig['roles'] ?? [];
        foreach ($roles as $childRole => $parents) {
            if (! in_array($roleId, (array) $parents, true)) {
                continue;
            }

            $hasExplicitAllow = array_key_exists($resourceId, $aclConfig['allow'][$childRole] ?? []);
            $hasExplicitDeny  = array_key_exists($resourceId, $aclConfig['deny'][$childRole] ?? []);

            if (! $hasExplicitAllow && ! $hasExplicitDeny) {
                $aclConfig[$oldType][$childRole][$resourceId] = [];
            }
        }

        $saveEvent = new ConfigSaveEvent(
            target:        AclInterface::class,
            targetFile:    Configuration::LOCAL_CONFIG_FILE,
            updatedConfig: $aclConfig,
            replace:       true,
        );

        try {
            $this->eventDispatcher->dispatch($saveEvent);
        } catch (\Throwable $e) {
            return new CommandResult($command, CommandStatus::Failure, $e);
        }

        if (! $saveEvent->isPropagationStopped()) {
            $this->eventDispatcher->dispatch(new ConfigBustCacheEvent());
        }

        return new CommandResult($command, CommandStatus::Success, null);
    }
}

