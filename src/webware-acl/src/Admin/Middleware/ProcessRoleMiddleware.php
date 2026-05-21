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

namespace Webware\Acl\Admin\Middleware;

use Axleus\Message\SystemMessengerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Admin\Command\DeleteRoleCommand;
use Webware\Acl\Admin\Command\SaveRoleCommand;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;

final class ProcessRoleMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        return $this->persistRole($request, $handler);
    }

    public function processPatch(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        return $this->persistRole($request, $handler);
    }

    public function processDelete(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $rolePk = (int) $request->getAttribute('pk');

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new DeleteRoleCommand(0), CommandStatus::Failure, null);

        if ($rolePk > 0) {
            $result = $this->commandBus->handle(new DeleteRoleCommand($rolePk));
            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success('Role deleted.');
            } elseif ($result->getStatus() === CommandStatus::Failure) {
                $messenger?->warning(
                    (string) ($result->getResult() ?? 'Role could not be deleted.'),
                    hops: 0,
                    now: true,
                );
            }
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }

    private function persistRole(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $body     = (array) $request->getParsedBody();
        $roleId   = trim((string) ($body['role_id']   ?? ''));
        $parentPk = (int)         ($body['parent_pk'] ?? 0);

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new SaveRoleCommand('', 0), CommandStatus::Failure, null);

        if ($roleId !== '' && $parentPk > 0) {
            $result = $this->commandBus->handle(new SaveRoleCommand($roleId, $parentPk));
            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success('Role saved.');
            }
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
