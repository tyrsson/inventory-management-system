# Integration Guide

This guide walks through the steps required to integrate a new Mezzio module
with webware-acl so that its routes are protected by `AuthorizationMiddleware`.

> **ACL is entirely config-driven. There are no DB tables in use.**
> All roles, resources, and rules are declared in `ConfigProvider::getAclConfig()`.
> Configs from every loaded module are deep-merged by Laminas Config before the
> `AclFactory` builds the `Laminas\Permissions\Acl\Acl` instance.

---

## Prerequisites

- `webware/acl` is installed and its `ConfigProvider` is loaded in `config/config.php`
- `IdentityMiddleware` is registered in the global pipeline before `AuthorizationMiddleware`
- `AuthorizationMiddleware` is registered in the global pipeline before `DispatchMiddleware`
- **`Mezzio\Authentication\UserInterface` is aliased to `Webware\UserManager\UserInterface`
  in the host-application's container** (see [User Identity Requirements](#user-identity-requirements) below)

---

## Overview

Integrating a module requires **one method** in the module's `ConfigProvider`:

```php
public function getAclConfig(): array
```

This method returns an array that is registered under the `AclInterface::class`
config key. `AclFactory` reads the merged config at container build time and
constructs the ACL — no events, no database, no cache.

---

## Step 1 — Implement `getAclConfig()` in `ConfigProvider`

```php
<?php

declare(strict_types=1);

namespace Acme\Widget;

use Webware\Acl\AclInterface;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            AclInterface::class => $this->getAclConfig(),
            // ... other keys
        ];
    }

    public function getAclConfig(): array
    {
        return [
            'resources' => [
                'acme.widget.list',
                'acme.widget.detail',
                'acme.widget.create',
                'acme.widget.update',
            ],
            'allow' => [
                'Member' => [
                    'acme.widget.list',
                    'acme.widget.detail',
                ],
                'Editor' => [
                    'acme.widget.create' => [\Acme\Widget\Acl\OwnershipAssertion::class],
                    'acme.widget.update' => [\Acme\Widget\Acl\OwnershipAssertion::class],
                ],
            ],
            'deny' => [],
        ];
    }
}
```

### Config array shape

| Key | Type | Description |
|---|---|---|
| `roles` | `array<string, string[]>` | Role hierarchy: `roleId => [parentRoleId, ...]`. Define only once in the central module that owns the role domain. |
| `resources` | `string[]` | Flat list of **route name strings** to protect. Must match `RouteProvider` exactly. |
| `allow` | `array<string, list\|assoc>` | Role → resource grant. Plain list for no assertion; associative for per-resource assertion class list. |
| `deny` | `array<string, list\|assoc>` | Same structure as `allow`. Explicit denials override inherited allows. |

> **Resources are route names.** `RouteResource::getResourceId()` returns the matched
> route name, and `AclFactory` registers those same strings as Laminas ACL resources.
> There is no separate "abstract resource" concept — one route name = one resource.

### Role hierarchy

Roles are defined once — in whichever module owns the role domain for the
application — and inherited by all feature modules through config merging.
**Do not redeclare the role hierarchy in a feature module.** Only add
`resources` and `allow`/`deny` for your module's routes.

### Assertion classes

When a rule requires an ownership or context check, pass a list of
fully-qualified assertion class names as the value for that resource.
`AclFactory` will resolve each class from the container (it must implement
`Laminas\Permissions\Acl\Assertion\AssertionInterface`) and wrap multiple
assertions in an `AssertionAggregate`.

---

## Step 2 — No Per-Route Middleware Required

`AuthorizationMiddleware` runs in the **global pipeline before**
Mezzio's `DispatchMiddleware`. You do **not** add any ACL middleware to
individual route stacks.

The ACL is checked for every matched request. If a route name is not registered
as a resource, `Acl::isAllowedRoute()` returns `false` (**fail-closed** —
intentional). Routes that must be publicly accessible (e.g. login, registration)
must still be listed in `resources` and granted to the `Guest` role in `allow`.

> **Never** add `AuthorizationMiddleware` to a route stack. It must only
> appear once, in the global pipeline.

---

## Checklist

```
□ getAclConfig() implemented in ConfigProvider
□ AclInterface::class => $this->getAclConfig() in ConfigProvider::__invoke()
□ All protected route names listed in 'resources'
□ Allow rules declared for every role that needs access
□ Guest routes explicitly allowed for 'Guest' role
□ Route names in getAclConfig() match RouteProvider exactly
□ Role hierarchy NOT redeclared — only ims-store owns 'roles'
□ AuthorizationMiddleware in global pipeline (not in route stacks)
□ DispatchMiddleware still present in global pipeline (after AuthorizationMiddleware)
```

---

## Common Mistakes

| Mistake | Symptom |
|---|---|
| Route name typo in `resources` or `allow` | Route always returns 403 — resource not registered or rule not matched |
| Route listed in `allow` but missing from `resources` | `isAllowedRoute()` returns `false` — resource must be registered before allow rules can apply |
| Redeclaring `roles` in a feature module | Role hierarchy merges incorrectly; parent resolution may fail |
| Adding `AuthorizationMiddleware` to a route stack | Double ACL check; unexpected behaviour |
| Removing Mezzio's `DispatchMiddleware` from the global pipeline | Routes never dispatched after ACL pass |
| Public route (e.g. login) not in `resources` + `allow Guest` | Guest users get 403 on the login page |
| Resolving `Mezzio\Authentication\UserInterface` without the alias | `isAllowed()` fails — `GuestUser` does not satisfy `RoleInterface` without proper wiring |

---

## User Identity Requirements

`webware-acl` resolves `Mezzio\Authentication\UserInterface::class` from the
container. Mezzio's own `DefaultUser` does **not** implement `RoleInterface` or
`ProprietaryInterface`, so `$acl->isAllowed($user, ...)` and ownership
assertions will fail if the bare Mezzio interface is used.

The host application **must** alias Mezzio's interface to
`Webware\UserManager\UserInterface` in its DI configuration:

```php
// config/autoload/dependencies.global.php  (host application only — not in any package)

use Mezzio\Authentication\UserInterface as MezzioUserInterface;
use Webware\UserManager\UserInterface as UserManagerUserInterface;

return [
    'dependencies' => [
        'aliases' => [
            // Resolving Mezzio's interface yields our richer implementation.
            MezzioUserInterface::class => UserManagerUserInterface::class,
        ],
        'factories' => [
            // The factory that creates User instances is registered under our
            // interface key — NOT under MezzioUserInterface::class directly.
            UserManagerUserInterface::class => \Webware\UserManager\Container\UserFactory::class,
        ],
    ],
];
```

> **Why the alias lives in the host app:**  
> `webware-acl` must not depend on `webware-usermanager` (circular dependency)
> and `webware-usermanager` must not own the DI key for
> `Mezzio\Authentication\UserInterface` (it does not own that package). The
> host application is the only place where both packages are simultaneously in
> scope.

See [`webware-usermanager` docs/user-interface.md](../../webware-usermanager/docs/user-interface.md)
for the full interface contract and concrete class requirements.
