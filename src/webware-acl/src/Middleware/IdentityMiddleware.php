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

use Mezzio\Session\RetrieveSession;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
    /** @var callable(string, string[], array<string, mixed>): UserInterface */
    private $userFactory;

    /**
     * @param callable(string, string[], array<string, mixed>): UserInterface $userFactory
     */
    public function __construct(
        callable $userFactory,
    ) {
        $this->userFactory = $userFactory;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session  = RetrieveSession::fromRequestOrNull($request);
        $userInfo = $session?->get(UserInterface::class);

        if (is_array($userInfo) && isset($userInfo['username'])) {
            $user = ($this->userFactory)(
                $userInfo['username'],
                $userInfo['roles']   ?? [],
                $userInfo['details'] ?? [],
            );
        } else {
            $user = ($this->userFactory)('Guest', [], []);
        }

        return $handler->handle($request->withAttribute(UserInterface::class, $user));
    }
}
