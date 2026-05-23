# webware-acl — ACL Integration Skill

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

Load this skill when:
- Integrating a new module with the ACL system (protecting routes, adding resources/rules)
- Writing or reviewing `RegisterXxx*Listener` classes for a component
- Using `AuthorizationMiddleware`, `AclInterface`, or `WriteResult` in any context
- Understanding how the ACL build pipeline works

---

## Package Coordinates

```
Package:   webware/webware-acl
Namespace: Webware\Acl
```

Admin sub-namespace for write-path middleware and handlers:
```
Webware\Acl\Admin\Middleware\
Webware\Acl\Admin\RequestHandler\
Webware\Acl\Admin\WriteResult
```

---

## Core Concepts

### `Privilege` — Canonical privilege identifiers

Non-instantiable class. Always use the constants — never hardcode strings.

```php
use Webware\Acl\Privilege;

Privilege::READ   // 'read'
Privilege::CREATE // 'create'
Privilege::UPDATE // 'update'
Privilege::DELETE // 'delete'
```

### `WriteResult` — Request attribute key enum

Backed string enum. Middleware sets an attribute on the request; handlers read
it. The enum value IS the PSR-7 attribute name key.

```php
use Webware\Acl\Admin\WriteResult;

// Middleware sets it:
$request->withAttribute(WriteResult::Success->value, true);   // bool
$request->withAttribute(WriteResult::Failure->value, 'msg');  // optional

// Handler reads it:
$request->getAttribute(WriteResult::Success->value); // true|false|null
```

Only `WriteResult::Success` is used in practice. Its string value is
`'webware_acl.write_result.success'`.

### `AclInterface` — Three access-check methods

```php
use Webware\Acl\AclInterface;

// Check by role(s) + resource + privilege (string constants)
$acl->isAllowed($roles, 'my.resource', Privilege::READ);

// Check from a PSR-7 request (uses RouteResource; FAIL CLOSED — deny if not registered)
$acl->isAllowedRoute($user, $routeResource);
```

---

## ACL Build Pipeline

The ACL is built by `AclBuilder` on first request (or after cache invalidation).
It fires PSR-14 events at each phase. Every component that adds resources, rules,
or route mappings does so by listening to these events.

```
AclBuildStartedEvent  → (roles loaded from DB)
ResourcesLoadedEvent  → listeners add component resources
RulesLoadedEvent      → listeners add component rules
AclBuiltEvent         → listeners may add last-minute rules; cache is written after dispatch
```

### Event summary

| Event | `$event` property | Listener adds |
|---|---|---|
| `ResourcesLoadedEvent` | `$event->acl` (`Laminas\Permissions\Acl\Acl`) | `$event->acl->addResource(...)` |
| `RulesLoadedEvent` | `$event->acl` | `$event->acl->allow(...)` / `->deny(...)` |
| `AclBuiltEvent` | `$event->acl` | `$event->acl->allow(...)` / `->deny(...)` last-minute rules only |

---

## How Every Component Integrates with ACL

Each component must provide **three listener classes** and register them in its
`ConfigProvider`. The pattern is identical across all components.

### 1. `Register{Module}ResourcesListener` — fires on `ResourcesLoadedEvent`

```php
final class RegisterManifestResourcesListener
{
    public function __invoke(ResourcesLoadedEvent $event): void
    {
        $event->acl->addResource('manifest');
    }
}
```

- One `addResource()` call per protected resource string.
- The resource string is the canonical name used in rule lookups — keep it short
  and lowercase (e.g. `'manifest'`, `'admin.acl'`, `'user'`).

### 2. `Register{Module}RulesListener` — fires on `RulesLoadedEvent`

```php
final class RegisterManifestRulesListener
{
    public function __invoke(RulesLoadedEvent $event): void
    {
        $event->acl->allow('Administrator', 'manifest', [
            Privilege::READ,
            Privilege::CREATE,
            Privilege::UPDATE,
            Privilege::DELETE,
        ]);
        $event->acl->allow('Manager', 'manifest', [Privilege::READ, Privilege::CREATE]);
    }
}
```

- Rules added here are **in-memory only** — they supplement the DB-loaded rules.
  Use them for built-in/default grants that should not be manageable via the
  admin UI (e.g. Developer always gets full access).
- End-user configurable grants live in the DB and are loaded automatically.

### 3. ~~`Register{Module}RouteMappingsListener`~~ — **DELETED (superseded by `ProtectRouteCommand`)**

> ⚠ Route mappings via `AclBuiltEvent::addRouteMapping()` are **no longer supported**.
> `AclBuiltEvent` no longer carries route mapping data. The method `addRouteMapping()` has been removed.
>
> Protected routes are registered through `ConfigProvider::getAclConfig()` (static) or
> via the admin UI using `ProtectRouteCommand` (DB-managed rules).
> `AuthorizationMiddleware` checks the ACL directly by route name — no route-mapping listener needed.
>
> **Do not create new `Register{Module}RouteMappingsListener` classes.** Remove any that exist.

### ConfigProvider registration

```php
public function getListeners(): array
{
    return [
        ResourcesLoadedEvent::class => [
            ['listener' => RegisterManifestResourcesListener::class, 'priority' => 1],
        ],
        RulesLoadedEvent::class => [
            ['listener' => RegisterManifestRulesListener::class, 'priority' => 1],
        ],
        // AclBuiltEvent — no RouteMappingsListener; route protection is managed via ProtectRouteCommand
    ];
}
```

