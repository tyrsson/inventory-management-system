# Route-Based ACL with `RouteResource` — Implementation Plan

**Branch:** `acl-ownership-assertion-aggregates`
**Planned:** 2026-05-14
**Status:** Ready to execute

---

## Architectural Summary

| Before | After |
|---|---|
| Route name → lookup `acl_route_mapping` → `{resource_id, privilege_id}` → `isAllowed(roles, string, string)` | Route name IS the resource ID. HTTP method IS the privilege. `isAllowed(roles, RouteResource, privilege)` |
| Unmapped route → deny | Unregistered route → allow (opt-in protection model) |
| Developer manually types resource ID string | Developer clicks "Protect" on a route in the UI |
| `$routeMappings` injected into `Acl` | `$paramMap` injected into `Acl` |
| `AuthorizationMiddleware` required first in every route stack | `AuthorizingDispatchMiddleware` in global pipeline — no per-route boilerplate |
| Denied request executes route stack up to `AuthorizationMiddleware` | Denied request short-circuits at dispatch — route stack never executes |

All superseded route-mapping infrastructure (`AuthorizationMiddleware`, `RouteMapManagerHandler`, `ProcessRouteMappingMiddleware`, `SaveRouteMappingCommand/Handler`, `DeleteRouteMappingCommand/Handler`, the `acl_route_privilege` repository methods, `AclBuiltEvent` route-mapping API, `AclBuilder` route-mapping data path, `RegisterAclRouteMappingsListener`, `RegisterManifestRouteMappingsListener`) is **deleted in this plan** — see Files 19–29 and the Files-to-Delete section below.

---

## Key Design Decisions

- **Route name = resource ID.** No separate mapping step.
- **HTTP method → privilege** (static map: `GET=read`, `POST=create`, `PUT/PATCH=update`, `DELETE=delete`).
- **Opt-in protection model.** Unregistered routes pass through — `isAllowedRoute()` returns `true`. Routes are open until explicitly protected.
- **`RouteResource`** is the HTTP-layer bridge between a Mezzio `RouteResult` and the Laminas ACL resource contract. Built once per request by `RouteMiddleware` and stored as `RouteResource::class` request attribute.
- **`RouteMiddleware` override** sets two request attributes: `RouteResult::class` (original, for `DispatchMiddleware` compatibility) and `RouteResource::class` (enriched, for ACL). Registered under the upstream DI key — no pipeline changes required.
- **`AuthorizingDispatchMiddleware` replaces `DispatchMiddleware`** globally. Reads `RouteResource::class`, performs the ACL check, and short-circuits with a redirect response on deny — the route stack (body parsing, command bus, handlers) never executes for denied requests. Registered under `DispatchMiddleware::class` in `webware-acl`'s `ConfigProvider`, overriding the upstream factory.
- **`AuthorizationMiddleware` is fully deleted.** ACL enforcement is now global via `AuthorizingDispatchMiddleware`. All five `RouteProvider` files (`webware-acl`, `App`, `ims-manifest`, `webware-usermanager`, `webware-admin`) have `AuthorizationMiddleware::class` removed from every stack. The class file, its factory, and its `ConfigProvider` registration are deleted.
- **Three-level `ownerId` resolution** (per-route options → global config → convention) gives established applications freedom to map existing param names without changing routes.
- **`StoreOwnedResourceAssertion`** receives `RouteResource` as the `$resource` argument — `getOwnerId()` provides ownership data at the HTTP layer.
- **`AuthorizableCommandInterface`** and command-level ACL are preserved as ecosystem infrastructure but deferred for this application.

---

## File List

