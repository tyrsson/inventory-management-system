<?php

declare(strict_types=1);

/**
 * This file is part of the Webware\UserManager package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\UserManager\Middleware;

use Mezzio\Session\RetrieveSession;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;
use Webware\UserManager\UserInterface;

use function is_array;

/**
 * Resolves the current identity and attaches a UserInterface to every request.
 *
 * Reads the session written by LoginMiddleware. If session data is present and
 * valid, calls the user factory to reconstruct the authenticated User. Otherwise
 * creates a GuestUser for the request.
 *
 * Always calls the next handler — access decisions are AuthorizationMiddleware's job.
 * Pipe this once in the global pipeline, after SessionMiddleware.
 */
final class IdentityMiddleware implements MiddlewareInterface
{
    /** @var callable(array): UserInterface */
    private $userFactory;

    /**
     * @param callable(array): UserInterface $userFactory
     */
    public function __construct(
        private UserRepositoryInterface $repository,
        callable $userFactory,
        private array $config,
    ) {
        $this->userFactory = $userFactory;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session  = RetrieveSession::fromRequestOrNull($request);
        $userInfo = $session?->get(UserInterface::class);

        if (null !== $userInfo) {
            
            $check = $this->repository->checkStatus($userInfo['id']);
            if ($check) {
                $user = ($this->userFactory)($userInfo);
            } else {
                $session?->clear();
                $user = ($this->userFactory)(['roleId' => UserInterface::GUEST_ROLE]);
            }
        } else {
            $user = ($this->userFactory)(['roleId' => UserInterface::GUEST_ROLE]);
        }

        return $handler->handle($request->withAttribute(UserInterface::class, $user));
    }
}
