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
        $routeResult   = $request->getAttribute(RouteResult::class);
        $user          = $request->getAttribute(UserInterface::class);
        $routeResource = new RouteResource($routeResult, $request, $this->paramMap);

        if (! $this->acl->isAllowedRoute($user, $routeResource)) {
            return $this->forbiddenHandler->handle($request);
        }

        return $handler->handle($request);
    }
}
