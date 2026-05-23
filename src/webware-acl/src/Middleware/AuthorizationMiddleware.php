<?php

declare(strict_types=1);

namespace Webware\Acl\Middleware;

use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Http\RouteResource;
use Webware\Acl\RequestHandler\ForbiddenHandlerInterface;
use Webware\UserManager\UserInterface;

final class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AclInterface $acl,
        private readonly ForbiddenHandlerInterface $forbiddenHandler,
        private readonly array $paramMap = [],
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);

        // Pass unmatched requests straight through to NotFoundHandler.
        // A failed RouteResult has no matched route name, so RouteResource cannot
        // be constructed and there is no ACL resource to protect. This is not a
        // security hole — unregistered paths are never ACL resources, and the
        // NotFoundHandler returns a 404 without serving any application content.
        if ($routeResult === null || $routeResult->isFailure()) {
            return $handler->handle($request);
        }

        $user          = $request->getAttribute(UserInterface::class);
        $routeResource = new RouteResource($routeResult, $request, $this->paramMap);

        if (! $this->acl->isAllowedRoute($user, $routeResource)) {
            return $this->forbiddenHandler->handle($request);
        }

        return $handler->handle($request);
    }
}
