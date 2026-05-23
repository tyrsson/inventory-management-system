# Integration Guide

This guide walks through the steps required to integrate a new Mezzio module
with webware-acl, so that its routes are protected and its resources can be
managed via the ACL Admin UI.

---

## Prerequisites

- `webware/acl` is installed and its `ConfigProvider` is loaded
- `IdentityMiddleware` is registered in the global pipeline (before route dispatch)
- The DB schema tables (`role`, `acl_resource`, `acl_privilege`, `acl_rule`,
  `acl_route_mapping`, `acl_version`) exist and are seeded with the base roles
- **`Mezzio\Authentication\UserInterface` is aliased to `Webware\UserManager\UserInterface`
  in the host-application's container** (see [User Identity Requirements](#user-identity-requirements) below)

---

## Overview

Integrating a module requires **three listener classes** and **three
`ConfigProvider` registrations**:

| Step | What to create | Event |
|---|---|---|
| 1 | `RegisterXxxResourcesListener` | `ResourcesLoadedEvent` |
| 2 | `RegisterXxxRulesListener` | `RulesLoadedEvent` |
| 3 | `RegisterXxxRouteMappingsListener` | `AclBuiltEvent` |

---

## Step 1 — Register Resources

Create a listener that adds the module's ACL resource(s) to the Laminas Acl:

```php
<?php

declare(strict_types=1);

namespace Ims\Manifest\Acl;

use Webware\Acl\Event\ResourcesLoadedEvent;

final class RegisterManifestResourcesListener
{
    public function __invoke(ResourcesLoadedEvent $event): void
    {
        $event->acl->addResource('manifest');
    }
}
```

**Rules:**

- One resource per conceptual domain (e.g. `manifest`, `ticket`, `transfer`).
  Do not add one resource per entity subtype unless ACL granularity requires it.
- The resource ID string must be unique across all modules.
- Do not add privileges here — privileges are added by the DB or Admin UI.

---

## Step 2 — Register Built-in Rules

Create a listener that adds rules which must be immutable (non-DB-manageable):

```php
<?php

declare(strict_types=1);

namespace Ims\Manifest\Acl;

use Webware\Acl\Entity\Privilege;
use Webware\Acl\Event\RulesLoadedEvent;

final class RegisterManifestRulesListener
{
    public function __invoke(RulesLoadedEvent $event): void
    {
        // Developer always has full access — not configurable via Admin UI
        $event->acl->allow('Developer', 'manifest', [
            PrivilegeInterface::READ,
            PrivilegeInterface::CREATE,
            PrivilegeInterface::UPDATE,
            PrivilegeInterface::DELETE,
        ]);
    }
}
```

**Rules:**

- Restrict this to **built-in grants only** — Developer super-access, or rules
  that must never be overridden by an Administrator.
- Do not replicate rules that belong in the DB (those are for Administrator
  configuration via the UI).
- Always use `PrivilegeInterface::READ / CREATE / UPDATE / DELETE` constants.
  **Never** hardcode strings like `'read'`.

---

## Step 3 — Register Route Mappings

Create a listener that maps every protected named route to a resource+privilege:

```php
<?php

declare(strict_types=1);

namespace Ims\Manifest\Acl;

use Webware\Acl\Entity\Privilege;
use Webware\Acl\Event\AclBuiltEvent;

final class RegisterManifestRouteMappingsListener
{
    public function __invoke(AclBuiltEvent $event): void
    {
        $event->addRouteMapping('manifest.list',         'manifest', PrivilegeInterface::READ);
        $event->addRouteMapping('manifest.detail',       'manifest', PrivilegeInterface::READ);
        $event->addRouteMapping('manifest.upload',       'manifest', PrivilegeInterface::READ);
        $event->addRouteMapping('manifest.upload.store', 'manifest', PrivilegeInterface::CREATE);
        $event->addRouteMapping('manifest.process',      'manifest', PrivilegeInterface::UPDATE);
        $event->addRouteMapping('manifest.finish',       'manifest', PrivilegeInterface::UPDATE);
    }
}
```

**Rules:**

- Map **every protected route** — `GET` and `POST` routes typically need
  separate entries with different privileges (`READ` vs `CREATE`/`UPDATE`).
