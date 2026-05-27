# Project Architecture Blueprint — Inventory Management System

_Generated: May 3, 2026_

---

## Runtime Status

> **Active runtime (verified):** PHP built-in web server (`php -S 0.0.0.0:8080 -t public/`) inside a `php:latest` Docker container. Standard synchronous PHP — no PHP-FPM, no nginx, no TrueAsync.
>
> **Future target runtime:** TrueAsync (`php-async` extension). The `src/mezzio-async/` package and all docs under `docs/async/` are retained for reintegration once the extension stabilises. See `docs/async/` for async planning.

---

## 1. Architectural Overview

**Inventory Management System (IMS)** is a warehouse operations application built on the **Mezzio** micro-framework. It targets furniture distribution warehouse staff: receiving DC manifests, scanning SKU barcodes, recording and photographing damage, managing product status, and submitting items for PQA assessment.

### Guiding Principles

| Principle | Implementation |
|---|---|
| Module isolation | Each domain area is a separate `src/{Module}/` package with its own `ConfigProvider` |
| One handler per action | Every HTTP action has a dedicated `RequestHandlerInterface` implementation |
| Middleware for input | Incoming data is processed by middleware before reaching handlers |
| Command bus for writes | All mutating operations go through `webware/command-bus` (no direct DB calls from handlers) |
| Repository/Entity for data | `PhpDb` TableGateway + hand-rolled repositories; no Active Record |
| Composition over inheritance | `readonly` entities, `final` classes, traits for shared behaviour |

### Architectural Pattern

**Layered architecture** with clear boundary enforcement:

```
HTTP Request
    │
    ▼
Mezzio Pipeline (middleware stack)
    │   ServerUrlMiddleware, MonologMiddleware, AuthenticationMiddleware,
    │   AuthorizationMiddleware, DetectAjaxRequestMiddleware, ...
    │
    ▼
Module Middleware (input processing, validation)
    │   e.g. RegistrationMiddleware transforms raw POST → Command
    │
    ▼
RequestHandler (dispatch decision, template/JSON response)
    │   Reads request attributes set by middleware
    │   Calls CommandBus for writes, Repository for reads
    │
    ▼
CommandBus Pipeline
    │   Pre(100) → CommandHandlerMiddleware(1) → Post(-100)
    │   CommandHandler executes domain logic + persists via Repository
    │   PostHandleMiddleware dispatches PSR-14 events
    │
    ▼
Repository → PhpDb TableGateway → MySQL 8.4
```

---

## 2. Architecture Diagram

### Module Dependency Graph

```
┌─────────────────────────────────────────────────────────────┐
│                    Mezzio Application                        │
│                    config/pipeline.php                       │
└──────────────────────────┬──────────────────────────────────┘
                           │ boots
           ┌───────────────┼───────────────┐
           ▼               ▼               ▼
    ┌────────────┐  ┌────────────┐  ┌────────────┐
    │  src/App   │  │ src/Htmx   │  │ src/User   │
    │            │  │            │  │            │
    │ Dashboard  │  │ HTMX layer │  │ Auth/Users │
    │ Ping       │  │ Middleware │  │ Commands   │
    │ RouteProvider│ │ View helpers│ │ Listeners  │
    └────────────┘  └────────────┘  └─────┬──────┘
                                          │ uses
                                    ┌─────▼──────┐
                                    │  PhpDb     │
                                    │  MySQL 8.4 │
                                    └────────────┘
```

### Request Lifecycle — HTMX vs Full-Page

```
Browser request
    │
    ├─[HTMX XHR]─────► DetectAjaxRequestMiddleware sets attribute
    │                   DisableBodyMiddleware suppresses layout layer
    │                   Handler returns partial template fragment
    │
    └─[Full navigation]► Handler returns full 3-layer render
                         (layout.phtml > body.phtml > page.phtml)
```

---

## 3. Module Catalogue

### `src/App` — Application Core

**Namespace:** `App`  
**Purpose:** Bootstrap glue, dashboard, and top-level routing.

| Component | File | Responsibility |
|---|---|---|
| `ConfigProvider` | `src/App/src/ConfigProvider.php` | Registers App dependencies |
| `RouteProvider` | `src/App/src/RouteProvider.php` | Declares app-level routes |
| `DashboardHandler` | `src/App/src/RequestHandler/DashboardHandler.php` | Renders dashboard |
| `PingHandler` | `src/App/src/RequestHandler/PingHandler.php` | Health-check endpoint |

---

### `src/Htmx` — HTMX Integration Layer

**Namespace:** `Htmx`  
**Purpose:** Provides HTMX-aware middleware, view helpers, and typed enums for HTMX request/response headers.

