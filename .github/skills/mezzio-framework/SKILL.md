---
name: "mezzio-framework"
description: "Load when working with Mezzio framework internals: ConfigProvider, RouteProvider, middleware pipeline, authentication, authorization, template rendering, or any framework integration pattern. Progressive skill — updated as deeper understanding is gained."
argument-hint: "<what you are working on — e.g. 'new module ConfigProvider', 'route middleware', 'authentication flow'>"
---

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## ConfigProvider Pattern

Every module has a `ConfigProvider` class. The project owner is an expert Mezzio user — follow these patterns exactly.

```php
final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'templates'    => $this->getTemplates(),
            // module-specific top-level keys...
        ];
    }
}
```

- `getDependencies()` returns `['factories' => [...], 'delegators' => [...]]`
- `getTemplates()` returns `['paths' => ['namespace' => [__DIR__ . '/../templates/namespace']]]`
- Only include keys that are relevant — do not add empty sections
- Third-party integrations (e.g. command bus) that merge config at the top-level key should be added at the `__invoke()` level, not inside `getDependencies()`

## Factory Co-location Convention

Factories live in a `Container/` subdirectory co-located with the class they instantiate:

```
src/ModuleName/src/
  RequestHandler/
    FooHandler.php
    Container/
      FooHandlerFactory.php         ← namespace: ModuleName\RequestHandler\Container
  CommandHandler/
    SaveFooHandler.php
    Container/
      SaveFooHandlerFactory.php     ← namespace: ModuleName\CommandHandler\Container
  SubLayer/
    RequestHandler/
      BarHandler.php
      Container/
        BarHandlerFactory.php       ← namespace: ModuleName\SubLayer\RequestHandler\Container
```

Root-level module services (e.g. `RouteProvider`) use the root `Container/` directory:
```
src/ModuleName/src/
  Container/
    RouteProviderFactory.php        ← namespace: ModuleName\Container
```

## RouteProvider Pattern

Routes are registered via a `RouteProvider` class injected into the pipeline, not directly in `config/routes.php`.

```php
final class RouteProvider implements RouteProviderInterface
{
    public function registerRoutes(
        RouteCollectorInterface $routeCollector,
        MiddlewareFactoryInterface $middlewareFactory,
    ): void {
        $routeCollector->get('/', [..., DashboardHandler::class], 'home');

        // Same path, different name + method — valid, no duplicate error
        $routeCollector->get('/login', [..., LoginHandler::class], 'module.login');
        $routeCollector->post('/login', [..., AuthenticationMiddleware::class, LoginHandler::class], 'module.login.post');

        $routeCollector->route('/resource[/{id:\d+}]', [..., ResourceHandler::class], ['GET', 'POST'], 'module.resource');
    }
}
```

**Route uniqueness rules** (Mezzio/FastRoute):
- Route **names** must be globally unique
- Route **paths** may be reused across registrations as long as the HTTP methods do not overlap
- Duplicate detection is triggered by name collision OR method overlap on the same path — not by path alone

**Pipeline middleware vs route middleware**: `SessionMiddleware` is piped globally in `pipeline.php` — it must **not** be added to route definitions. `AuthenticationMiddleware` is applied per-route to all protected routes. The login routes (`user.login`, `user.login.post`) must **not** include `AuthenticationMiddleware` — adding it there causes an infinite redirect loop since unauthenticated requests redirect to `/login` which is itself protected.

Route names referenced in templates via `$this->url('route.name')`.

## Template Namespace Registration

Registered in `ConfigProvider::getTemplates()`:

```php
private function getTemplates(): array
{
    return [
        'paths' => [
            'module-namespace' => [__DIR__ . '/../templates/module-namespace'],
        ],
    ];
}
```

Rendered as `$this->template->render('module-namespace::template-name')`.

## 3-Layer Rendering Stack

See the `htmx-mezzio` skill for full details on the rendering stack, variable propagation, layout disable, and conditional chrome suppression.

## Authentication

Uses `mezzio/mezzio-authentication` (session adapter: `mezzio/mezzio-authentication-session`).

- `LoginMiddleware` handles POST — `LoginHandler` is never reached on POST
- `LoginHandler::handle()` is GET only — renders the login form
- `AuthenticationMiddleware` is added to protected routes in `RouteProvider`
- Redirect-on-unauthenticated behaviour is configured in `config/autoload/`

