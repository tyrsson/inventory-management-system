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

namespace Webware\UserManager\Admin\RequestHandler;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\UserManager\UserInterface;

final class ToggleUserActiveHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly TemplateRendererInterface $template,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var CommandResult|null $result */
        $result = $request->getAttribute(CommandResult::class);

        if (!$result instanceof CommandResult || $result->getStatus() !== CommandStatus::Success) {
            return new HtmlResponse('', 422);
        }

        $user = $result->getResult();

        if (!$user instanceof UserInterface) {
            return new HtmlResponse('', 404);
        }

        return new HtmlResponse($this->template->render('user::partials/user-row', [
            'user' => $user,
            'layout' => false,
            'body' => false,
        ]));
    }
}
