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
use Laminas\InputFilter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Admin\Command\DeleteRuleCommand;
use Webware\Acl\Admin\Command\SaveRuleCommand;
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\Acl\InputFilter\RuleDataFilter;
use Webware\Acl\RuleType;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;

use function is_array;

final readonly class ProcessRuleMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(
        private CommandBusInterface $commandBus,
    ) {}

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        /** @var SystemMessengerInterface|null $messenger */
        $messenger     = $request->getAttribute(SystemMessengerInterface::class);
        $filterManager = $request->getAttribute(InputFilter\InputFilterPluginManager::class);
        $filter        = $filterManager->get(RuleDataFilter::class);
        $filter->setValidationGroup([
            'roleId',
            'resourceId',
            'type',
            'assertions',
        ]);
        $filter->setData($request->getParsedBody());

        if (! $filter->isValid()) {
            $messenger?->warning($filter->getSystemMessage());

            return $handler->handle($request);
        }

        $filteredData = $filter->getValues();

        $result = $this->commandBus->handle(
            new SaveRuleCommand(...$filteredData)
        );

        if ($result->getStatus() === CommandStatus::Success) {
            $messenger?->success('Rule saved.');
        } else {
            $messenger?->warning('Rule could not be saved. Please try again.');
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }

    public function processPatch(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        /** @var SystemMessengerInterface|null $messenger */
        $messenger     = $request->getAttribute(SystemMessengerInterface::class);
        $filterManager = $request->getAttribute(InputFilter\InputFilterPluginManager::class);
        $filter        = $filterManager->get(RuleDataFilter::class);
        $filter->setValidationGroup([
            'roleId',
            'resourceId',
            'type',
        ]);
        $filter->setData($request->getParsedBody());

        if (! $filter->isValid()) {
            $messenger?->warning($filter->getSystemMessage());

            return $handler->handle($request);
        }

        $filteredData = $filter->getValues();

        $result = $this->commandBus->handle(new UpdateRuleTypeCommand(...$filteredData));
        if ($result->getStatus() === CommandStatus::Success) {
            $messenger?->success('Rule updated.');
        } else {
            $messenger?->warning('Rule update failed. Please try again.');
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }

    public function processDelete(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        /** @var SystemMessengerInterface|null $messenger */
        $messenger     = $request->getAttribute(SystemMessengerInterface::class);
        $filterManager = $request->getAttribute(InputFilter\InputFilterPluginManager::class);
        $filter        = $filterManager->get(RuleDataFilter::class);
        $filter->setValidationGroup([
            'roleId',
            'resourceId',
        ]);
        $filter->setData($request->getAttributes());

        $filteredData = $filter->getValues();

        $result = $this->commandBus->handle(new DeleteRuleCommand(...$filteredData));
        if ($result->getStatus() === CommandStatus::Success) {
            $messenger?->success('Rule deleted.');
        } else {
            $messenger?->warning('Rule could not be deleted. Please try again.');
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
