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

namespace Webware\Acl\CommandBus\Middleware;

use Override;
use Webware\Acl\AclInterface;
use Webware\Acl\CommandBus\AuthorizableCommandInterface;
use Webware\Acl\CommandBus\CommandStatus;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandHandlerResolverInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\MiddlewareInterface;

/**
 * ACL-enforcing override of the upstream CommandHandlerMiddleware.
 *
 * Commands that implement AuthorizableCommandInterface are checked via
 * $acl->isAllowed() before the handler is invoked. A denied command returns
 * a CommandResult with CommandStatus::Forbidden — no exception is thrown.
 *
 * Commands that do not implement AuthorizableCommandInterface are passed
 * through to their handler unchanged.
 */
final readonly class CommandHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CommandHandlerResolverInterface $resolver,
        private AclInterface $acl
    ) {}

    #[Override]
    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler,
    ): CommandResultInterface {
        if (! $command instanceof AuthorizableCommandInterface) {
            return ($this->resolver->resolve($command))->handle($command);
        }

        if (
            $this->acl->isAllowed(
                $command->getRole(),
                $command,
                $command->getPrivilegeId()
            )
        ) {
            return ($this->resolver->resolve($command))->handle($command);
        }

        return new CommandResult($command, CommandStatus::Forbidden, null);
    }
}
