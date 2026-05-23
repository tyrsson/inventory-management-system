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

namespace Webware\Acl\Middleware;

use Mezzio\Authentication\AuthenticationInterface;
use Webware\UserManager\UserInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves the current identity and attaches a UserInterface to every request.
 *
 * Delegates to AuthenticationInterface::authenticate() which handles both:
 *  - POST /login with credentials → verifies, writes session, returns real user
 *  - Any request with a valid session → reads session, returns real user
 *  - No credentials / no session → returns null → guest user set
 *
 * Always calls the next handler — access decisions are AuthorizingDispatchMiddleware's job.
 * Pipe this once in the global pipeline, after SessionMiddleware.
 */
final class IdentityMiddleware implements MiddlewareInterface
{
    /** @var callable(string, string[], array<string, mixed>): UserInterface */
    private $userFactory;

    /**
     * @param callable(string, string[], array<string, mixed>): UserInterface $userFactory
     */
    public function __construct(
        callable $userFactory,
        private readonly AuthenticationInterface $auth,
    ) {
        $this->userFactory = $userFactory;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $mezzioUser = $this->auth->authenticate($request);

        if ($mezzioUser === null) {
            return $handler->handle(
                $request->withAttribute(
                    UserInterface::class,
                    ($this->userFactory)('Guest', [], []),
                )
            );
        }

        return $handler->handle(
            $request->withAttribute(
                UserInterface::class,
                ($this->userFactory)(
                    $mezzioUser->getIdentity(),
                    $mezzioUser->getRoles(),
                    $mezzioUser->getDetails(),
                ),
            )
        );
    }
}
