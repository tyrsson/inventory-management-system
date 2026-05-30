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
use Webware\Acl\Admin\Command\DeleteRuleCommand;
use Webware\Acl\Admin\Command\SaveRuleCommand;
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\Acl\RuleType;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;

use function is_array;

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
        $parsed     = $request->getParsedBody();
        $body       = is_array($parsed) ? $parsed : [];
        $roleId     = $body['role_id']    ?? '';
        $resourceId = $body['route_name'] ?? '';
        $type       = $body['rule_type']  ?? RuleType::Allow->value;
        $assertions = null;

        if (isset($body['assertion_alias']) && $body['assertion_alias'] !== '') {
            $assertions = [$body['assertion_alias']];
        }

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new SaveRuleCommand('', '', RuleType::Allow->value), CommandStatus::Failure, null);

        if ($roleId !== '' && $resourceId !== '' && RuleType::tryFrom($type) !== null) {
            $result = $this->commandBus->handle(new SaveRuleCommand($roleId, $resourceId, $type, $assertions));
            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success('Rule saved.');
            } else {
                $messenger?->warning('Rule could not be saved. Please try again.');
            }
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }

    public function processPatch(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $parsed     = $request->getParsedBody();
        $body       = is_array($parsed) ? $parsed : [];
        $roleId     = $body['role_id']    ?? '';
        $resourceId = $body['route_name'] ?? '';
        $type       = $body['type']       ?? RuleType::Allow->value;

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new UpdateRuleTypeCommand('', '', RuleType::Allow->value), CommandStatus::Failure, null);

        if ($roleId !== '' && $resourceId !== '' && RuleType::tryFrom($type) !== null) {
            $result = $this->commandBus->handle(new UpdateRuleTypeCommand($roleId, $resourceId, $type));
            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success('Rule updated.');
            } else {
                $messenger?->warning('Rule update failed. Please try again.');
            }
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }

    public function processDelete(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $roleId     = $request->getAttribute('role_id')     ?? '';
        $resourceId = $request->getAttribute('resource_id') ?? '';

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $result = new CommandResult(new DeleteRuleCommand('', ''), CommandStatus::Failure, null);

        if ($roleId !== '' && $resourceId !== '') {
            $result = $this->commandBus->handle(new DeleteRuleCommand($roleId, $resourceId));
            if ($result->getStatus() === CommandStatus::Success) {
                $messenger?->success('Rule deleted.');
            } else {
                $messenger?->warning('Rule could not be deleted. Please try again.');
            }
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
