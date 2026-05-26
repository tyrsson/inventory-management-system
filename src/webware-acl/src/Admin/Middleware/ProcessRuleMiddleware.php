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
        $body       = (array)  $request->getParsedBody();
        $roleId     = (string) ($body['role_id']     ?? '');
        $resourceId = (string) ($body['route_name']  ?? '');
        $type       = (string) ($body['type']        ?? 'allow');

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new UpdateRuleTypeCommand('', '', 'allow'), CommandStatus::Failure, null);

        if ($roleId !== '' && $resourceId !== '' && in_array($type, ['allow', 'deny'], true)) {
            $result = $this->commandBus->handle(new UpdateRuleTypeCommand($roleId, $resourceId, $type));
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
        $body       = (array) $request->getParsedBody();
        $roleId     = $body['role_id']       ?? '';
        $resourceId = $body['route_name']    ?? '';
        $type       = $body['rule_type']     ?? 'allow';
        $assertions = [];

        if (isset($body['assertion_alias']) && $body['assertion_alias'] !== '') {
            $assertions = [$body['assertion_alias']];
        }

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new SaveRuleCommand('', '', 'allow'), CommandStatus::Failure, null);

        if ($roleId !== '' && $resourceId !== '' && in_array($type, ['allow', 'deny'], true)) {
            $result = $this->commandBus->handle(new SaveRuleCommand($roleId, $resourceId, $type, $assertions));
            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success('Rule saved.');
            }
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
