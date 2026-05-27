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

namespace App\CommandBus\Middleware;

use Axleus\Log\Event\LogEvent;
use Axleus\Log\LogChannel;
use Monolog\Level;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\MiddlewareInterface;

use function sprintf;

final class CommandLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    #[Override]
    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler,
    ): CommandResultInterface {
        // At this point in the pipeline $command is a CommandResult
        // (passed forward by CommandHandlerMiddleware)
        if ($command instanceof CommandResultInterface) {
            $level   = $command->getStatus() === CommandStatus::Success ? Level::Info : Level::Error;
            $name    = $command->getCommand()::class;
            $message = $command->getStatus() === CommandStatus::Success
                ? sprintf('Command succeeded: %s', $name)
                : sprintf('Command failed: %s — %s', $name, (string) $command->getResult());

            $event = (new LogEvent(LogChannel::Audit, $level))
                ->setMessage($message)
                ->setContext(['command' => $name, 'status' => $command->getStatus()->name]);

            $this->dispatcher->dispatch($event);
        }

        return $handler->handle($command);
    }
}
