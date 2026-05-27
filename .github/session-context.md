# Session Context — Inventory Management System
_Last updated: May 13, 2026 (evening)_

> **⚠ SESSION WORKFLOW — MANDATORY**
>
> - **Start of every session:** invoke `/session-start` in Copilot Chat — loads all skills, reads all docs, confirms state before any code is written.
> - **End of every session:** invoke `/session-end` in Copilot Chat — updates this file, writes session memory, confirms next steps.
>
> Both prompt files live in `.github/prompts/`. Neither protocol may be skipped.
> This file is **append-only** — never remove or shorten existing content without explicit user approval.

> **⚠ Runtime environment changed (April 28, 2026):** TrueAsync (`php-async` extension) has been **removed** from the active stack. After 3 hours rebuilding the Docker environment and the VS Code devcontainer, the project now runs as a standard Mezzio application served by the **PHP built-in web server** (`php -S`) inside a `php:latest` Docker container. There is **no PHP-FPM and no nginx** in the active devcontainer stack. The decision was driven by current usability issues in TrueAsync (SIGABRT crashes, `proc_open` incompatibility inside coroutines, devcontainer instability). The `src/mezzio-async/` source tree and all TrueAsync planning docs (`docs/planning/php-async-api.md`, `docs/planning/trueasync-bugs.md`) are **retained** for future reintegration once the extension matures.
>
> **Verified from:** `.devcontainer/docker-compose.yml` (image `php:latest`, command `sleep infinity`), `.devcontainer/docker/php/Dockerfile` (`FROM php:latest`), `public/index.php` (`PHP_SAPI === 'cli-server'`), `devcontainer.json` (port 8080 forwarded). DO NOT assume the stack — read the files.

---

## Project Overview

**Inventory Management System (IMS)** — Warehouse operations platform for furniture distribution stores.
Built for store-floor staff: receiving manifests from the DC, scanning SKU barcodes,
recording and photographing damage, submitting items for PQA assessment.

**Stack**: Mezzio + HTMX + Bootstrap 5.3.3 + Bootstrap Icons 1.13.1. PHP 8.6+ served by
the **PHP built-in web server** (`php -S 0.0.0.0:8080 -t public/`) inside a `php:latest`
Docker container (standard synchronous, single-threaded). ~~TrueAsync extension~~ — removed
(see note above); `src/mezzio-async/` retained for future use.

> **There is no PHP-FPM and no nginx in this project's active devcontainer.** Verified from
> `.devcontainer/docker-compose.yml` and `public/index.php`. Do not assume — verify.

---

## Mockup Status

### v1 — `resources/ui-mockup/v1/`
Complete reference implementation (custom CSS, no Bootstrap JS). Retained for reference only.
**Do not modify v1.**

### v2 — `resources/ui-mockup/v2/`  ← **ACTIVE**
Full Bootstrap 5.3.3 rebuild. All pages complete and working.

| File | Status | Notes |
|---|---|---|
| `custom.css` | ✅ Complete | Thin override layer only — brand tokens, sidebar fix, status badges, bottom nav, scan zone, photo grid |
| `app.js` | ✅ Complete | Minimal: store switcher active state, status toggle buttons |
| `index.html` | ✅ Complete | Dashboard — stat cards, quick actions, recent damage list |
| `inventory.html` | ✅ Complete | Product list, `btn-check` filter chips, search input |
| `manifests.html` | ✅ Complete | Manifest list with progress bars, status badges |
| `manifest-detail.html` | ✅ Complete | Summary card, damaged/clean item sections |
| `damage-detail.html` | ✅ Complete | Product identity, status toggles, damage notes, photo grid, PQA card, Send Images modal |
| `process-manifest.html` | ✅ Complete | Scan zone, manual entry, processed list, Finish Manifest modal |
| `process-manifest.js` | ✅ Complete | Hardware wedge + camera stub (ZXing placeholder), Bootstrap toast feedback |
| `settings.html` | ✅ Complete | Profile form, notification switches, store config, Change Password modal |
| `login.html` | ✅ Complete | Centered card, password show/hide toggle |
| `analytics.html` | ✅ Complete | Chart.js 4 — damage trend (line), status doughnut, items/manifest grouped bar, top categories horizontal bar, manifest summary table |