- Route names must match exactly the names used in `RouteProvider.php`.
- Routes with no mapping are treated as **denied** by `AuthorizationMiddleware`.
  An unmapped public route (e.g. `/login`) should simply not have
  `AuthorizationMiddleware` in its stack.

---

## Step 4 — Register Listeners in ConfigProvider

Add all three listeners to the module's `ConfigProvider`:

```php
<?php

declare(strict_types=1);

namespace Ims\Manifest;

use Ims\Manifest\Acl\RegisterManifestResourcesListener;
use Ims\Manifest\Acl\RegisterManifestRulesListener;
use Ims\Manifest\Acl\RegisterManifestRouteMappingsListener;
use Webware\Acl\Event\AclBuiltEvent;
use Webware\Acl\Event\ResourcesLoadedEvent;
use Webware\Acl\Event\RulesLoadedEvent;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'listeners'    => $this->getListeners(),
        ];
    }

    public function getListeners(): array
    {
        return [
            ResourcesLoadedEvent::class => [
                ['listener' => RegisterManifestResourcesListener::class, 'priority' => 1],
            ],
            RulesLoadedEvent::class => [
                ['listener' => RegisterManifestRulesListener::class, 'priority' => 1],
            ],
            AclBuiltEvent::class => [
                ['listener' => RegisterManifestRouteMappingsListener::class, 'priority' => 1],
            ],
        ];
    }
}
```

**Priority**: Higher numbers run first. For most modules the default `1` is
correct. Use higher priority if your listener must extend the ACL before
another module's listener runs (rare).

---

## Step 5 — No Per-Route Middleware Required

`AuthorizationMiddleware` runs in the **global pipeline before**
Mezzio's `DispatchMiddleware`. You do **not** add any ACL middleware to
individual route stacks.

Protection is determined entirely by the route-to-resource mapping registered
in `RegisterXxxRouteMappingsListener`. If a route has no mapping, it is denied
(**fail-closed** — intentional). Intentionally public routes (e.g. `/login`)
must still be registered and explicitly allowed for the `Guest` role.

> **Never** add `AuthorizationMiddleware` to a route stack. It must only
> appear once, in the global pipeline.

---

## Step 6 — Seed Base Rules in the Database

After deploying the new module, seed the DB with the Administrator's default
rules for the new resource. This is typically done in a migration or seed file:

```sql
-- Allow Administrator to read and create manifests (as a starting point)
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT r.id, res.id, priv.id, 'allow'
FROM   role r
JOIN   acl_resource res ON res.resource_id = 'manifest'
JOIN   acl_privilege priv ON priv.privilege_id IN ('read', 'create')
                         AND priv.resource_pk = res.id
WHERE  r.role_id = 'Administrator';
```

After seeding, call `AclRepository::incrementVersion()` or truncate
`data/cache/acl.cache` so the next request triggers a cache rebuild.

---

## Checklist

```
□ RegisterXxxResourcesListener — adds resource(s) to Laminas Acl
□ RegisterXxxRulesListener — adds built-in immutable rules
□ RegisterXxxRouteMappingsListener — maps all protected routes
□ Three listeners registered in ConfigProvider::getListeners()
□ Route names in addRouteMapping() match RouteProvider exactly
□ Privilege constants used (PrivilegeInterface::READ etc.) — no hardcoded strings
□ DB seed: Administrator default rules for new resource
□ Cache invalidated after seeding (AclRepository::incrementVersion())
□ AuthorizationMiddleware in global pipeline (not in route stacks)
```

---

## Common Mistakes

| Mistake | Symptom |
|---|---|
| Listener not in `ConfigProvider::getListeners()` | Resource/rule/mapping silently missing from ACL on rebuild |
| Route name typo in `addRouteMapping()` | Route always returns 403 — no mapping found |
| Adding `AuthorizationMiddleware` to a route stack | Double ACL check; unexpected behaviour |
| Removing Mezzio's `DispatchMiddleware` from the global pipeline | Routes never dispatched after ACL pass |
| Hardcoded privilege string (`'read'`) instead of `PrivilegeInterface::READ` | Fragile — breaks if the constant value changes |
| Forgetting `incrementVersion()` after seeding DB rules | Cache not invalidated; stale ACL persists |
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
