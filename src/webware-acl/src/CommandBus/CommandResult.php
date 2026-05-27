<?php

declare(strict_types=1);

namespace Webware\Acl\CommandBus;

use Override;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\CommandInterface;

final readonly class CommandResult implements CommandResultInterface
{
    public function __construct(
        private CommandInterface $command,
        private CommandStatus $status,
        private mixed $result,
    ) {}

    #[Override]
    public function getCommand(): CommandInterface
    {
        return $this->command;
    }

    #[Override]
    public function getStatus(): CommandStatus
    {
        return $this->status;
    }

    #[Override]
    public function getResult(): mixed
    {
        return $this->result;
    }
}
