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

use function filter_var;
use function is_array;

use const FILTER_VALIDATE_INT;

final readonly class ProcessUpdateUserMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(
        private CommandBusInterface $commandBus,
    ) {}

    public function processPatch(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);
        $id = filter_var($request->getAttribute('id'), FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        $params = $request->getParsedBody();
        $roleId = $params['roleId'] ?? [];

        $command = new UpdateUserCommand(
            id: $id,
            firstName: $params['firstName'] ?? '',
            lastName: $params['lastName'] ?? '',
            email: $params['email'] ?? '',
            roleId: is_array($roleId) ? $roleId : [$roleId],
            active: isset($params['active']),
        );

        $result = $this->commandBus->handle($command);

        if ($result->getStatus() === CommandStatus::Success) {
            $messenger?->success('User updated.', hops: 0, now: true);
        } else {
            $messenger?->danger('User could not be updated. Please try again.', hops: 0, now: true);
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