| Component | File | Responsibility |
|---|---|---|
| `DetectAjaxRequestMiddleware` | `src/Htmx/src/Middleware/` | Detects `HX-Request` header; maps to PSR-7 attributes |
| `DisableBodyMiddleware` | `src/Htmx/src/Middleware/` | Suppresses layout/body layers for HTMX partial responses |
| `ServerRequestFilter` | `src/Htmx/src/Request/ServerRequestFilter.php` | Maps HTMX headers → request attributes (use `getAttribute`, not `hasHeader`) |
| `Request\Header` | `src/Htmx/src/Request/Header.php` | Backed enum of HTMX request headers |
| `Response\Header` | `src/Htmx/src/Response/Header.php` | Backed enum of HTMX response headers |
| `LaminasRenderer` | `src/Htmx/src/View/LaminasRenderer.php` | Custom renderer wiring 3-layer stack |
| `EnumTrait` | `src/Htmx/src/EnumTrait.php` | `tryFromName`, `fromName`, `names`, `values`, `toArray` for backed enums |
| `RequestHandlerTrait` | `src/Htmx/src/RequestHandlerTrait.php` | Shared handler helpers |
| `ResponseTrait` | `src/Htmx/src/Response/ResponseTrait.php` | Shared response helpers |
| `TriggerTrait` | `src/Htmx/src/TriggerTrait.php` | Builds `HX-Trigger` / `HX-Trigger-After-*` headers |
| `Attribute` | `src/Htmx/src/Attribute.php` | Constants for PSR-7 request attribute names |
| `Swap` | `src/Htmx/src/Swap.php` | Typed enum for `HX-Reswap` values |

**Critical rule:** Always detect HTMX via `$request->getAttribute(Header::Request->value)`, never `hasHeader()`. `ServerRequestFilter` maps headers to attributes.

---

### `src/User` — User, Auth, and Registration

**Namespace:** `User`  
**Purpose:** Full user lifecycle: registration, email verification, login/logout, user management.

#### Entities

| Entity | Key Properties | Notes |
|---|---|---|
| `Entity\User` | `id`, `displayName`, `email`, `active`, `verificationToken`, `tokenCreatedAt` | `final readonly`; implements `UserInterface` + `NamedCommandInterface` (via `NamedCommandTrait`). Uses `displayName` not `name` to avoid trait clash. |
| `Entity\Role` | `id`, `roleId` | `final readonly`; `roleId` is the Laminas ACL identifier. Title Case with spaces is fine. |
| `Entity\Store` | `storeNumber`, `city`, `pqaEmail` | `final readonly`. Each store has a configurable `pqa_email` for damage image dispatch. |

#### Commands & Handlers

| Command | Handler | Action |
|---|---|---|
| `SaveUserCommand` | `SaveUserHandler` | Inserts new user; generates UUID7 verification token; sets `active=0` |

#### Middleware

| Middleware | Responsibility |
|---|---|
| `RegistrationMiddleware` | Transforms raw POST body → `SaveUserCommand`; validates input |

#### Request Handlers

| Handler | Route | Notes |
|---|---|---|
| `RegistrationHandler` | `POST /register` | Dispatches `SaveUserCommand` via command bus |
| `LoginHandler` | `GET/POST /login` | Reads `SystemMessenger` flash messages; passes to template |
| `LogoutHandler` | `GET /logout` | Clears session |
| `VerifyEmailHandler` | `GET /verify-email/{token}` | Validates token; sets `active=1`; `302 → /login` + flash |
| `ResendVerificationHandler` | `POST /resend-verification` | Re-sends verification email |
| `UserListHandler` | `GET /admin/users` | Lists users |
| Admin handlers | `POST /admin/users/*` | Create, update, toggle-active |

#### Event Flow (Registration)

```
POST /register
    → RegistrationMiddleware (build SaveUserCommand)
    → RegistrationHandler (dispatch to CommandBus)
        → CommandHandlerMiddleware → SaveUserHandler
            → INSERT user (active=0, UUID7 token)
            → return CommandResult(token)
        → PostHandleMiddleware → dispatch PostHandleEvent
            → SendVerificationEmailListener → Mailpit/SMTP
    → 302 redirect
```

#### Repository

`UserRepository` composes `TableGateway('user', $adapter)`. Uses `getSql()` + `prepareStatementForSqlObject()` for all DML. Manual `hydrate()` method (readonly-clone support not yet merged upstream).

---

## 4. Database Architecture

**Engine:** MySQL 8.4 via `pdo_mysql`  
**Abstraction:** `webware/phpdb` (`PhpDb\Adapter`, `PhpDb\Sql\*`, `TableGateway`)  
**Schema:** `data/schema/` — 15 DDL files + `999_seed.sql`

### Schema Overview

