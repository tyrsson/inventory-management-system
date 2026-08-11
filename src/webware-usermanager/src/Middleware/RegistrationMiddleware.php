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
use Ramsey\Uuid\Uuid;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\UserManager\Command\CreateUserCommand;
use Webware\UserManager\InputFilter\UserDataFilter;
use Webware\UserManager\InputFilter\ValidationGroupTrait;

use function array_merge;
use function json_encode;

final class RegistrationMiddleware implements MiddlewareInterface
{
    use ValidationGroupTrait;

    const string DEFAULT_ROLE_ID = 'Member';
    const array DEFAULT_ROLE    = ['Member'];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly TemplateRendererInterface $template,
        private readonly UserDataFilter $filter,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = array_merge(
            $request->getParsedBody(),
            [
                'verificationToken' => Uuid::uuid7()->toString(),
                'roleId'            => json_encode(self::DEFAULT_ROLE),
                'active'            => '0',
            ],
        );

        $this->filter->setValidationGroup(self::REGISTRATION_VALIDATION_GROUP);
        $this->filter->setData($data);

        if (! $this->filter->isValid()) {
            return new HtmlResponse(
                $this->template->render('user::registration', ['errors' => $this->filter->getMessages()]),
                422,
            );
        }

        $values = $this->filter->getValues();
        unset($values['confirmPasswordHash']);

        $result = $this->commandBus->handle(
            new CreateUserCommand(...$values),
        );

        if ($result->getStatus() === CommandStatus::Failure) {
            return new HtmlResponse(
                $this->template->render('user::registration', ['errors' => [$result->getResult()]]),
                500,
            );
        }

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);
        $messenger?->success(
            'Registration successful! Please check your email to verify your account.',
            hops: 1,
            now: false,
        );

        return $handler->handle(
            $request->withAttribute(CommandResult::class, $result),
        );
    }
}