## Authorization

Uses `mezzio/mezzio-authorization` + `mezzio/mezzio-authorization-acl`.

Config key: `mezzio-authorization-acl` with `roles`, `resources` (= route names), `allow` rules.

```php
'mezzio-authorization-acl' => [
    'roles'     => ['guest' => [], 'user' => ['guest'], 'admin' => ['user']],
    'resources' => ['home', 'module.login', 'module.logout', ...],
    'allow'     => ['guest' => ['module.login'], 'user' => ['home', ...], 'admin' => ['admin.*']],
],
```

`AuthorizationInterface::class => LaminasAcl::class` alias required in DI config.

## Key Framework Packages

| Package | Purpose |
|---|---|
| `mezzio/mezzio-authentication` | Session-based authentication |
| `mezzio/mezzio-authorization` | ACL-based authorization |
| `mezzio/mezzio-authorization-acl` | Laminas ACL driver |
| `mezzio/mezzio-valinor` | Value object input mapping via CuyZ/Valinor |
| `laminas/laminas-view` | Template renderer (used with Htmx module's custom renderer) |

## DI Alias Rule

**Always map a service to its interface when one exists.** Register the concrete class under its interface name as an alias:

```php
'aliases' => [
    SomeInterface::class => SomeConcreteClass::class,
],
```

Third-party packages key their container lookups to the interface (`$container->has(SomeInterface::class)`). If only the concrete class is registered, those lookups fail silently and features (panels, delegators, etc.) are skipped. This rule applies to every service in every module — factories, invokables, and delegator targets alike.

## webware-admin Dynamic Route Segment

`webware-admin` provides the base `/admin` route segment for all admin modules. The segment is **dynamic** — configurable per-application so it never clashes with existing routes.

### Configuration class

`Webware\Admin\Container\Configuration` is the canonical helper for reading admin config:

```php
use Webware\Admin\Container\Configuration;

// Constants
Configuration::ROUTE_KEY             // 'admin_route_key' — key inside the config array
Configuration::ROUTE_NAME_PREFIX     // 'admin.' — prefix for all route names
Configuration::DEFAULT_ROUTE_SEGMENT // 'webware.admin' — default path segment

// Helper methods
Configuration::getConfig($container, self::class);         // returns full AdminInterface::class config array
Configuration::getRouteSegment($container, self::class);   // returns just the route segment string
```

### Config key

The config array is stored under `AdminInterface::class` (the interface itself as key):

```php
// config/autoload/admin.local.php (to override default)
return [
    \Webware\Admin\AdminInterface::class => [
        'admin_route_key' => 'admin',   // changes /webware.admin/... to /admin/...
    ],
];
```

`webware-admin`'s own `ConfigProvider::getDefaultConfig()` registers the default:
```php
public function getDefaultConfig(): array
{
    return [
        Container\Configuration::ROUTE_KEY => Container\Configuration::DEFAULT_ROUTE_SEGMENT,
    ];
}
```

### How modules consume it

Any module whose `RouteProvider` must sit under the admin segment injects the full admin config array and reads `Configuration::ROUTE_KEY`:

```php
// Module's RouteProviderFactory
final readonly class RouteProviderFactory
{
    public function __invoke(ContainerInterface $container): RouteProvider
    {
        return new RouteProvider(
            Configuration::getConfig($container, self::class)
        );
    }
}

// Module's RouteProvider
final readonly class RouteProvider implements RouteProviderInterface
{
    public function __construct(private array $config) {}

    public function registerRoutes(
        RouteCollectorInterface $routeCollector,
        MiddlewareFactoryInterface $middlewareFactory
    ): void {
        $routeCollector->get(
            '/' . $this->config[Configuration::ROUTE_KEY] . '/acl.manager',
            $middlewareFactory->prepare([...]),
            $this->config[Configuration::ROUTE_NAME_PREFIX] . 'acl.read'
        );
    }
}
```

- Route paths become `/{admin_route_key}/{module-segment}` — e.g. `/admin/acl.manager`
- Route names become `admin.{module}.{action}` — e.g. `admin.acl.read`
- Changing the `admin_route_key` in config updates all admin routes automatically with no code changes