| # | Table | Purpose |
|---|---|---|
| 001 | `store` | Store locations (PK: `store_number SMALLINT UNSIGNED`) |
| 002 | `role` | ACL roles (`role_id VARCHAR(50)`) |
| 003 | `user` | Users (`display_name`, `active`, `verification_token`) |
| 004 | `major_code` | DC major category codes |
| 005 | `sku_catalogue` | Product-type catalogue keyed by SKU |
| 006 | `manifest` | DC incoming shipment manifests |
| 007 | `manifest_item` | Line items on each manifest |
| 008 | `product` | Physical product units (keyed by AO#) |
| 009 | `product_status` | Multi-value status flags per product |
| 010 | `product_image` | Damage photo records |
| 011 | `ticket` | Delivery/pickup work orders |
| 012 | `ticket_item` | Products on each ticket |
| 013 | `transfer` | Inter-store transfer records |
| 014 | `transfer_item` | Products in each transfer |
| 015 | `log` | Monolog DB handler (`channel`, `level`, `uuid`, `message`, `context JSON`) |

### FK-safe DROP order (reverse)
`transfer_item` → `transfer` → `ticket_item` → `ticket` → `product_image` → `product_status` → `product` → `manifest_item` → `manifest` → `sku_catalogue` → `major_code` → `user` → `role` → `store`

### Query Conventions

All DML must use `PhpDb\Sql\*` via `TableGateway::getSql()`. Never raw SQL strings. See `.github/instructions/phpdb-sql-queries.instructions.md`.

```php
$sql    = $this->tableGateway->getSql();
$select = $sql->select()->where(['id' => $id]);
$stmt   = $sql->prepareStatementForSqlObject($select);
$result = $stmt->execute();
```

---

## 5. Cross-Cutting Concerns

### Authentication & Authorisation

| Concern | Package | Notes |
|---|---|---|
| Authentication | `LoginMiddleware` (custom) | POST-only credential check; writes session; no `mezzio-authentication-session` |
| Identity restore | `IdentityMiddleware` (global pipeline) | Reads session; attaches `User` or `GuestUser` to request |
| Authorisation | `webware/webware-acl` + Laminas ACL | Fail-closed; routes must be registered as ACL resources in `ConfigProvider::getAclConfig()` |

### Logging

- **`axleus/axleus-log`** — Monolog integration
- `MonologMiddleware` in `config/pipeline.php` (after `ServerUrlMiddleware`)
- `PhpDbHandler` — writes to `log` DB table; fields: `channel`, `level`, `uuid`, `message`, `time`, `user_identifier`, `context JSON`
- Processors: `RamseyUuidProcessor`, `PsrLogMessageProcessor`
- Tracy `SqlProfilerPanel` via `Webware\Traccio\PhpDb\ProfilingDelegator` (dev-only in `development.local.php`)

### Error Handling & Debug

- **Tracy** — dev-mode error handler (`Debugger::enable()`); disable with `php bin/development-mode disable` to use Xdebug breakpoints uninterrupted
- **Xdebug 3** — port 9003; `start_with_request=yes`; `client_host=127.0.0.1`

### Validation

- Input validation in Module Middleware before handler dispatch
- Planned: `mezzio/mezzio-valinor` for PSR-7 → Entity upcasting

### Configuration Management

- `config/autoload/` — global + local PHP config files
- `*.local.php` files are gitignored (secrets/dev overrides)
- Config cache: `data/cache/config-cache.php` — clear after pipeline changes: `rm -f data/cache/config-cache.php`

### Command Bus Pipeline

```
Priority 100 : PreHandleMiddleware    (pre-hooks)
Priority   1 : CommandHandlerMiddleware (resolves + executes handler)
Priority -100: PostHandleMiddleware   (dispatches PostHandleEvent)
Terminal     : EmptyPipelineHandler   (returns CommandResult passthrough)
```

**Known upstream fix needed:** `EmptyPipelineHandler` must check `$command instanceof CommandResultInterface` and return it directly. Local vendor fix applied — see `docs/planning/upstream-fixes-needed.md`.

### Template Rendering (3-Layer Stack)

Managed by `Htmx\View\LaminasRenderer`. Three layers:

| Layer | File | When rendered |
|---|---|---|
| Layout | `layout.phtml` | All full-page responses |
| Body | `body.phtml` | All full-page responses |
| Page | `{module}/{page}.phtml` | Always |

HTMX partial requests: `DisableBodyMiddleware` suppresses layout + body layers; only the page partial is rendered.

**Rules:**
- No inline styles — use `.ims-*` CSS classes in `public/assets/css/custom.css`
- No hardcoded URLs — use `$this->url('route.name')` and `$this->basePath()`

---

## 6. Frontend Architecture

| Technology | Version | Purpose |
|---|---|---|
| HTMX | latest | Partial page updates, form submissions, SSE (planned) |
| Bootstrap | 5.3.3 | CSS framework |
| Bootstrap Icons | 1.13.1 | Icon set |
| ZXing-js (`@zxing/library`) | planned | Camera-based Code 128B barcode scanning |
| Chart.js | 4.4.3 | Analytics charts (damage trend, status doughnut, etc.) |

### Key UI Patterns

- **Sidebar:** `offcanvas-lg offcanvas-start` — drawer on mobile, fixed panel on desktop (260px, fixed from 56px top)
- **Modals:** `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` — zero custom JS
- **Notifications/Filter chips:** `form-switch` / `btn-check` radio groups
- **Flash messages:** `SystemMessenger` (hops-based); rendered as dismissible Bootstrap `.alert-{level}` blocks

### Barcode Scanning

- Code 128B format; AO# encoded
- ZXing-js for camera; hardware wedge scanners emit keystrokes natively (no library change needed)
- Scan input stays focused after each confirmation

---

## 7. Infrastructure

### Dev Container

| Component | Detail |
|---|---|
| Container image | `php:latest` (PHP 8.5.5 NTS) |
| PHP extensions | `pdo_mysql`, `intl`, `zip`, `xdebug`, `pdo_sqlite`, `opcache` |
| Container command | `sleep infinity` (VS Code attaches) |
| MySQL | 8.4 (`docker/database/mysql/`) |
| phpMyAdmin | Port 8082 |
| Mailpit | Port 8025 (SMTP 1025) — email testing |
| App server | `php -S 0.0.0.0:8080 -t public/` |

### Docker Compose Split

Two files merged at container startup:
- `docker-compose.yml` (root) — `mysql` + `phpmyadmin` services
- `.devcontainer/docker-compose.yml` — `php` service

**Do not consolidate.** The split is required for the devcontainer feature to work correctly.

### Starting the Dev Server

```bash
php -S 0.0.0.0:8080 -t public/
```

Must be manually restarted after code changes (no hot-reload in synchronous mode).

---

## 8. Extension Points

### Adding a New Module

1. Create `src/{Module}/src/ConfigProvider.php` — registers routes, dependencies
2. Create `src/{Module}/src/RouteProvider.php`
3. Register `ConfigProvider` in `config/config.php`
4. Follow pattern: `Middleware` → `RequestHandler` → `Command` → `CommandHandler` → `Repository`

### Adding a Route

Routes are registered via `RouteProvider::__invoke(Application $app)` in each module. Pattern: `$app->route('/path', [MiddlewareA::class, HandlerB::class], ['GET', 'POST'], 'route.name')`.

### Adding a DB Table

1. Create `data/schema/NNN_tablename.sql` with `DROP TABLE IF EXISTS` guard
2. Update FK-safe DROP order in `999_seed.sql` comments
3. Create Entity (`final readonly`), Repository interface + implementation, Factory

---

## 9. Future Async Reintegration

When TrueAsync stabilises, the path back to async is:

1. Switch devcontainer image to `trueasync/php-true-async:latest`
2. Add `true_async` extension to `Dockerfile`
3. Re-enable `src/mezzio-async/` in `config/config.php`
4. Switch entry point from `php -S` to `php bin/mezzio-async start`
5. Apply planned refactors in `docs/async/`:
   - HTTP keep-alive loop (`docs/async/keep-alive/`)
   - `TaskGroup` refactor (`docs/async/taskgroup-and-pdo/`)
   - MySQL PDO Pool support (`docs/planning/database/rdbms-selection.md`)
6. Verify HTMX middleware compatibility (no blocking issues identified — see `docs/planning/architectural-overview.md`)

**Known TrueAsync bugs to watch:** `FileSystemWatcher` recursive mode broken (workaround: `inotifywait`). See `docs/async/trueasync-bugs.md`.

---

## 10. Key File Map

```
config/
  config.php                   ← ConfigProvider aggregator
  pipeline.php                 ← Mezzio middleware pipeline
  container.php                ← DI container bootstrap
  autoload/
    commandbus-event.global.php ← PostHandleEvent → SendVerificationEmailListener
    dependencies.global.php
    global.php
    mezzio.global.php
    project.global.php
    tracy.global.php
    user.global.php             ← base_url, from_email, verification_token_ttl

src/
  App/     ← Dashboard, ping, top-level routing
  Htmx/    ← HTMX middleware, view helpers, enums
  User/    ← Auth, registration, user CRUD

public/
  index.php            ← Entry point; PHP_SAPI === 'cli-server' guard
  assets/css/custom.css
  assets/js/

data/schema/           ← 15 DDL files + seed
docs/
  Project_Architecture_Blueprint.md  ← this file
  planning/                          ← active application planning
    architectural-overview.md
    must-have.md
    phase-2.md
    sample-manifest.md
    upstream-fixes-needed.md
    database/
      rdbms-selection.md
  async/                             ← future async reintegration
    php-async-api.md
    trueasync-bugs.md
    keep-alive/
    taskgroup-and-pdo/
```
