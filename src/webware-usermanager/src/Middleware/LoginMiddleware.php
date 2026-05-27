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
        $email    = $params['email'] ?? null;
        $password = $params['password'] ?? null;

        if ($email === null || $password === null) {
            return $handler->handle($request);
        }

        $user = $this->repository->authenticate($email, $password);

        if ($user === null) {
            $this->logger->info('Failed login attempt', ['email' => $email]);
            $messenger = $request->getAttribute(SystemMessengerInterface::class);
            $messenger?->error('Invalid email or password.');
            return $handler->handle($request);
        }

        $session = RetrieveSession::fromRequest($request);
        $session->set(UserInterface::class, [
            'username' => $user->getIdentity(),
            'roles'    => $user->getRoles(),
            'details'  => [
                'id'                 => $user->id,
                'store_id'           => $user->storeId,
                'first_name'         => $user->firstName,
                'last_name'          => $user->lastName,
                'active'             => $user->active,
                'created_at'         => $user->createdAt->format('Y-m-d H:i:s'),
                'verification_token' => $user->verificationToken,
                'token_created_at'   => $user->tokenCreatedAt?->format('Y-m-d H:i:s'),
                'password_hash'      => $user->passwordHash,
            ],
        ]);
        $session->regenerate();

        return new RedirectResponse($this->redirectUrl);
    }
}
