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
use Webware\Acl\Admin\Command\SaveRuleCommand;
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;

use function in_array;

final class ProcessRuleMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        return $this->persistRule($request, $handler);
    }

    public function processPatch(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $id   = (int)    $request->getAttribute('id');
        $body = (array)  $request->getParsedBody();
        $type = (string) ($body['type'] ?? 'allow');

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new UpdateRuleTypeCommand(0, 'allow'), CommandStatus::Failure, null);

        if ($id > 0 && in_array($type, ['allow', 'deny'], true)) {
            $result = $this->commandBus->handle(new UpdateRuleTypeCommand($id, $type));
            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success('Rule updated.');
            }
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }

    private function persistRule(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $body        = (array) $request->getParsedBody();
        $rolePk      = (int)    ($body['role_pk']      ?? 0);
        $resourcePk  = (int)    ($body['resource_pk']  ?? 0);
        $privilegePk = (int)    ($body['privilege_pk'] ?? 0);
        $type        = (string) ($body['type']          ?? 'allow');

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new SaveRuleCommand(0, 0, 0, 'allow'), CommandStatus::Failure, null);

        if ($rolePk > 0 && $resourcePk > 0 && $privilegePk > 0) {
            $result = $this->commandBus->handle(new SaveRuleCommand($rolePk, $resourcePk, $privilegePk, $type));
            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success('Rule saved.');
            }
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