---

## Key Design Decisions

### Bootstrap Usage
- **Always use native Bootstrap** over custom implementations.
- All modals: `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` — zero custom JS.
- Sidebar: `offcanvas-lg offcanvas-start` — drawer on mobile, fixed panel on desktop.
- Notifications: `form-switch`. Filter chips: `btn-check` radio groups.
- Progress bars: native `.progress` / `.progress-bar`.

### Sidebar Layout Fix
The `offcanvas-lg` becomes a block element at ≥992px, which pushed content down.
**Fix (in `custom.css`):** At `@media (min-width: 992px)`, sidebar is `position: fixed`,
`top: 56px`, `bottom: 0`, `z-index: 1020`. `.ims-layout` gets `margin-left: 260px`.

### Navigation Structure
- **Desktop**: Fixed top navbar (56px) + fixed left sidebar (260px) + main content area.
- **Mobile**: Fixed top navbar + bottom nav (60px, 5 items) with centre scan FAB.
- Sidebar collapse accordion for store switching.

### Data Model (as shown in mockup)
Each product item displays three identifiers:
```
SKU: 195844 · AO#: A006523361 · 207-0401
```
- **SKU** — 6-digit integer (DC internal catalogue number)
- **AO#** — per-unit unique ID (format: `A` + 9 digits); encoded in Code 128B barcode on SKU card
- **Manifest ID** — format `{store}-{MMDD}` e.g. `207-0401`; the date = DC load date (consignment date)

Manifest ID is shown on all product list items, manifest detail rows, damage detail metadata.