| # | File | Action |
|---|---|---|
| 1 | `src/webware-acl/src/Http/RouteResource.php` | CREATE |
| 2 | `src/webware-acl/src/Middleware/RouteMiddleware.php` | CREATE — enriches request with `RouteResource::class` attribute |
| 3 | `src/webware-acl/src/Middleware/Container/RouteMiddlewareFactory.php` | CREATE |
| 4 | `src/webware-acl/src/Middleware/AuthorizingDispatchMiddleware.php` | CREATE — ACL gate + dispatch, replaces upstream `DispatchMiddleware` |
| 4a | `src/webware-acl/src/RequestHandler/ForbiddenHandler.php` | CREATE — default denial response; swappable via `RequestHandlerInterface` |
| 5 | `src/webware-acl/src/Middleware/Container/AuthorizingDispatchMiddlewareFactory.php` | CREATE |
| 6 | `src/webware-acl/src/Acl.php` | MODIFY |
| 7 | `src/webware-acl/src/Container/AclFactory.php` | MODIFY |
| 8 | `src/webware-acl/src/Admin/Command/ProtectRouteCommand.php` | CREATE |
| 9 | `src/webware-acl/src/Admin/CommandHandler/ProtectRouteHandler.php` | CREATE |
| 10 | `src/webware-acl/src/Admin/CommandHandler/Container/ProtectRouteHandlerFactory.php` | CREATE |
| 11 | `src/webware-acl/src/Admin/Middleware/ProcessProtectRouteMiddleware.php` | CREATE |
| 12 | `src/webware-acl/src/Admin/Middleware/Container/ProcessProtectRouteMiddlewareFactory.php` | CREATE |
| 13 | `src/webware-acl/src/Admin/RequestHandler/ResourceListHandler.php` | MODIFY |
| 14 | `src/webware-acl/src/Admin/RequestHandler/Container/ResourceListHandlerFactory.php` | MODIFY |
| 15 | `src/webware-acl/src/RouteProvider.php` + 4 other module `RouteProvider` files | MODIFY — remove `AuthorizationMiddleware` from all stacks; remove route-mapping admin routes from webware-acl |
| 16 | `src/webware-acl/src/ConfigProvider.php` | MODIFY — factory overrides; command map; config key; remove all dead registrations |
| 17 | `src/webware-acl/templates/acl/admin-resources.phtml` | MODIFY — Unprotected Routes section |
| 18 | `.github/skills/webware-acl-ownership-command/SKILL.md` | MODIFY |
| 19 | `src/ims-manifest/src/ConfigProvider.php` | MODIFY — remove `RegisterManifestRouteMappingsListener` factory + event wiring |
| 20 | `src/webware-acl/src/Repository/AclRepositoryInterface.php` | MODIFY — remove `fetchRouteMappings()`, `saveRouteMapping()`, `deleteRouteMapping()` |
| 21 | `src/webware-acl/src/Repository/AclRepository.php` | MODIFY — remove same three methods |
| 22 | `src/webware-acl/src/AclBuilder.php` | MODIFY — strip route-mapping data path |
| 23 | `src/webware-acl/src/Event/AclBuiltEvent.php` | MODIFY — strip route-mapping API |
| 24–29 | DELETE — see Files-to-Delete section | DELETE |

---

## File 1 — `src/webware-acl/src/Http/RouteResource.php` (CREATE)

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Http;

use Ims\Store\Acl\StoreOwnedResourceInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;
use Webware\Acl\PrivilegeInterface;
use Webware\Acl\RoleProviderInterface;
use Webware\UserManager\UserInterface;

/**
 * Bridges a Mezzio RouteResult into a Laminas ACL resource.
 *
 * Resource ID  = matched route name
 * Privilege    = HTTP method mapped to create/read/update/delete
 * Role         = authenticated UserInterface from request attribute
 * OwnerId      = resolved from route param → query string → request attribute
 *                using three-level config: per-route options > global param map > convention
 *
 * @note StoreOwnedResourceInterface creates a dependency on ims-store. When extracting
 *       webware-acl as a standalone ecosystem package, move the interface or provide
 *       a webware-acl-store bridge package.
 */
final class RouteResource implements
    ResourceInterface,
    RoleProviderInterface,
    PrivilegeInterface,
    StoreOwnedResourceInterface
{
    private const array METHOD_PRIVILEGE_MAP = [
        'GET'    => PrivilegeInterface::READ,
        'POST'   => PrivilegeInterface::CREATE,
        'PUT'    => PrivilegeInterface::UPDATE,
        'PATCH'  => PrivilegeInterface::UPDATE,
        'DELETE' => PrivilegeInterface::DELETE,
    ];

    public function __construct(
        private readonly RouteResult $routeResult,
        private readonly ServerRequestInterface $request,
        private readonly array $paramMap = [],
    ) {}

    public function getResourceId(): string
    {
        return $this->routeResult->getMatchedRouteName();
    }

    public function getPrivilegeId(): string
    {
        return self::METHOD_PRIVILEGE_MAP[$this->request->getMethod()]
            ?? PrivilegeInterface::READ;
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
            $this->routeResult->getMatchedParam($paramName)
            ?? $this->request->getQueryParams()[$paramName]
            ?? $this->request->getAttribute($paramName)
            ?? 0
        );
    }
}
```

---

## File 2 — `src/webware-acl/src/Middleware/RouteMiddleware.php` (CREATE)

Runs in the global pipeline in place of the upstream `RouteMiddleware`. Performs identical routing, then additionally builds a `RouteResource` and attaches it as a second request attribute.

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Middleware;

use Mezzio\Router\RouterInterface;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Http\RouteResource;

final class RouteMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly array $paramMap = [],
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result  = $this->router->match($request);
        $request = $request->withAttribute(RouteResult::class, $result);

        if ($result->isSuccess()) {
            foreach ($result->getMatchedParams() as $param => $value) {
                $request = $request->withAttribute($param, $value);
            }

            // Attach enriched RouteResource for AuthorizingDispatchMiddleware and downstream
            $request = $request->withAttribute(
                RouteResource::class,
                new RouteResource($result, $request, $this->paramMap)
            );
        }

        return $handler->handle($request);
    }
}
```

