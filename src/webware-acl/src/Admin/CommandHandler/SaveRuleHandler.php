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
use Webware\Acl\Admin\Command\SaveRuleCommand;
use Webware\Acl\AclInterface;
use Webware\Acl\Container\Configuration;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\ConfigManager\Event\ConfigBustCacheEvent;
use Webware\ConfigManager\Event\ConfigSaveEvent;

use function assert;

final class SaveRuleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly array $config,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof SaveRuleCommand);

        $updatedConfig = [
            'resources' => [$command->resourceId => true],
            $command->type => [$command->roleId => [$command->resourceId => $command->assertions]],
        ];

        $saveEvent = new ConfigSaveEvent(
            target:        AclInterface::class,
            targetFile:    Configuration::LOCAL_CONFIG_FILE,
            updatedConfig: $updatedConfig,
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
