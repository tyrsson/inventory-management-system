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
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\UserManager\Command\SaveUserCommand;

final class RegistrationMiddleware implements MiddlewareInterface
{
    public const DEFAULT_ROLE = 'Warehouse';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly TemplateRendererInterface $template,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        $errors = [];
        foreach (['firstName', 'lastName', 'email', 'password', 'storeId'] as $field) {
            if (empty($body[$field])) {
                $errors[] = sprintf('Field "%s" is required.', $field);
            }
        }

        if (! empty($body['email']) && ! filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if (! empty($errors)) {
            return new HtmlResponse(
                $this->template->render('user::registration', ['errors' => $errors]),
                422
            );
        }

        $command = new SaveUserCommand(
            firstName: (string) $body['firstName'],
            lastName: (string) $body['lastName'],
            email: (string) $body['email'],
            password: (string) $body['password'],
            storeId: (int) $body['storeId'],
        );

        $result = $this->commandBus->handle($command);

        if ($result->getStatus() === CommandStatus::Failure) {
            return new HtmlResponse(
                $this->template->render('user::registration', ['errors' => [$result->getResult()]]),
                500
            );
        }

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);
        $messenger?->success('Registration successful! Please check your email to verify your account.', hops: 1, now: false);

        return $handler->handle(
            $request->withAttribute('registration_result', $result)
        );
    }
}