Listeners that have no constructor dependencies may be registered as
`'invokables'` in `getDependencies()`. Listeners with dependencies need a
factory and go in `'factories'`.

---

## `AuthorizationMiddleware` — Route Access Control (Global Pipeline)

`AuthorizationMiddleware` handles route-level ACL enforcement globally. It runs
**before** Mezzio's `DispatchMiddleware` in the pipeline — both middleware are present.
`DispatchMiddleware` was never replaced.

**Do NOT add any middleware to individual route stacks for ACL purposes.** Every
route stack should only contain data-processing middleware and a terminal handler.

```php
// In RouteProvider::registerRoutes() — NO authorization middleware in the stack
$routeCollector->get(
    '/manifests',
    $middlewareFactory->prepare([
        ManifestListHandler::class,   // ← no ACL middleware here
    ]),
    'manifest.list'
);

$routeCollector->post(
    '/manifests/upload',
    $middlewareFactory->prepare([
        ProcessManifestUploadMiddleware::class,
        ManifestUploadHandler::class,
    ]),
    'manifest.upload.store'
);
```

### How route protection works

1. `isAllowedRoute()` is **FAIL CLOSED** — a route not registered as an ACL resource
   is always denied. Every accessible route must appear in a module's `getAclConfig()`
   resources array with an explicit allow rule for at least one role.
2. Static rules come from `ConfigProvider::getAclConfig()`. Runtime/DB-managed rules
   are added via `ProtectRouteCommand` through the ACL admin UI.
3. `AuthorizationMiddleware` calls `$acl->isAllowedRoute($user, $routeResource)`.
   - Route resource not in ACL → **deny** (fail-closed).
   - Route in ACL but role not allowed → `ForbiddenHandler::handle($request)`.
   - Route in ACL and role allowed → pass to next middleware.

### Decision table

| Condition | Result |
|---|---|
| No `RouteResult` or routing failure | Pass through (not ACL's concern) |
| Route not registered as ACL resource | **Deny** — fail-closed |
| `isAllowed()` → true | Delegate to next middleware |
| `isAllowed()` → false | `ForbiddenHandler::handle($request)` |

---

## `AclRepositoryInterface` — Write Methods

All write methods are in `AclRepositoryInterface`. After any write, always call
`incrementVersion()` to invalidate the `FileAclCache`:

```php
$this->aclRepository->saveRole($roleId, $parentPk);
$this->aclRepository->incrementVersion();    // ← required after every write
```

Key write methods:

| Method | Notes |
|---|---|
| `saveRole(string $roleId, int $parentPk): int` | Upsert by `role_id` unique key |
| `deleteRole(int $rolePk): void` | Caller must verify no users assigned |
| `saveResource(string $resourceId, string $label): int` | Upsert by `resource_id` |
| `insertPrivilege(int $resourcePk, string $privilegeId, string $label): int` | Scoped to a resource |
| `deleteResource(int $resourcePk): void` | Cascades: deletes rules + mappings + privileges |
| `saveRule(int $rolePk, int $resourcePk, int $privilegePk, string $type): void` | Upsert — type: `'allow'`\|`'deny'` |
| `updateRuleType(int $id, string $type): void` | PATCH path |
| `deleteRule(int $id): void` | By rule PK |
| `incrementVersion(): void` | **Must be called after every write** |

---

## `IdentityMiddleware`

Attaches the authenticated `UserInterface` to the request. It runs **before**
`AuthorizationMiddleware` in the global pipeline (not per-route) — it is already
present on every request. Do not add it to individual route stacks.

`SystemMessengerInterface` is **not** attached by `IdentityMiddleware`. That is
the responsibility of `App\Middleware\ImsMessengerMiddleware`.

---

## `FileAclCache`

The ACL is expensive to build (multiple DB queries + event dispatch). `AclBuilder`
caches the result. The cache is invalidated by `incrementVersion()` — the next
request rebuilds and re-caches.

Do not interact with `FileAclCache` directly in component code. Only
`AclRepositoryInterface::incrementVersion()` is the correct invalidation signal.

---

## Listener Directory Layout

```
src/{module}/src/Listener/
    Register{Module}ResourcesListener.php
    Register{Module}RulesListener.php
```

> ~~`Register{Module}RouteMappingsListener.php`~~ — **DELETED**. Do not create this class.
> Route protection is managed via `ProtectRouteCommand` (admin UI).

Factories (if needed) live in:
```
src/{module}/src/Container/
    Register{Module}ResourcesListenerFactory.php
    ...
```

---

## Anti-Patterns

- **Do not** call `$acl->addResource()` or `$acl->allow()` outside of ACL event
  listeners. Resources and rules must be registered through the event pipeline.
- **Do not** call `$aclRepository->incrementVersion()` multiple times per
  request — one call per write operation is sufficient.
- **Do not** add `AuthorizationMiddleware` to individual route stacks — it runs once in the global pipeline.
- **Do not** remove `DispatchMiddleware` from the global pipeline — both coexist with `AuthorizationMiddleware`.
- **Do not** implement `Register{Module}RouteMappingsListener` — the `AclBuiltEvent`
  route-mapping API has been removed. Register routes via `ConfigProvider::getAclConfig()` or `ProtectRouteCommand`.
- **Do not** call `$acl->addResource()` or `$acl->allow()` outside of ACL event
  listeners. Resources and rules must be registered through the event pipeline.