---

## File 3 — `src/webware-acl/src/Middleware/Container/RouteMiddlewareFactory.php` (CREATE)

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Middleware\Container;

use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Middleware\RouteMiddleware;

final class RouteMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): RouteMiddleware
    {
        $paramMap = $container->get('config')[AclInterface::class]['route_param_map'] ?? [];

        return new RouteMiddleware(
            $container->get(RouterInterface::class),
            $paramMap,
        );
    }
}
```

---

## File 4 — `src/webware-acl/src/Middleware/AuthorizingDispatchMiddleware.php` (CREATE)

Replaces upstream `DispatchMiddleware` globally. Reads `RouteResource::class` from the request, runs the ACL check, and delegates the denial response entirely to the injected `RequestHandlerInterface $forbiddenHandler` — no authentication-status checks here. The route stack never executes for denied requests.

```php
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
use Webware\Acl\RequestHandler\ForbiddenHandler;
use Webware\UserManager\UserInterface;

final class AuthorizingDispatchMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AclInterface $acl,
        private readonly RequestHandlerInterface $forbiddenHandler = new ForbiddenHandler(),
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class, false);

        if (! $routeResult instanceof RouteResult) {
            // No route matched — pass through (MethodNotAllowedMiddleware handles this)
            return $handler->handle($request);
        }

        $user  = $request->getAttribute(UserInterface::class);
        $roles = $user?->getRoles() ?? [];

        if (! $this->acl->isAllowedRoute($request, $roles)) {
            return $this->forbiddenHandler->handle($request);
        }

        return $routeResult->process($request, $handler);
    }
}
```

> **Note:** `RouteResource::class` is available on the request for downstream use (e.g. `Process*Middleware` reading `getOwnerId()`). It is not used directly here — `isAllowedRoute()` constructs a fresh `RouteResource` internally from `RouteResult::class` using `$this->paramMap`. Consider whether `isAllowedRoute()` should accept a pre-built `RouteResource` to avoid rebuilding it.

---

## File 4a — `src/webware-acl/src/RequestHandler/ForbiddenHandler.php` (CREATE)

Default denial response handler. Implements PSR `RequestHandlerInterface`. `AuthorizingDispatchMiddleware` uses this as its default — consumers swap it by providing a custom `RequestHandlerInterface` implementation to the factory.

> **⚠ TODO — default response behaviour pending decision.** The mechanism (redirect, 403, messenger warning, etc.) has not yet been decided. Confirm before implementing this file. It is the last file to write — everything else is unblocked.

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\RequestHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ForbiddenHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // TODO: decide default response — redirect + messenger warning, 403, or configurable?
    }
}
```

---

## File 5 — `src/webware-acl/src/Middleware/Container/AuthorizingDispatchMiddlewareFactory.php` (CREATE)

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Middleware\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Middleware\AuthorizingDispatchMiddleware;

final class AuthorizingDispatchMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): AuthorizingDispatchMiddleware
    {
        // ForbiddenHandler is not container-registered — newed directly as the default.
        // To swap the denial response (e.g. for SPA/API), override this factory and
        // pass a custom RequestHandlerInterface implementation as the second argument.
        return new AuthorizingDispatchMiddleware($container->get(AclInterface::class));
    }
}
```

---

## File 6 — `src/webware-acl/src/Acl.php` (MODIFY)

**Constructor:** replace `$routeMappings` with `$paramMap`.

```php
public function __construct(
    private readonly LaminasAclInterface $acl,
    private readonly array $paramMap = [],  // replaces $routeMappings
) {}
```

**`isAllowedRoute()`:** use `hasResource()` + `RouteResource`. Unregistered route → `true`.

```php
#[Override]
public function isAllowedRoute(
    ServerRequestInterface $request,
    array|RoleInterface|string|null $roles = null,
): bool {
    $routeResult = $request->getAttribute(RouteResult::class);

    if (! ($routeResult instanceof RouteResult) || $routeResult->isFailure()) {
        return true;
    }

    $routeName = $routeResult->getMatchedRouteName();

    // Not opted in as a resource — allow through (opt-in model)
    if (! $this->acl->hasResource($routeName)) {
        return true;
    }

    $routeResource = new RouteResource($routeResult, $request, $this->paramMap);

    return $this->isAllowed($roles, $routeResource, $routeResource->getPrivilegeId());
}
```

**`isAllowedByRouteName()`:** use `hasResource()` + `null` privilege (any). Used by navigation — shows link if role has any privilege on the resource.

```php
#[Override]
public function isAllowedByRouteName(
    string $routeName,
    array|RoleInterface|string|null $roles = null,
): bool {
    // Not opted in = not protected = allow
    if (! $this->acl->hasResource($routeName)) {
        return true;
    }

    // null privilege = "any" — correct for navigation visibility checks
    return $this->isAllowed($roles, $routeName, null);
}
```

Add import: `use Webware\Acl\Http\RouteResource;`

---

## File 7 — `src/webware-acl/src/Container/AclFactory.php` (MODIFY)

```php
public function __invoke(ContainerInterface $container): Acl
{
    $aclBuilder = $container->get(\Webware\Acl\AclBuilder::class);
    $laminas    = $aclBuilder->build();
    $paramMap   = $container->get('config')[AclInterface::class]['route_param_map'] ?? [];

    return new Acl(
        acl:      $laminas,
        paramMap: $paramMap,
    );
}
```

---

## File 8 — `src/webware-acl/src/Admin/Command/ProtectRouteCommand.php` (CREATE)

Follows existing `NamedCommandInterface` + `NamedCommandTrait` pattern (same as `SaveResourceCommand`).

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Command;

use Webware\CommandBus\Command\NamedCommandInterface;
use Webware\CommandBus\Command\NamedCommandTrait;

final readonly class ProtectRouteCommand implements NamedCommandInterface
{
    use NamedCommandTrait;

    /**
     * @param string[] $allowedMethods  e.g. ['GET', 'POST']
     */
    public function __construct(
        public string $routeName,
        public array  $allowedMethods,
    ) {}
}
```