### Barcode Scanning
- Barcode format: **Code 128B** on DC-printed SKU cards
- AO# is encoded in the barcode (confirmed by field analysis of physical cards)
- **ZXing-js** (`@zxing/library`) is the planned camera scanner library
- Hardware wedge scanners work natively (rapid keystrokes → Enter on pre-focused AO# input)
- `process-manifest.js` has a ZXing stub (simulates scan after 1.5s timeout)

### Charting
- **Chart.js 4.4.3** via CDN — used on analytics page
- Dark theme wired via `Chart.defaults.color` and `borderColor`
- Brand colour tokens in `analytics.html` script block mirror `custom.css` CSS variables
- No ApexCharts dependency — Chart.js was chosen for bundle size and simplicity

### PQA Email
- Each store has a `pqa_email` field (e.g. `pqa@store207.example.com`)
- "Send Images to PQA" on damage-detail triggers a Bootstrap modal pre-filled with:
  - To: store PQA email
  - Subject: `Damage Report — AO# … — {Product Name}`
  - Body: summary of damage
- Store PQA email is configurable in Settings → Store Configuration

---

## Routes / Page Connections
```
login.html → index.html (dashboard)
index.html → inventory.html, manifests.html, damage-detail.html, analytics.html, settings.html
inventory.html → damage-detail.html (damaged items)
manifests.html → process-manifest.html (in-progress), manifest-detail.html (complete)
manifest-detail.html → damage-detail.html
damage-detail.html ← back → manifest-detail.html
process-manifest.html ← back → manifests.html
analytics.html → manifest-detail.html, process-manifest.html (table links)
settings.html → login.html (sign out)
```

---

## What Was Removed
- **Transfer Lookup** — removed from all sidebars. Handled manually by the company; not in scope for v1.

---

## Pages Still Needing Sidebar Analytics Link
The abbreviated sidebars on `manifest-detail.html`, `damage-detail.html`,
`process-manifest.html`, and `settings.html` do not have a Reporting section.
Only `index.html`, `inventory.html`, `manifests.html`, and `analytics.html` have
the full Reporting nav section. This is intentional (these are detail pages).

---

## Next Steps

### Immediate (todo list)
1. ~~**Wire `LoginCommand` into login flow`**~~ — **dropped**. Auth audit logging is
   handled by dispatching `LogEvent(LogChannel::Security)` from `UserRepository::authenticate()`.
   The `LoginCommand` stub has been deleted (May 8, 2026) — no replacement needed.
   **`LogoutCommand`** — needed for forced session invalidation when: a user's role is
   changed, their account is set inactive (termination), or they self-transition roles
   (e.g. Warehouse → Sales). Will forcibly terminate their session so the new ACL state
   takes effect immediately.
2. **Migrate password requirements validator** — user has one in another project; needs
   adaptation for this codebase.
3. **Phase 5 — ACL listener wiring + AclDashboardWidget** ← **NEXT SPRINT PRIORITY**
   Full plan in `docs/planning/feature-admin-acl-listener-wiring-1.md`. Summary:
   - **Phase 1**: Augment `AclBuiltEvent` to carry a mutable route mappings registry
     (`addRouteMapping()` / `getRouteMappings()`); update `AclBuilder::buildFromArrays()`
     to read back listener-augmented mappings after dispatch.
   - **Phase 2**: Three listener classes in `src/webware-admin/src/Listener/`:
     `RegisterAdminResourcesListener` (on `ResourcesLoadedEvent`),
     `RegisterAdminRulesListener` (on `RulesLoadedEvent`),
     `RegisterAdminRouteMappingsListener` (on `AclBuiltEvent`).
   - **Phase 3**: Factories + wire `ConfigProvider` listeners key.
   - **Phase 4**: Remove `admin.dashboard` rows from `999_seed.sql` (prevent double-registration).
   - **Phase 5**: Unit tests for all new listeners and the augmented event.
   - Separately: `AclDashboardWidget` + `RegisterAclWidgetListener` in `webware-acl`
     contributing an ACL overview widget to the admin dashboard.
   - Load `htmx-mezzio` SKILL before implementing any templates.

### Authorization phases
4. **Phase 6 — RouteMapManager management UI** — Admin UI for managing
   route→resource+privilege mappings in `acl_route_map` table. Needs handler, template,
   route, and ACL grant for Admin/Developer roles. Load `htmx-mezzio` SKILL before implementing.
5. **Phase 7 — Role/Resource/Rule management UI** — Admin CRUD for `acl_role`,
   `acl_resource`, `acl_privilege`, `acl_rule` tables. Must invalidate ACL cache
   (increment `acl_version`) on any change.
6. **Phase 8 — CommandBus privilege integration** — wire privilege checks into CommandBus
   so commands can be authorized via ACL.

### Other work (not yet started)
7. **Integrate ZXing-js** into `process-manifest` for real camera scanning.
8. **Analytics endpoint** — JSON response for Chart.js data arrays.
9. **Analytics — date range switching** — the 30d/90d/6mo buttons are wired visually but
   not yet functional; will need an HTMX swap on chart data.
10. **Manifest, Inventory, Fulfilment modules** — not started.

### ACL Ownership Assertion — In Progress (May 13, 2026)
11. **Audit roles/resources/privileges for `StoreOwnedResourceAssertion` scope** — enumerate
    every store-scoped resource and every mutating privilege (standard + domain-specific:
    `process`, `flag-damage`, `approve`, `receive`, `pick-up`, etc.). Seed SQL is the
    source of truth. Domain privileges must be in `acl_privilege` before listener can reference them.
12. **Implement `RegisterOwnershipAssertionListener`** — un-deprecate, call
    `$acl->allow([store-roles], [store-resources], ['update', 'delete', ...domain privs], new StoreOwnedResourceAssertion())`
    — only after audit above is complete.
13. **Unit tests** for `OwnershipAssertion`, `StoreOwnedResourceAssertion`, `CommandHandlerMiddleware` (override).
14. **Wire `HandleAccessDeniedTrait`** into each `Process{Action}Middleware` that dispatches
    an ownership-capable command.

### Future: `webware/composer-plugin`
Feasibility assessed in `docs/planning/feature-admin-acl-listener-wiring-1.md` §10.
Verdict: feasible and worthwhile. Suggested milestones:
1. Cache invalidation only (no DB writes) — simplest scaffold
2. ACL + route mapping DB writes (enables Approach B caching strategy)
3. Schema migration runner
Not started. Requires a separate package and separate plan.

---

### Developer Role — ✅ Added (May 7, 2026)
`Developer` role added to `data/schema/002_role.sql` (comment) and `data/schema/999_seed.sql`.
- Inherits from `Administrator` (sits above it in the hierarchy)
- Explicit deny rules for `user/login` and `user/register` added (matches all authenticated role pattern)
- Intended for developer tools, setup/config UI, and future `webware/composer-plugin` integration

---

## Key Design Decisions — May 13, 2026 (evening session)

### ACL: Assertion Registration Strategy — CONFIRMED

Two separate strategies are required depending on assertion scope:

**1. `OwnershipAssertion` — DB-driven (`acl_rule_assertion` table)**
- Scoped to a single rule: `member → user → update`
- Seeded via `data/schema/999_seed.sql` INSERT into `acl_rule_assertion`
- FQCN stored: `Webware\Acl\Assertion\OwnershipAssertion`
- `AclBuilder::buildAssertion()` instantiates via `new $fqcn()` (no-arg constructor — confirmed)
- Seed row added this session using a subquery to avoid hardcoded PKs (idempotent with `ON DUPLICATE KEY UPDATE`)

**2. `StoreOwnedResourceAssertion` — Listener-driven (`RegisterOwnershipAssertionListener` on `AclBuiltEvent`)**
- Must be applied as a bulk `$acl->allow(['member', 'sales', ...], [store-scoped resources], ['update', 'delete'], new StoreOwnedResourceAssertion())`
- DB-driven approach would require dozens of rows (role × resource × privilege) — not maintainable
- `RegisterOwnershipAssertionListener` is currently a **no-op** (deprecated); must be un-deprecated and properly implemented
- **NOT YET IMPLEMENTED** — blocked pending role/resource/privilege audit

### ACL: Assertions Do NOT Inherit Through Role Hierarchy — CONFIRMED
Laminas `Acl::isAllowed()` walks the role DAG depth-first. When a matching rule is found it evaluates that rule's assertion. The assertion is bound to the specific role's rule — child roles do not inherit parent assertions. Explicit `allow()` calls with assertions must be made per-role.

### ACL: `create` privilege does NOT need `StoreOwnedResourceAssertion` — CONFIRMED (reasoning)
`store_id` on create commands is injected from the authenticated session, not from user input. Cross-store creation is prevented upstream. Only `update` and `delete` (and domain-specific mutating privileges like `process`, `flag-damage`) need the assertion.

### `RegisterOwnershipAssertionListener` — Pending Audit Before Implementation
Before implementing the listener, the following must be mapped:
1. **Roles** — which roles operate within a store boundary? (`member`, `sales`, `manager`? hierarchy TBD)
2. **Resources** — which resources are store-scoped? (`product`, `manifest`, `ticket`, `transfer` confirmed; `manifest.process`, damage flagging, PQA submission, etc. not yet fully identified)
3. **Privileges** — `update` and `delete` confirmed; domain-specific privileges (`process`, `flag-damage`, `approve`, `receive`, `pick-up`) must be registered in `acl_privilege` before the listener can reference them
4. Seed SQL (`999_seed.sql`) is the source of truth — audit it when resuming

---

## Application Layer Status — May 13, 2026 (evening session)

### Files Created or Modified

| File | Change |
|---|---|
| `src/webware-acl/src/CommandBus/Middleware/CommandHandlerMiddleware.php` | **CREATED** — ACL-aware override of upstream `CommandHandlerMiddleware`; injects `AclInterface`, `OwnershipAssertion`, `StoreOwnedResourceAssertion`; throws `AccessDeniedException` on ownership failure |
| `src/webware-acl/src/Container/CommandHandlerMiddlewareFactory.php` | **CREATED** — Factory for the above |
| `src/webware-acl/src/Exception/AccessDeniedException.php` | **CREATED** — extends `Webware\Acl\Exception\RuntimeException` |
| `src/webware-acl/src/HandleAccessDeniedTrait.php` | **CREATED** — Used by PSR-15 `Process{Action}Middleware` to catch `AccessDeniedException`; calls `danger(hops:0, now:true)`, dispatches `LogEvent(Security, Critical)`, returns `$handler->handle($request)` (no redirect) |
| `src/webware-acl/src/AclInterface.php` | **MODIFIED** — added `getAcl(): LaminasAclInterface` |
| `src/webware-acl/src/Acl.php` | **MODIFIED** — implemented `getAcl()` returning `$this->acl` |
| `src/webware-acl/src/ConfigProvider.php` | **MODIFIED** — registered `CommandHandlerMiddleware::class => CommandHandlerMiddlewareFactory::class` (replaces upstream factory) |
| `src/webware-acl/src/Listener/RegisterOwnershipAssertionListener.php` | **NOT YET MODIFIED** — still a no-op; needs un-deprecating and implementation after resource/role/privilege audit |
| `data/schema/999_seed.sql` | **MODIFIED** — added `acl_rule_assertion` INSERT for `member → user → update → OwnershipAssertion` (subquery-based, idempotent) |
| `.github/skills/htmx-mezzio/SKILL.md` | **MODIFIED** — Toast/SystemMessenger pattern section added |
| `.github/skills/webware-coding-standard/SKILL.md` | **MODIFIED** — PHPUnit 11 `#[CoversClass]` rule added |
| All 19 `.github/skills/*/SKILL.md` | **MODIFIED** — NEVER REMOVE directive added to each |
| `.github/prompts/session-start.prompt.md` | **CREATED** |
| `.github/prompts/session-end.prompt.md` | **CREATED** |
| `test/AppTest/Acl/StoreOwnershipAssertionPrototypeTest.php` | **MODIFIED** — risky test fixed (`#[CoversClass]` attributes added) |

### CommandHandlerMiddleware — Key Pattern
```php
// Registered under the upstream key — replaces upstream factory in DI
CommandHandlerMiddleware::class => CommandHandlerMiddlewareFactory::class

// Logic (simplified):
if ($command instanceof RoleProviderInterface) {
    if ($command instanceof StoreOwnedResourceInterface) {
        // StoreOwnedResourceAssertion::assert()
    } elseif ($command instanceof ProprietaryInterface) {
        // OwnershipAssertion::assert()
    }
    // throw AccessDeniedException on failure
}
```

### HandleAccessDeniedTrait — Key Pattern
```php
// In Process{Action}Middleware — catch point for CommandBus exceptions
} catch (AccessDeniedException $e) {
    return $this->handleAccessDenied($e, $request, $handler, $dispatcher);
}

// Trait method:
// 1. $messenger->danger('...', hops: 0, now: true)  ← current render, no redirect
// 2. dispatch LogEvent(Security, Critical)
// 3. return $handler->handle($request)               ← renders current page with toast
```
- No DB migration needed yet — applies on next clean seed

### ACL Listener Wiring — Planning complete (May 7, 2026)
Full implementation plan at `docs/planning/feature-admin-acl-listener-wiring-1.md`.
Includes: 5 implementation phases, caching strategy analysis (Approach A chosen),
and `webware/composer-plugin` feasibility assessment. See §9 and §10 of the plan.

### Caching strategy decision (May 7, 2026)
`FileAclCache` is explicitly a **DB-state cache only**. Listener-contributed ACL data
(resources, rules, route mappings) is re-applied on every `buildFromArrays()` call via
PSR-14 events — it is **not** written to or read from the cache file. This is the chosen
approach (Approach A) for the current sprint. Migration to Approach B (write-once to DB
via `webware/composer-plugin`) is planned as a future iteration.

### Planning documents (May 7, 2026)
- `docs/planning/feature-admin-acl-listener-wiring-1.md` — ACL listener wiring plan +
  caching strategy decision + `webware/composer-plugin` feasibility

---

## Application Layer Status (as of May 7, 2026)

### Packages Installed
- `mezzio/mezzio-authentication ^1.13`
- `mezzio/mezzio-authorization ^1.11`
- `mezzio/mezzio-authorization-acl ^1.13`
- `mezzio/mezzio-valinor ^1.0`
- `mezzio/mezzio-authentication-session` (dev)
- All ConfigProviders injected into `config/config.php`

### Database Schema (`data/schema/`)
- 15 table files (001–015) + `999_seed.sql`
- **`015_log.sql`** — `log` table for DB-level Monolog logging. Columns: `id BIGINT AUTO_INCREMENT`, `channel VARCHAR(64)`, `level VARCHAR(20)`, `uuid VARCHAR(36)`, `message TEXT`, `time INT UNSIGNED`, `user_identifier VARCHAR(255)`, `context JSON NULL`. Indexes on `level`, `channel`, `time`.
- All files have `DROP TABLE IF EXISTS` for clean reimport
- `user` is backticked everywhere (reserved word)
- **`role` table**: `id TINYINT UNSIGNED AUTO_INCREMENT` + `role_id VARCHAR(50)` (was `name`)
  — `role_id` is the Laminas ACL role identifier; Title Case with spaces is fine
- **`user` table**: `display_name VARCHAR(150)` (was `name`) — renamed to avoid conflict
  with `NamedCommandTrait`'s `protected readonly string $name` property
- `999_seed.sql`: inserts use `role_id` column, `ON DUPLICATE KEY UPDATE role_id`
- FK-safe DROP order (reverse): `transfer_item` → `transfer` → `ticket_item` → `ticket`
  → `product_image` → `product_status` → `product` → `manifest_item` → `manifest`
  → `sku_catalogue` → `major_code` → `user` → `role` → `store`

### User Module (`src/User/src/`)
**Do not touch without reading files first — user has done heavy refactoring.**

Key decisions made:
- `Entity\User` — `final readonly`, implements `UserInterface` + `NamedCommandInterface`
  (via `NamedCommandTrait`). Properties: `displayName` (not `name` — avoids trait clash).
- `Entity\Role` — `final readonly`, implements `NamedCommandInterface`. Properties:
  `int $id`, `string $roleId`. No `$name` property — trait owns that for FQCN resolution.
- `Entity\Store` — `final readonly`, implements `NamedCommandInterface`.
- `Repository\UserRepositoryInterface` — extends `UserRepositoryContract` only (not `UserInterface`).
  Identity comes from session at runtime, not the repository.
- `Repository\UserRepository` — composes `TableGateway('user', $adapter)` internally;
  uses `getSql()` + `prepareStatementForSqlObject()` for all DML queries.
  Manual `hydrate()` method retained until phpdb readonly-clone support is merged.
  See `@todo` in `UserRepositoryFactory` for the HydratingResultSet upgrade path.
- `Command\CreateUserCommand` — implements `NamedCommandInterface`, wraps `UserInterface`.

### Query Conventions
All DML queries must use `PhpDb\Sql\*` via `TableGateway::getSql()`.
See `.github/instructions/phpdb-sql-queries.instructions.md`.

### Logging (`axleus/axleus-log`)
- **`MonologMiddleware`** is in `config/pipeline.php` (added after `ServerUrlMiddleware`).
- **`PhpDbHandler`** (`vendor/axleus/axleus-log/src/Handler/PhpDbHandler.php`) — Monolog handler that writes to the `log` DB table using `PhpDb\Sql\Sql` directly (not TableGateway). Fields: `channel`, `level`, `uuid`, `message`, `time`, `user_identifier`, `context` (JSON).
- **`PhpDbHandlerFactory`** reads `config[ConfigProvider::class]['table']` and `PhpDb\Adapter\AdapterInterface` from the container.
- **`LogFactory`** pushes `PhpDbHandler`. Processors: `RamseyUuidProcessor`, `PsrLogMessageProcessor`.
- Config cache must be cleared after any pipeline change: `rm -f data/cache/config-cache.php`.

### Tracy / SQL Profiler
- **`ProfilingDelegator`** (`Webware\\Traccio\\PhpDb\\ProfilingDelegator`) is registered as a delegator for `AdapterInterface` in `config/autoload/development.local.php` (dev-only). Attaches `Profiler` to the DB adapter, enabling `SqlProfilerPanel` in Tracy (dev mode only).
- `ProfilerInterface::class => Profiler::class` alias also added to `development.local.php` — `TracyDebuggerMiddlewareFactory` calls `$container->has(ProfilerInterface::class)` to decide whether to render the SQL panel; without the alias, it always returns `false`.

### Htmx Module — EnumTrait
- `src/Htmx/src/EnumTrait.php` — utility trait for backed enums: `tryFromName`, `fromName`, `names`, `values`, `toArray(bool $normalize, string|callable|null $valueTreatment)`. Namespace `Htmx`.
- `src/App/src/EnumTrait.php` — **deleted**. Both `Htmx\Request\Header` and `Htmx\Response\Header` now `use Htmx\EnumTrait`.

### RegistrationHandler — pending fix
- `src/User/src/RequestHandler/RegistrationHandler.php` still uses `$request->hasHeader(HtmxRequestHeader::Request->value)`. Should use `$request->getAttribute(HtmxRequestHeader::Request->value)` because `ServerRequestFilter` maps HTMX headers to attributes. **Not yet changed** — confirm before applying.

### Authorization / ACL (`src/webware-acl/src/`) — ✅ Namespace refactor complete (May 6, 2026)

Full authorization system was implemented in the May 5 session. On May 6 the entire
namespace was reorganised — the `Webware\Acl\Acl\*` double-segment was eliminated.

**Current file layout:**

```
src/webware-acl/src/
  Acl.php                          Webware\Acl\Acl
  AclBuilder.php                   Webware\Acl\AclBuilder
  AclInterface.php                 Webware\Acl\AclInterface
  ConfigProvider.php
  Authentication/
    DefaultUserFactory.php         Webware\Acl\Authentication\DefaultUserFactory
  Cache/
    AclCacheInterface.php
    FileAclCache.php
  Container/                       ← ALL factories live here
    AclBuilderFactory.php
    AclFactory.php
    AclRepositoryFactory.php
    AuthorizationMiddlewareFactory.php
    FileAclCacheFactory.php
    IdentityMiddlewareFactory.php
  Entity/
  Event/
  Exception/
  Middleware/                      ← ALL middleware live here
    AuthorizationMiddleware.php    Webware\Acl\Middleware\AuthorizationMiddleware
    IdentityMiddleware.php         Webware\Acl\Middleware\IdentityMiddleware
  Repository/
    AclRepository.php
    AclRepositoryInterface.php
```

**Consumer files updated:**
- `src/User/src/RouteProvider.php` — `use Webware\Acl\Middleware\AuthorizationMiddleware`
- `src/App/src/RouteProvider.php` — `use Webware\Acl\Middleware\AuthorizationMiddleware`
- `config/pipeline.php` — `use Webware\Acl\Middleware\IdentityMiddleware`

**Config shape (unchanged):**
```php
'webware-acl' => [
    'base_role'  => 'guest',
    'login_path' => '/login',
]
```

### Auth Audit Logging — ✅ Complete (May 6, 2026)
`UserRepository::authenticate()` dispatches a `LogEvent(LogChannel::Security, Level::Info)`
on successful authentication. `EventDispatcherInterface` injected via constructor.
`UserRepositoryFactory` updated to inject it. No vendor modification required.

### `User\Entity\User` — Bug fixed (May 6, 2026)
All 9 `with*()` methods were missing `$this->verificationToken, $this->tokenCreatedAt`
arguments (constructor positions 10–11). `$this->roles` was landing in the
`verificationToken` slot. All methods now pass the full 13-argument constructor correctly.

### Admin Dashboard Widget System (`src/webware-admin/src/`) — ✅ Complete + renamed (May 7, 2026)

PSR-14 event-driven widget system for the admin dashboard. Modules contribute widgets
by registering a single PSR-14 listener — zero changes to `webware-admin` per module.

**File layout (post-rename):**
```
src/webware-admin/src/
  ConfigProvider.php
  Container/
    DashboardHandlerFactory.php
    DashboardMiddlewareFactory.php          (was CollectDashboardWidgetsMiddlewareFactory)
  Event/
    RegisterWidgetEvent.php                 (was CollectDashboardWidgetsEvent)
  Middleware/
    DashboardMiddleware.php                 (was CollectDashboardWidgetsMiddleware)
  RequestHandler/
    DashboardHandler.php
  Widget/
    WidgetInterface.php                     PHP 8.4 get-hooked props; extends ResourceInterface
    AclWidgetFilterIterator.php             FilterIterator — calls acl->isAllowed() per widget
```

**`WidgetInterface` contract:**
```php
interface WidgetInterface extends ResourceInterface
{
    public string $title      { get; }
    public string $resourceId { get; }   // ACL resource — also satisfies getResourceId()
    public string $privilege  { get; }
    public string $template   { get; }   // e.g. 'product::admin-widget'
    public int    $order      { get; }
}
```

**Flow:** `DashboardMiddleware` dispatches `RegisterWidgetEvent` →
listeners call `addWidget()` → `AclWidgetFilterIterator` wraps sorted `ArrayIterator` and
filters by `acl->isAllowed($roles, $widget->resourceId, $widget->privilege)` → iterator
set as `RegisterWidgetEvent::class` request attribute → `DashboardHandler` passes
it to `admin::dashboard` template → template calls `$this->partial($widget->template, $widget)`.

**Route — ✅ wired (May 7, 2026):**
```php
$app->get('/admin', [
    AuthorizationMiddleware::class,
    DashboardMiddleware::class,
    DashboardHandler::class,
], 'admin.dashboard');
```

**Docs:** `src/webware-admin/docs/dashboard-widget-system.md` (updated May 7)

### phpdb Skill
- `.github/skills/phpdb/SKILL.md` — created. Covers: `Sql` direct vs `TableGateway`, all DML patterns, last-insert-id, `Profiler` shape, `ProfilingDelegator` wiring, `mysql.local.php` config shape.

### Hot-Reload
~~Disabled via `config/autoload/development.local.php` (`mezzio-async.hot-reload.enabled = false`)
to prevent TrueAsync SIGABRT crash on `fgets()` in `proc_open` pipe inside coroutine.~~
**No longer applicable** — TrueAsync and `mezzio-async` are not active. The PHP built-in
web server (`php -S`) must be manually stopped and restarted after code changes in the
devcontainer terminal.

### NamedCommandTrait / commandbus
`NamedCommandTrait` declares `protected readonly string $name`. Domain classes must NOT
have a `$name` property. Planned fix: rename to `$commandName` in the command-bus package.

---

## Dev Server

> **Verified stack**: PHP built-in web server (`php -S`) inside `php:latest` container.
> No PHP-FPM. No nginx. Source: `.devcontainer/docker-compose.yml`, `public/index.php`.

- **Mezzio app**: PHP built-in server on port 8080 (forwarded by devcontainer)
  ```bash
  php -S 0.0.0.0:8080 -t /workspaces/inventory-management-system/public/
  ```
- **v2 mockup**: PHP built-in server on port 7655
  ```bash
  php -S 0.0.0.0:7655 -t /workspaces/inventory-management-system/resources/ui-mockup/v2/ &
  ```
- **v1 mockup**: port 7654 (python3 http.server or PHP)
- Files also browseable directly via Windows→WSL2 filesystem bridge (`file://`)

---

## Repo Info
- Repository: `tyrsson/inventory-management-system`
- Branch: `build-authorization` (active), `0.1.x` (default)
- Active PR: #12 — "Initial commit of webware-acl setup and wiring"
- Workspace: `/workspaces/inventory-management-system`
- v2 mockup path: `resources/ui-mockup/v2/`
- Planning docs: `docs/planning/`
- Async future docs: `docs/async/`
- Architecture blueprint: `docs/Project_Architecture_Blueprint.md`
