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

namespace Webware\UserManager\RequestHandler;

use Axleus\Message\SystemMessengerInterface;
use Htmx\Attribute;
use Htmx\Response\Header;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Webware\UserManager\UserInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Renders Login page for GET
 * Post Request never reaches this handler as its Handled by
 * the LoginMiddleware. This handler is only for rendering the login page on GET request.
 */
final class LoginHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly TemplateRendererInterface $template,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);

        if (! $user->isGuest()) {
            // Authenticated — redirect; HTMX boosted forms need HX-Redirect
            if ($request->getAttribute(Attribute::Request->value) === true) {
                return new EmptyResponse(200, [Header::Redirect->value => '/']);
            }
            return new RedirectResponse('/');
        }

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);
        $messages  = $messenger?->getMessages() ?? [];

        return new HtmlResponse($this->template->render('user::login', [
            'flashMessages' => $messages,
        ]));
    }
}
