<?php

declare(strict_types=1);


namespace Webware\Navigation\Middleware;

use Webware\UserManager\UserInterface;
use Mezzio\Router\RouteResult;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Navigation\View\Helper\Navigation;

/**
 * Pipes per-request roles and the active route name into the Navigation helper.
 *
 * Must run AFTER RouteMiddleware so RouteResult is on the request.
 * Pipe in the global pipeline after UrlHelperMiddleware.
 */
final class NavigationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Navigation $helper) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);

        if ($user !== null) {
            $this->helper->setUser($user);
        }

        $routeResult = $request->getAttribute(RouteResult::class);

        if ($routeResult instanceof RouteResult && ! $routeResult->isFailure()) {
            $this->helper->setActiveRouteName($routeResult->getMatchedRouteName() ?: null);
        }

        return $handler->handle($request);
    }
}
