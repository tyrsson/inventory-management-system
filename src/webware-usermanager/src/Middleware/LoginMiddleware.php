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
use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\RetrieveSession;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Webware\UserManager\Auth\AuthenticationStatus;
use Webware\UserManager\Repository\UserRepositoryInterface;
use Webware\UserManager\UserInterface;

final class LoginMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
        private readonly string $redirectUrl,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (RequestMethodInterface::METHOD_POST !== $request->getMethod()) {
            return $handler->handle($request);
        }

        $params   = $request->getParsedBody();
        $email    = $params['email']    ?? null;
        $password = $params['password'] ?? null;

        if ($email === null || $password === null) {
            return $handler->handle($request);
        }
        $result = $this->repository->authenticate($email, $password);

        if (AuthenticationStatus::Success !== $result->status) {
            $this->logger->info('Failed login attempt', ['email' => $email]);
            $messenger = $request->getAttribute(SystemMessengerInterface::class);
            $messenger?->danger('Invalid email or password.');
            if ($result->status === AuthenticationStatus::NotActive) {
                $messenger?->info('Did you activate your account?');
            }

            return $handler->handle($request);
        }

        $session = RetrieveSession::fromRequest($request);
        $session->set(UserInterface::class, $result->user->toArray());

        $session->regenerate();

        return new RedirectResponse($this->redirectUrl);
    }
}