---

## File 9 — `src/webware-acl/src/Admin/CommandHandler/ProtectRouteHandler.php` (CREATE)

Inserts one `acl_resource` row and one `acl_privilege` row per mapped HTTP method. Deduplication is the repository's responsibility — safe to re-run.

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\CommandHandler;

use Webware\Acl\Admin\Command\ProtectRouteCommand;
use Webware\Acl\PrivilegeInterface;
use Webware\Acl\Repository\AclRepositoryInterface;
use Webware\CommandBus\Command\CommandHandlerInterface;
use Webware\CommandBus\Command\CommandInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;

use function array_filter;
use function array_map;
use function array_unique;
use function assert;

final class ProtectRouteHandler implements CommandHandlerInterface
{
    private const array METHOD_PRIVILEGE_MAP = [
        'GET'    => PrivilegeInterface::READ,
        'POST'   => PrivilegeInterface::CREATE,
        'PUT'    => PrivilegeInterface::UPDATE,
        'PATCH'  => PrivilegeInterface::UPDATE,
        'DELETE' => PrivilegeInterface::DELETE,
    ];

    public function __construct(private readonly AclRepositoryInterface $aclRepository) {}

    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof ProtectRouteCommand);

        // Insert resource row (route name as both resourceId and label)
        $this->aclRepository->saveResource($command->routeName, $command->routeName);

        // Derive unique privileges from allowed methods and insert
        $privileges = array_unique(array_filter(array_map(
            static fn(string $method): string => self::METHOD_PRIVILEGE_MAP[$method] ?? '',
            $command->allowedMethods,
        )));

        foreach ($privileges as $privilege) {
            $this->aclRepository->savePrivilege($command->routeName, $privilege);
        }

        return new CommandResult($command, CommandStatus::Success, null);
    }
}
```

> **Pre-condition:** Verify `AclRepositoryInterface` declares `saveResource(string $resourceId, string $label): void` and `savePrivilege(string $resourceId, string $privilegeId): void`. Add methods if missing. Also check whether the existing `SaveResourceHandler` invalidates the ACL cache — replicate that pattern here.

---

## File 10 — `ProtectRouteHandlerFactory.php` (CREATE)

Standard single-dependency factory:

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\CommandHandler\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\CommandHandler\ProtectRouteHandler;
use Webware\Acl\Repository\AclRepositoryInterface;

final class ProtectRouteHandlerFactory
{
    public function __invoke(ContainerInterface $container): ProtectRouteHandler
    {
        return new ProtectRouteHandler($container->get(AclRepositoryInterface::class));
    }
}
```

---

## File 11 — `ProcessProtectRouteMiddleware.php` (CREATE)

Follows `HttpMethodProcessorTrait` pattern. POST only — reads `routeName` from parsed body, resolves allowed methods from `RouteCollectorInterface`.

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware;

use Axleus\Message\SystemMessengerInterface;
use Mezzio\Router\RouteCollectorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Admin\Command\ProtectRouteCommand;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;

