---
name: "webware-acl-ownership-command"
description: "ALWAYS load when creating or modifying any Command class that mutates store-scoped or user-scoped data. Enforces the ownership-capable command pattern: every data-mutation command that crosses an ownership boundary must implement RoleProviderInterface + StoreOwnedResourceInterface (or OwnershipAssertion equivalent) so the ACL can assert ownership before the handler executes."
argument-hint: "<what you are implementing — e.g. 'SaveManifestCommand', 'new store-scoped command', 'ownership assertion for transfer'>"
---

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## Core Rule

**Every command that mutates store-scoped or user-scoped data is the resource.**

The command itself implements the ACL resource contract. No separate entity fetch or resource stub is needed at the command-bus layer. The assertion fires before the handler runs, using only data already present on the command.

This is the governing pattern for **all data-mutation commands** across every module in this application.

---

## Required Interfaces

Every store-scoped mutation command must implement all three:

| Interface | Package | Purpose |
|---|---|---|
| `CommandInterface` | `webware/command-bus` | Marks as a bus command |
| `RoleProviderInterface` | `webware-acl` | Exposes the authenticated user as a `RoleInterface` |
| `StoreOwnedResourceInterface` | `ims-store` | Extends `ResourceInterface` + `ProprietaryInterface`; exposes the target `storeId` |

For user-profile-scoped mutations (user modifying their own profile), replace `StoreOwnedResourceInterface` with a `UserOwnedResourceInterface` equivalent and use `Webware\Acl\Assertion\OwnershipAssertion` instead.

---

## Canonical Command Shape

```php
final class SaveManifestCommand implements
    CommandInterface,
    RoleProviderInterface,
    StoreOwnedResourceInterface
{
    public function __construct(
        public readonly string $resourceId,  // ACL resource string e.g. 'store.manifest'
        public readonly int $storeId,        // target store — from request, NOT from user
        public readonly UserInterface $user, // authenticated user — from session/request attribute
    ) {}

    #[Override]
    public function getRole(): RoleInterface
    {
        return $this->user; // UserInterface extends RoleInterface
    }

    #[Override]
    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    #[Override]
    public function getOwnerId(): int
    {
        return $this->storeId; // the TARGET store, not the user's store
    }
}
```

---

## Critical Distinctions

### `$storeId` comes from the REQUEST, not the user

