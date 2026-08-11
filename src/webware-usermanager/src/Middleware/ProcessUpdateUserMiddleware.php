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

namespace Webware\UserManager\Middleware;

use Axleus\Message\SystemMessengerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;
use Webware\UserManager\Command\UpdateUserCommand;
use Webware\UserManager\InputFilter\UserDataFilter;
use Webware\UserManager\InputFilter\ValidationGroupTrait;

use function array_merge;

final readonly class ProcessUpdateUserMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;
    use ValidationGroupTrait;

    public function __construct(
        private CommandBusInterface $commandBus,
        private UserDataFilter $filter,
    ) {}

    public function processPatch(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);
        $data      = array_merge(
            $request->getParsedBody(),
            ['id' => $request->getAttribute('id')],
        );

        $this->filter->setValidationGroup(self::UPDATE_VALIDATION_GROUP);
        $this->filter->setData($data);
        if (! $this->filter->isValid()) {
            $messenger?->warning($this->filter->getSystemMessage());
            return $handler->handle($request);
        }
        $command = new UpdateUserCommand(...$this->filter->getValues());

        $result = $this->commandBus->handle($command);

        if ($result->getStatus() === CommandStatus::Success) {
            $messenger?->success('User updated.', hops: 0, now: true);
        } else {
            $messenger?->danger('User could not be updated. Please try again.', hops: 0, now: true);
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
