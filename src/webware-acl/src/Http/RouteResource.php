<?php

declare(strict_types=1);

namespace Webware\Acl\Http;

use Laminas\Permissions\Acl\Role\RoleInterface;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;
use Webware\UserManager\UserInterface;

/**
 * Bridges a Mezzio RouteResult into a Laminas ACL resource.
 *
 * Resource ID  = matched route name
 * Role         = authenticated UserInterface or default Guest from request attribute
 * OwnerId      = resolved from route param → query string → request attribute
 *                using three-level config: per-route options > global param map > convention
 *
 * @note StoreOwnedResourceInterface creates a dependency on ims-store. When extracting
 *       webware-acl as a standalone ecosystem package, move the interface or provide
 *       a webware-acl-store bridge package.
 */
final class RouteResource implements RouteResourceInterface
{
    public function __construct(
        private readonly RouteResult $routeResult,
        private readonly ServerRequestInterface $request,
        private readonly array $paramMap = [],
    ) {}

    public function getResourceId(): string
    {
        $matchedRoute = $this->routeResult->getMatchedRoute();

        return $this->routeResult->getMatchedRouteName();
    }

    public function getRole(): RoleInterface
    {
        return $this->request->getAttribute(UserInterface::class);
    }

    public function getOwnerId(): int
    {
        $routeName = $this->getResourceId();

        // 1. Per-route options array (most specific)
        $routeOptions = $this->routeResult->getMatchedRoute()->getOptions();
        $paramName    = $routeOptions['acl']['ownerId']
            // 2. Global route_param_map config (app-level fallback)
            ?? $this->paramMap[$routeName]['ownerId']
            // 3. Convention
            ?? 'ownerId';

        return (int) (
            $this->routeResult->getMatchedParams()[$paramName]
            ?? $this->request->getQueryParams()[$paramName]
            ?? $this->request->getAttribute($paramName)
            ?? 0
        );
    }
}