final class ProcessProtectRouteMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly RouteCollectorInterface $routeCollector,
    ) {}

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $body      = $request->getParsedBody();
        $routeName = (string) ($body['routeName'] ?? '');
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        // Resolve allowed methods from the registered route definition
        $allowedMethods = ['GET'];
        foreach ($this->routeCollector->getRoutes() as $route) {
            if ($route->getName() === $routeName) {
                $allowedMethods = $route->getAllowedMethods() ?? ['GET'];
                break;
            }
        }

        $result = $this->commandBus->handle(
            new ProtectRouteCommand($routeName, $allowedMethods)
        );

        if ($result->getStatus() === CommandStatus::Success) {
            $messenger?->success("Route '{$routeName}' is now protected.", hops: 0, now: true);
        } else {
            $messenger?->error("Failed to protect route '{$routeName}'.", hops: 0, now: true);
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
```

---

## File 12 — `ProcessProtectRouteMiddlewareFactory.php` (CREATE)

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware\Container;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\Middleware\ProcessProtectRouteMiddleware;
use Webware\CommandBus\CommandBusInterface;

final class ProcessProtectRouteMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): ProcessProtectRouteMiddleware
    {
        return new ProcessProtectRouteMiddleware(
            $container->get(CommandBusInterface::class),
            $container->get(RouteCollectorInterface::class),
        );
    }
}
```

---

## File 13 — `ResourceListHandler.php` (MODIFY)

Inject `RouteCollectorInterface`. Compute `$unprotected` = registered route names not yet in `acl_resource`.

**New constructor:**
```php
public function __construct(
    private readonly AclRepositoryInterface $aclRepository,
    private readonly TemplateRendererInterface $template,
    private readonly RouteCollectorInterface $routeCollector,
) {}
```

**New logic in `handle()`** (add before `$response = new HtmlResponse(...)`):

```php
// Build set of already-registered resource IDs
$registeredIds = [];
foreach ($resources as $resource) {
    $registeredIds[$resource->resourceId] = true;
}

// Unprotected = registered routes not yet opted in as ACL resources
// Value is the route's allowed methods array for display
$unprotected = [];
foreach ($this->routeCollector->getRoutes() as $route) {
    $name = $route->getName();
    if ($name !== null && $name !== '' && ! isset($registeredIds[$name])) {
        $unprotected[$name] = $route->getAllowedMethods() ?? ['GET'];
    }
}
```

**Updated `render()` call** — add `'unprotected' => $unprotected` to the view data array.

---

## File 14 — `ResourceListHandlerFactory.php` (MODIFY)

```php
public function __invoke(ContainerInterface $container): ResourceListHandler
{
    return new ResourceListHandler(
        $container->get(AclRepositoryInterface::class),
        $container->get(TemplateRendererInterface::class),
        $container->get(RouteCollectorInterface::class),
    );
}
```

Add import: `use Mezzio\Router\RouteCollectorInterface;`

---

## File 15 — RouteProvider files (MODIFY — 5 files)

**Files affected:**
1. `src/webware-acl/src/RouteProvider.php`
2. `src/App/src/RouteProvider.php`
3. `src/ims-manifest/src/RouteProvider.php`
4. `src/webware-usermanager/src/RouteProvider.php`
5. `src/webware-admin/src/RouteProvider.php`

**All five files:** Remove `AuthorizationMiddleware::class` from every route stack and remove the `use Webware\Acl\Middleware\AuthorizationMiddleware;` import. ACL enforcement is now handled globally by `AuthorizingDispatchMiddleware`.

```php
// Before
$middlewareFactory->prepare([
    AuthorizationMiddleware::class,   // ← REMOVE from all stacks
    SomeHandler::class,
])

// After
$middlewareFactory->prepare([
    SomeHandler::class,
])
```

**`src/webware-acl/src/RouteProvider.php` only — additional changes:**

Remove the `use Webware\Acl\Admin\RequestHandler\RouteMapManagerHandler;` import.

Remove all three route-mapping admin routes:
```php
// DELETE — these three routes manage the now-deleted route-mapping system
$routeCollector->get('/admin/access/routes', ...);    // admin.acl.routes.read
$routeCollector->post('/admin/access/routes', ...);   // admin.acl.routes.create
$routeCollector->delete('/admin/access/routes', ...); // admin.acl.routes.delete
```

Add the new protect endpoint (no `AuthorizationMiddleware` — global pipeline handles it):
```php
$routeCollector->post(
    '/admin/access/resources/protect',
    $middlewareFactory->prepare([
        BodyParamsMiddleware::class,
        ProcessProtectRouteMiddleware::class,
        ResourceListHandler::class,
    ]),
    'admin.acl.resources.protect'
);
```

Add import: `use Webware\Acl\Admin\Middleware\ProcessProtectRouteMiddleware;`

---

## File 16 — `ConfigProvider.php` (MODIFY)

**`getDependencies()` factories block** — add upstream key overrides and new service factories:
```php
// Upstream key overrides — replace upstream RouteMiddleware and DispatchMiddleware
Mezzio\Router\Middleware\RouteMiddleware::class    => Middleware\Container\RouteMiddlewareFactory::class,
Mezzio\Router\Middleware\DispatchMiddleware::class => Middleware\Container\AuthorizingDispatchMiddlewareFactory::class,

// New admin services
ProtectRouteHandler::class           => ProtectRouteHandlerFactory::class,
ProcessProtectRouteMiddleware::class => ProcessProtectRouteMiddlewareFactory::class,
```

**Remove from `getDependencies()` factories block:**
```php
// DELETE — dead after route-mapping system removal
AuthorizationMiddleware::class    => AuthorizationMiddlewareFactory::class,
SaveRouteMappingHandler::class    => SaveRouteMappingHandlerFactory::class,
DeleteRouteMappingHandler::class  => DeleteRouteMappingHandlerFactory::class,
ProcessRouteMappingMiddleware::class => ProcessRouteMappingMiddlewareFactory::class,
RouteMapManagerHandler::class     => RouteMapManagerHandlerFactory::class,
```

**Remove from `getBusConfig()` command map:**
```php
// DELETE — dead
SaveRouteMappingCommand::class   => SaveRouteMappingHandler::class,
DeleteRouteMappingCommand::class => DeleteRouteMappingHandler::class,
```

**Remove from `getListenerConfig()` (or equivalent event wiring):**
```php
// DELETE — RegisterAclRouteMappingsListener is deleted
```

**`getBusConfig()` command map** — add:
```php
ProtectRouteCommand::class => ProtectRouteHandler::class,
```

**`__invoke()`** — add default config key to the returned array:
```php
AclInterface::class => $this->getAclConfig(),
```

**New method:**
```php
public function getAclConfig(): array
{
    return [
        // Per-route ACL param name overrides.
        // Key: route name. Value: map of ACL param name => route/query param name.
        // Example: 'manifest.create' => ['ownerId' => 'store']
        'route_param_map' => [],
    ];
}
```

Remove all dead `use` statements for deleted classes. Add new `use` statements for new classes.

---

## File 17 — `admin-resources.phtml` (MODIFY)

**Add the following section immediately before the `<!-- Resources accordion -->` comment.** Follow all existing CSS class conventions — no inline styles.

```php
<?php if ($this->unprotected !== []): ?>
<div class="card mb-4">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-shield-exclamation text-warning"></i>
        <span class="small fw-semibold">Unprotected Routes</span>
        <span class="badge bg-warning-subtle border border-warning-subtle text-warning-emphasis ms-1">
            <?= count($this->unprotected) ?>
        </span>
        <span class="text-secondary small ms-auto">Routes not yet opted in to ACL protection — currently open to all</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-warning">
                <tr>
                    <th>Route Name</th>
                    <th class="ims-col-methods">Methods</th>
                    <th class="ims-col-action"></th>
                </tr>
            </thead>
            <tbody id="ims-unprotected-routes">
                <?php foreach ($this->unprotected as $routeName => $methods): ?>
                    <tr id="ims-unprotected-<?= $this->escapeHtmlAttr(preg_replace('/[^a-z0-9]+/', '-', strtolower($routeName))) ?>">
                        <td><code class="small"><?= $this->escapeHtml($routeName) ?></code></td>
                        <td>
                            <?php foreach ($methods as $method): ?>
                                <span class="badge bg-secondary-subtle border text-secondary-emphasis ims-badge-xs">
                                    <?= $this->escapeHtml($method) ?>
                                </span>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-end">
                            <button
                                class="btn btn-sm btn-warning py-0 px-2"
                                hx-post="<?= $this->url('admin.acl.resources.protect') ?>"
                                hx-vals='{"routeName": "<?= $this->escapeHtmlAttr($routeName) ?>"}'
                                hx-target="main"
                                hx-swap="innerHTML"
                            >
                                <i class="bi bi-shield-check me-1"></i>Protect
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
```

**Required CSS additions to `public/assets/css/custom.css`:**
```css
.ims-col-methods { width: 22%; }
.ims-col-action  { width: 12%; }
```

**HTMX behaviour:** `hx-target="main"` + `hx-swap="innerHTML"` is identical to the boosted-navigation pattern used throughout this application. `ProcessProtectRouteMiddleware` calls `$handler->handle($request)` (no redirect), so `ResourceListHandler` re-renders the full page body (Layer 2). HTMX swaps this into `<main>`. The protected route is absent from `$unprotected` in the fresh render and the success toast fires via `showPendingToasts()` reading the `.ims-pending-toast` elements present in the new body. This is the only correct approach — `HX-Trigger` for toasts is explicitly forbidden by the `htmx-mezzio` skill.

---

## File 18 — `webware-acl-ownership-command` SKILL (MODIFY)

Update to reflect:

1. **Required Interfaces table** — add `AuthorizableCommandInterface` row, note it supersedes `CommandInterface + RoleProviderInterface` for command-level ACL (ecosystem use, deferred for this app).
2. **Canonical Command Shape** — add a second example showing `RouteResource` as the HTTP-layer alternative; note that store-scoped assertions work at both layers.
3. **Add section:** "Route-based ACL (primary mechanism for this application)" describing the `RouteResource` opt-in flow.
4. **Preserve all existing content** — append only, do not remove.

---

## File 19 — `src/ims-manifest/src/ConfigProvider.php` (MODIFY)

Remove the `RegisterManifestRouteMappingsListener` factory registration and its `AclBuiltEvent` listener wiring — the listener is deleted (see Files-to-Delete).

```php
// DELETE from getDependencies() factories:
Listener\RegisterManifestRouteMappingsListener::class => Container\RegisterManifestRouteMappingsListenerFactory::class,

// DELETE from getListenerConfig() (AclBuiltEvent listeners array):
['listener' => Listener\RegisterManifestRouteMappingsListener::class, 'priority' => 1],
```

Remove the `use` statements for both deleted classes.

---

## File 20 — `src/webware-acl/src/Repository/AclRepositoryInterface.php` (MODIFY)

Remove three method signatures — they are implemented only by the route-mapping system which is deleted:

```php
// DELETE these three method declarations:
public function fetchRouteMappings(): array;
public function saveRouteMapping(string $routeName, int $resourcePk, int $privilegePk): void;
public function deleteRouteMapping(string $routeName): void;
```

---

## File 21 — `src/webware-acl/src/Repository/AclRepository.php` (MODIFY)

Remove the three method implementations corresponding to File 20. Each spans roughly 20–30 lines:

- `fetchRouteMappings()` — SELECT JOIN across `acl_route_privilege`, `acl_resource`, `acl_privilege`
- `saveRouteMapping()` — INSERT into `acl_route_privilege`
- `deleteRouteMapping()` — DELETE from `acl_route_privilege`

Remove any `use` statements for classes used exclusively by those three methods.

---

## File 22 — `src/webware-acl/src/AclBuilder.php` (MODIFY)

Strip the route-mapping data path. `AclBuilder` no longer fetches, caches, or exposes route mappings:

**Remove the `$routeMappings` property:**
```php
// DELETE:
private array $routeMappings = [];
```

**Remove `getRouteMappings()` method** (entire method — no longer called by `AclFactory`).

**In `build()`** — remove the `fetchRouteMappings()` call and the `routeMappings` key from `$data`:
```php
// DELETE:
$routeMappings  = $this->repository->fetchRouteMappings();

// DELETE from $data array:
'routeMappings' => $routeMappings,
```

**In `buildFromArrays()`** — remove the `AclBuiltEvent` route-mapping lines:
```php
// BEFORE:
$event = new AclBuiltEvent($acl, $data['routeMappings']);
$this->dispatch($event);
$this->routeMappings = $event->getRouteMappings();

// AFTER:
$this->dispatch(new AclBuiltEvent($acl));
```

---

## File 23 — `src/webware-acl/src/Event/AclBuiltEvent.php` (MODIFY)

Strip the route-mapping API. The event retains only the `$acl` public property — it fires to allow listeners to add last-minute ACL rules, but no longer carries route mappings:

**Remove** the `$routeMappings` property, the `array $routeMappings = []` constructor parameter, the constructor body assignment, `addRouteMapping()`, and `getRouteMappings()`.

**Simplified constructor:**
```php
public function __construct(public readonly Acl $acl) {}
```

---

## Files-to-Delete

The following files are entirely deleted — no replacement, no deprecation notice needed:

**`webware-acl` — `AuthorizationMiddleware` system:**
- `src/webware-acl/src/Middleware/AuthorizationMiddleware.php`
- `src/webware-acl/src/Container/AuthorizationMiddlewareFactory.php`

**`webware-acl` — Route-mapping listeners:**
- `src/webware-acl/src/Listener/RegisterAclRouteMappingsListener.php`

**`webware-acl` — Route-mapping admin CRUD:**
- `src/webware-acl/src/Admin/Command/SaveRouteMappingCommand.php`
- `src/webware-acl/src/Admin/Command/DeleteRouteMappingCommand.php`
- `src/webware-acl/src/Admin/CommandHandler/SaveRouteMappingHandler.php`
- `src/webware-acl/src/Admin/CommandHandler/DeleteRouteMappingHandler.php`
- `src/webware-acl/src/Admin/CommandHandler/Container/SaveRouteMappingHandlerFactory.php`
- `src/webware-acl/src/Admin/CommandHandler/Container/DeleteRouteMappingHandlerFactory.php`
- `src/webware-acl/src/Admin/Middleware/ProcessRouteMappingMiddleware.php`
- `src/webware-acl/src/Admin/Middleware/Container/ProcessRouteMappingMiddlewareFactory.php`
- `src/webware-acl/src/Admin/RequestHandler/RouteMapManagerHandler.php`
- `src/webware-acl/src/Admin/RequestHandler/Container/RouteMapManagerHandlerFactory.php`

**`ims-manifest` — Route-mapping listener:**
- `src/ims-manifest/src/Listener/RegisterManifestRouteMappingsListener.php`
- `src/ims-manifest/src/Container/RegisterManifestRouteMappingsListenerFactory.php`

**Tests:**
- `src/webware-acl/test/unit/Admin/CommandHandler/SaveRouteMappingHandlerTest.php`
- `src/webware-acl/test/unit/Admin/CommandHandler/DeleteRouteMappingHandlerTest.php`
- `src/webware-acl/test/integration/ProcessRouteMappingMiddlewareTest.php`

---

## Pre-Execution Checklist

Pre-execution findings (completed during session-start exploration, 2026-05-14):

1. **`AclRepositoryInterface::saveResource()`** — ✅ exists: `saveResource(string $resourceId, string $label): int`. Returns PK — use it in `ProtectRouteHandler`.
2. **`AclRepositoryInterface::savePrivilege()`** — ❌ does NOT exist. Use `insertPrivilege(int $resourcePk, string $privilegeId, string $label): int`. `ProtectRouteHandler` must call `saveResource()` first, capture the returned PK, then call `insertPrivilege($pk, $privilegeId, $label)` per privilege.
3. **`Route::getAllowedMethods()`** — ✅ confirmed: `public function getAllowedMethods(): ?array` exists on `Mezzio\Router\Route`.
4. **`RouteResult` and `Route` are `final`** — ✅ confirmed. No subclassing possible. `RouteResource` wrapper approach is correct.
5. **`DispatchMiddleware`** — ✅ confirmed: `$routeResult instanceof RouteResult` check at line 26. Our `RouteMiddleware` must still set `RouteResult::class` = original `RouteResult`; `AuthorizingDispatchMiddleware` reads `RouteResult::class` for dispatch and `RouteResource::class` for ACL.
6. **Upstream factory keys** — ✅ confirmed from `mezzio/mezzio-router` `ConfigProvider`: `RouteMiddleware::class => RouteMiddlewareFactory::class` and `DispatchMiddleware::class => DispatchMiddlewareFactory::class`. Override both in `webware-acl` `ConfigProvider`.
7. **`StoreOwnedResourceInterface`** — in `src/ims-store/src/Acl/`. Confirm `ims-store` is a `webware-acl` composer dependency before writing `RouteResource.php`.
8. **ACL cache invalidation pattern** — read from `SaveResourceHandler`: calls `$this->aclRepository->incrementVersion()` after all writes. Replicate in `ProtectRouteHandler`.
9. **`AuthorizationMiddleware` removal from route stacks** — all `RouteProvider` files across all modules must have `AuthorizationMiddleware::class` removed. Enumerate affected files before executing File 15.
10. **`htmx-mezzio` skill** — confirm partial-refresh pattern for the Protect button response before writing File 17.
11. **Behavior change** — `isAllowedRoute()` now returns `true` for unregistered routes (was `false`). Verify admin routes are in `acl_resource` after the seed runs.

---

## Deferred (not in this plan)

- `SaveManifestCommand` → `CreateManifestCommand` / `UpdateManifestCommand` split
- `#[Authorizable]` attribute + `AuthorizableCommandTrait`
- `ResourceProviderInterface` / `CommandMapResourceProvider`
- **`webware-configmanager`** — PSR-14 migration of `axleus-configmanager`. Replaces `ProtectRouteCommand` DB writes with flat-file writes using the webimpress safe-writer. Key benefits:
  - **Development mode** — `axleus-configmanager` already respects Mezzio development mode; when dev mode is active, no config cache exists to bust, so ACL rule changes take effect immediately with no manual cache-clear step.
  - **Production mode (sync runtime)** — fires a `bustCache` event that deletes the Mezzio config cache file, causing it to rebuild on the next request; route protection registrations are picked up automatically.
  - **Standard config array** — protected route registrations become part of the normal `$config` array, eliminating the separate DB round-trip and making them version-controllable alongside the rest of the application config.
  - Eliminates the `acl_route_privilege` DB table entirely.
  - Implement after this PR.
  - **⚠ TrueAsync constraint** — in TrueAsync the config array is loaded once at process startup and lives in memory for the lifetime of the process. The `bustCache` event (delete Mezzio PHP cache file) is a **no-op** against a running TrueAsync process — the cache file is not consulted until the next process start. Two implications:
    1. **Dev mode with hot-reload** — `HotCodeReload\Watcher` already watches `config/` via `inotifywait`; a flat-file write to `config/autoload/` triggers an automatic `pcntl_exec` restart, which picks up the new file. No extra work needed.
    2. **Production** — if hot-reload is disabled, the `bustCache` listener must be replaced (or supplemented) with a mechanism that signals the running process to restart gracefully (e.g. SIGHUP handler, or hot-reload kept enabled with production guards). Without this, the config write persists to disk but has no effect until a manual restart. **This is a required design decision for the `webware-configmanager` PR — do not overlook it.**
- DB schema: `acl_route_privilege` table — dead storage after this PR; drop via a new migration in the `webware-configmanager` PR when the replacement is ready.
- Fix duplicate section in `webware-coding-standard/SKILL.md`