`getOwnerId()` returns the **target store's ID** — the store whose data is being mutated.
It is populated from the request (route param, form field, or resolved manifest's `storeId`).

The assertion then compares this against the user's `store_id` (via `$role->getDetail('store_id')`).
If they match, the user belongs to the target store and the operation is allowed.

**Never** set `getOwnerId()` to return `$this->user->getDetail('store_id')` — that makes the assertion
a tautology (user always owns their own store_id) and provides no protection.

### `$user` comes from the SESSION, not the request body

Always resolve the `UserInterface` from the request attribute set by `IdentityMiddleware`, never
from parsed body parameters.

---

## Assertion Classes

### Store-scoped: `Ims\Store\Acl\StoreOwnedResourceAssertion`

```php
public function assert(Acl $acl, ?RoleInterface $role = null, ?ResourceInterface $resource = null, $privilege = null): bool
{
    if (! $resource instanceof ProprietaryInterface) {
        return false;
    }
    if (! method_exists($role, 'getDetail')) {
        return false;
    }
    return (int) $role->getDetail('store_id') === (int) $resource->getOwnerId();
}
```

Fail-closed — denies if either side cannot be checked.

### User-profile-scoped: `Webware\Acl\Assertion\OwnershipAssertion`

```php
// Compares $role->getOwnerId() === $resource->getOwnerId()
// User::getOwnerId() returns the user's PK (not store_id)
// Profile resource's getOwnerId() returns the owning user's PK
```

Fail-closed — denies if either side lacks `ProprietaryInterface` or resource owner is null.
This is the webware-acl custom version, NOT Laminas\Permissions\Acl\Assertion\OwnershipAssertion
(which is fail-open).

---

## Transfer Exception

Transfers change a product's `store_id` by design. Do NOT apply `StoreOwnedResourceAssertion`
to transfer commands — the destination store will differ from the user's store, causing a false denial.

Transfer commands require a dedicated `TransferAuthorityAssertion` that asserts only against the
**source** store (the store the user is transferring FROM). The destination ownership is established
by the transfer itself.

See session memory: `transfer-workflow-concerns.md`.

---

## ResourceId Enum (Planned)

Resource identifier strings (e.g. `'store.manifest'`) will be replaced with a backed PHP Enum
(`ResourceId::StoreManifest->value`) to eliminate mistyped string bugs. All new commands should
be written with this migration in mind — avoid scattering the string in multiple places.

---

## Route-based ACL (primary mechanism for this application)

**`RouteResource`** is the HTTP-layer bridge that satisfies both the Laminas ACL resource contract
and the store-ownership assertion contract — without requiring the command to carry ACL fields.

Route name IS the resource ID. HTTP method IS the privilege. Ownership is resolved from the route
params at the ACL check layer, before the route stack executes.

### How it works

1. `RouteMiddleware` builds a `RouteResource` from the matched `RouteResult`
   and attaches it as `RouteResource::class` request attribute.
2. `AuthorizationMiddleware` (global pipeline, before `DispatchMiddleware`) reads `RouteResource::class`,
   calls `Acl::isAllowedRoute($user, $routeResource)`, and short-circuits with `ForbiddenHandlerInterface`
   on deny — the route stack never executes for denied requests.
3. `Acl::isAllowedRoute()` is **fail-closed** — `$this->acl->hasResource($routeResource)` returns `false`
   for unregistered routes, which denies access. Every accessible route must be registered.
4. `StoreOwnedResourceAssertion` receives the `RouteResource` as `$resource` — `getOwnerId()`
   reads the owner ID from route params using a three-level config:
   - Per-route options array (`$route->getOptions()['acl']['ownerId']`)
   - Global `route_param_map` config (`AclInterface::class['route_param_map'][$routeName]['ownerId']`)
   - Convention fallback (`'ownerId'`)

### Opting a route into ACL protection

Use the Admin UI "Unprotected Routes" section (`/admin/access/resources`) and click **Protect**.
This fires `ProtectRouteCommand`, which inserts one `acl_resource` row (route name = resource ID)
and one `acl_privilege` row per mapped HTTP method into the DB, then increments the ACL version.

Or from a listener at build time:
```php
$event->acl->addResource('route.name'); // in RegisterXxxResourcesListener
```

### Second canonical shape — HTTP-layer (RouteResource, no command fields needed)

When the command bus is not involved (route-level ACL only), the command does not need
`RoleProviderInterface` or `StoreOwnedResourceInterface`. The assertion fires at the
`AuthorizingDispatchMiddleware` layer using `RouteResource`:

```php
// ✅ No ACL fields on the command — ownership is checked at the HTTP layer
final readonly class CreateProductCommand implements CommandInterface
{
    public function __construct(
        public string $name,
        public int    $storeId,  // from request — used in handler only, NOT for ACL
    ) {}
}
```

The handler only runs if the route is allowed. Store ownership was already asserted by
`StoreOwnedResourceAssertion` acting on the `RouteResource` (which read `ownerId` from the
route param before the route stack executed).

---

## `AuthorizableCommandInterface` (ecosystem use, deferred for this application)

Supersedes `CommandInterface + RoleProviderInterface` for command-level ACL enforcement
inside the command bus (rather than at the HTTP route layer).

| Interface | Package | Purpose |
|---|---|---|
| `AuthorizableCommandInterface` | `webware-acl` | Extends `CommandInterface`; command carries its own role + resource for bus-level ACL |
| `RoleProviderInterface` | `webware-acl` | Exposes the authenticated user as a `RoleInterface` |
| `StoreOwnedResourceInterface` | `ims-store` | Extends `ResourceInterface` + `ProprietaryInterface`; exposes the target `storeId` |

**Status:** `AuthorizableCommandInterface` and `CommandHandlerMiddleware` exist in
`src/webware-acl/src/CommandBus/` but are not wired for this application. All route-level
ACL is handled by `AuthorizationMiddleware`. Implement command-level ACL in a future PR
when commands need to be dispatchable from non-HTTP contexts (CLI, queues, internal services).

---

## Proven By

Integration prototype: `test/AppTest/Acl/StoreOwnershipAssertionPrototypeTest.php`

Tests cover:
- `OwnershipAssertion` (profile): own data → allow, other user's data → deny, via aggregate
- `StoreOwnedResourceAssertion` (store): own store → allow, foreign store → deny, via aggregate

---

## Commands That Do NOT Require Ownership Interfaces

Some commands mutate ACL configuration data itself and run in the admin context where ownership
is already asserted by the global `AuthorizationMiddleware` pipeline. These commands are
**exempt** from `RoleProviderInterface` + `StoreOwnedResourceInterface`:

| Command | Reason |
|---|---|
| `ProtectRouteCommand` | Admin-only; registers a route as an ACL resource. No store ownership boundary. |
| `SaveRoleCommand` | Admin-only; no store boundary. |
| `DeleteRoleCommand` | Admin-only; no store boundary. |
| `SaveResourceCommand` | Admin-only; no store boundary. |
| `DeleteResourceCommand` | Admin-only; no store boundary. |
| `SaveRuleCommand` | Admin-only; no store boundary. |
| `DeleteRuleCommand` | Admin-only; no store boundary. |

### `ProtectRouteCommand` shape (for reference)

```php
final readonly class ProtectRouteCommand implements CommandInterface
{
    use NamedCommandTrait;

    public function __construct(
        public string $routeName,
        public array  $allowedMethods,
    ) {}
}
```

The `allowedMethods` are resolved from the router at request time by `ProcessProtectRouteMiddleware`
using `RouteCollectorInterface::getRoutes()`, so the handler only needs the route name and the
pre-resolved method list — no ACL resource fields on the command itself.
