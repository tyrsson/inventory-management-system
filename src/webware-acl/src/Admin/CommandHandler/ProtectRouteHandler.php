<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\CommandHandler;

use Override;
use Webware\Acl\Admin\Command\ProtectRouteCommand;
use Webware\Acl\AclInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use function assert;

final class ProtectRouteHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly array $config,
        private readonly ?EventDispatcherInterface $events = null,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof ProtectRouteCommand);

        // @todo Implement config-driven route protection:
        //       merge rule into config array and fire ConfigSaveEvent.

        return new CommandResult($command, CommandStatus::Success, null);
    }
}
