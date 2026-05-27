---
title: Sprint — May 8–11, 2026
date_created: 2026-05-07
status: Block 3 complete — Block 4 not started
total_hours_estimated: 62–65h
---

# Sprint Plan — May 8–11, 2026

## Schedule

| Block | Date | Window | Est. Hours |
|---|---|---|---|
| **Block 1** | Thursday May 8 | 19:00 – 00:00 | 5h |
| **Block 2** | Friday May 9 | Full day | 18–20h |
| **Block 3** | Sunday May 10 | Full day | 18–20h |
| **Block 4** | Monday May 11 | Full day | 18–20h |
| **Total** | | | **59–65h** |

> Items are sequenced by dependency order, not importance. Items within a block
> can be attacked in parallel but are listed priority-first. Items marked 🔁 will
> carry forward if not reached — they are included for completeness.

---

## Block 1 — Thursday May 8 · 19:00–00:00 (5h)

**Goal:** Complete the ACL listener wiring plan end-to-end.
Full plan: `docs/planning/feature-admin-acl-listener-wiring-1.md`

| # | Task | Est. | Files |
|---|---|---|---|
| 1.1 | **Augment `AclBuiltEvent`** — add `private array $routeMappings` constructor param; add `addRouteMapping(string $route, string $resource, string $privilege): void`; add `getRouteMappings(): array`. Update docblock. | 30m | `src/webware-acl/src/Event/AclBuiltEvent.php` |
| 1.2 | **Update `AclBuilder::buildFromArrays()`** — pass `$data['routeMappings']` into `AclBuiltEvent` constructor; after dispatch read back `$event->getRouteMappings()` into `$this->routeMappings` (replaces the pre-dispatch assignment). | 30m | `src/webware-acl/src/AclBuilder.php` |
| 1.3 | **`RegisterAdminResourcesListener`** — invokable on `ResourcesLoadedEvent`; calls `$event->acl->addResource('admin.dashboard')`. No constructor deps. | 20m | `src/webware-admin/src/Listener/RegisterAdminResourcesListener.php` |
| 1.4 | **`RegisterAdminRulesListener`** — invokable on `RulesLoadedEvent`; `$event->acl->allow('Administrator', 'admin.dashboard', 'read')` + same for `Developer`. | 20m | `src/webware-admin/src/Listener/RegisterAdminRulesListener.php` |
| 1.5 | **`RegisterAdminRouteMappingsListener`** — invokable on `AclBuiltEvent`; `$event->addRouteMapping('admin.dashboard', 'admin.dashboard', 'read')`. | 20m | `src/webware-admin/src/Listener/RegisterAdminRouteMappingsListener.php` |
| 1.6 | **Three listener factories** in `src/webware-admin/src/Container/` — each trivially returns `new Listener()`. | 20m | `RegisterAdminResourcesListenerFactory.php`, `RegisterAdminRulesListenerFactory.php`, `RegisterAdminRouteMappingsListenerFactory.php` |
| 1.7 | **Update `webware-admin` `ConfigProvider`** — add three factories to `getDependencies()`; add `listeners` key to `__invoke()` return mapping each event to its listener via `['listener' => FQCN, 'priority' => 1]`. | 20m | `src/webware-admin/src/ConfigProvider.php` |
| 1.8 | ~~**Remove `admin.dashboard` rows from `999_seed.sql`**~~ — **No-op. Dropped.** `admin.dashboard` was never in the seed; `dashboard` (the main app home route) is a separate resource and must not be touched. The listener adds `admin.dashboard` fresh. | — | — |
| 1.9 | **`AclDashboardWidget`** in `webware-acl` — implement `WidgetInterface`; properties: title `'ACL Management'`, resourceId `'admin.dashboard'`, privilege `'read'`, template `'acl::admin-widget'`, order `10`. | 30m | `src/webware-acl/src/Widget/AclDashboardWidget.php` |
| 1.10 | **`RegisterAclWidgetListener`** in `webware-acl` — invokable on `RegisterWidgetEvent`; calls `$event->addWidget(new AclDashboardWidget())`. Factory: returns `new RegisterAclWidgetListener()`. | 20m | `src/webware-acl/src/Listener/RegisterAclWidgetListener.php`, factory |
| 1.11 | **Wire `RegisterAclWidgetListener`** in `webware-acl` `ConfigProvider` under `listeners[RegisterWidgetEvent::class]`. | 15m | `src/webware-acl/src/ConfigProvider.php` |
| 1.12 | **Smoke test** — start the dev server, log in as Administrator, hit `/admin`, verify no exceptions. Clear config cache + acl cache before testing. | 30m | — |
| 1.13 | **Unit tests — listener classes** (TASK-013–016 from plan) — 4 test classes covering `RegisterAdminResourcesListener`, `RegisterAdminRulesListener`, `RegisterAdminRouteMappingsListener`, augmented `AclBuiltEvent`. | 60m | `test/` |

---

## Block 2 — Friday May 9 · Full Day (18–20h)

**Goal:** Password validator, ACL management UI (route map + roles/resources/rules), CommandBus auth.

### Morning session (≈6h)

| # | Task | Est. | Files |
|---|---|---|---|
| 2.1 | **Migrate password requirements validator** — adapt from user's other project; wire as a `ValidatorInterface` implementation; add to registration and change-password flows. | 90m | `src/User/src/Validator/PasswordRequirementsValidator.php` + factory + form wiring |
| 2.2 | **ACL admin resource: `admin.acl`** — add to listener-based registration (`RegisterAdminResourcesListener` extended or new listener in `webware-acl`); privileges: `read`, `create`, `update`, `delete`. Allow `Administrator` + `Developer` all four privileges. Add route mappings for all `admin.acl.*` routes. | 45m | Extend listener classes |
| 2.3 | **`RouteMapManagerHandler`** — `GET /admin/access/routes` (`admin.acl.routes`) — reads all `acl_route_privilege` rows via `AclRepository`; renders `acl::admin-route-map` template. Load `htmx-mezzio` SKILL before starting templates. | 90m | `src/webware-acl/src/RequestHandler/RouteMapManagerHandler.php` + factory + template |
| 2.4 | **`RouteMapManagerHandler` — POST** — `POST /admin/access/routes` — add/update/delete a route mapping; increments `acl_version`; invalidates ACL cache; HTMX swap back updated table rows. | 90m | Same handler + `AclRepository` write methods |
| 2.5 | **ACL overview widget template** — `acl::admin-widget` partial — stat counts (roles, resources, rules, route maps); links to `admin.acl.routes`, role list, resource list. | 45m | `src/webware-acl/templates/acl/admin-widget.phtml` |

### Afternoon session (≈6h)

| # | Task | Est. | Files |
|---|---|---|---|
| 2.6 | **Role list handler** — `GET /admin/access/roles` (`admin.acl.roles`) — list all roles with parent info and assigned user counts; HTMX-paginated. | 90m | Handler + factory + template |
| 2.7 | **Role create/edit modal** — Bootstrap modal with role name + parent selector; `POST /admin/access/roles`; invalidates ACL cache. | 90m | Handler + template partial |
| 2.8 | **Resource list handler** — `GET /admin/access/resources` (`admin.acl.resources`) — list all resources with their privileges as nested rows. | 90m | Handler + factory + template |
| 2.9 | **Resource create + privilege add** — `POST /admin/access/resources`; `POST /admin/access/resources/{id}/privileges`; both invalidate ACL cache. | 90m | Handlers + templates |

### Evening session (≈6h)

| # | Task | Est. | Files |
|---|---|---|---|
| 2.10 | **Rule management UI** — `GET /admin/access/rules` — matrix or list view: role × resource × privilege × allow/deny toggle. | 2h | Handler + factory + template |
| 2.11 | **Rule toggle endpoint** — `POST /admin/access/rules` — upsert allow/deny; increment `acl_version`; invalidate cache; HTMX swap the toggled cell. | 90m | Handler |
| 2.12 | **`AclRepository` write methods** — `upsertRule()`, `deleteRule()`, `insertRole()`, `insertResource()`, `insertPrivilege()`, `incrementVersion()`, `upsertRouteMapping()`. | 90m | `src/webware-acl/src/Repository/AclRepository.php` + interface |
| 2.13 | **Wire all new admin.acl routes** in `webware-acl` `RouteProvider` with `AuthorizationMiddleware` guard. | 30m | `src/webware-acl/src/RouteProvider.php` |

---

## Block 3 — Saturday May 10 · Full Day (18–20h)

**Goal:** CommandBus auth integration, ZXing-js, analytics backend, begin Manifest module.

### Morning session (≈6h)

| # | Task | Est. | Files |
|---|---|---|---|
| 3.1 | **CommandBus privilege integration (Phase 8)** — design an `AuthorizationMiddleware` for the CommandBus (not HTTP middleware — a CommandBus middleware that calls `AclInterface::isAllowed()` before dispatching). Requires: role resolution from the current request context or `Async\Context` equivalent; `CommandInterface` must carry or map to a resource+privilege pair. | 2h | `src/webware-acl/src/CommandBus/AclCommandMiddleware.php` + factory |
| 3.2 | **Command → ACL resource/privilege mapping config** — define `config['command-acl']` array mapping `CommandClass::class => ['resource' => '…', 'privilege' => '…']`. Wire `AclCommandMiddleware` to read it. | 1h | `config/autoload/` + `AclCommandMiddleware` |
| 3.3 | **`LoginCommand` stub** — either implement as a real command (audit log dispatch, rate-limit check) or formally delete the file. Decision point. | 45m | `src/User/src/Command/LoginCommand.php` |
| 3.4 | **ZXing-js integration** — replace the stub in `process-manifest.js` with real `@zxing/library` camera scanning. Wire `BrowserMultiFormatReader`; handle `NotFoundException`; stop stream on modal close; test with both wedge and camera paths. | 2h | `public/assets/js/process-manifest.js` |

### Afternoon session (≈6h)

| # | Task | Est. | Files |
|---|---|---|---|
| 3.5 | **Analytics JSON endpoint** — `GET /api/analytics` — returns Chart.js-ready data arrays: damage trend (30/90/180 day), status distribution, items-per-manifest, top damage categories. Accepts `?range=30d\|90d\|6mo` query param. | 2h | `src/App/src/RequestHandler/AnalyticsApiHandler.php` + factory + route |
| 3.6 | **Analytics — date range switching** — wire 30d/90d/6mo buttons with `hx-get="/api/analytics?range=…"` + `hx-target` swapping chart data; update `Chart.js` datasets on swap without re-creating charts. | 1h | `src/App/templates/app/analytics.phtml` + `public/assets/js/app.js` |
| 3.7 | ~~**Manifest module scaffold**~~ ✅ | 2h | Complete |
| 3.8 | ~~**Manifest list handler**~~ ✅ | 90m | Complete |

### Evening session (≈6h)

| # | Task | Est. | Files |
|---|---|---|---|
| 3.9 | ~~**Manifest detail handler**~~ ✅ — `GET /manifest/{id}` (`manifest.detail`) — summary card, damaged items list, clean items list. | 90m | Complete |
| 3.10 | **Process manifest handler** — `GET /manifest/{id}/process` (`manifest.process`) — scan zone, manual entry form, processed items list. Uses HTMX for real-time updates. | 2h | Handler + factory + template |
| 3.11 | **Scan / AO# lookup endpoint** — `POST /manifest/{id}/scan` (`manifest.scan`) — receives AO# from hardware wedge or ZXing; validates against manifest items; returns HTMX partial (success card or error toast). | 2h | Handler + factory + template partial |
| 3.12 | **Finish manifest endpoint** — `POST /manifest/{id}/finish` — marks manifest complete; validates all items accounted for or explicitly skipped; calls `unlink($manifest->csvPath)` + nulls `csv_path` in DB; redirects to detail. | 45m | Handler |

---

## Block 4 — Sunday May 11 · Full Day (18–20h)

**Goal:** Inventory module, damage flow, PQA email, settings, `webware/composer-plugin` scaffold, PR prep.

### Morning session (≈6h)

| # | Task | Est. | Files |
|---|---|---|---|
| 4.1 | **Inventory module scaffold** — `src/Inventory/` or within `src/App/`: entity classes (`Product`, `ProductStatus`, `ProductImage`), repository interface + implementation. DB schema: `008_product.sql`, `009_product_status.sql`, `010_product_image.sql`. | 2h | `src/Inventory/src/` (or `src/App/src/`) |
| 4.2 | **Product list handler** — `GET /inventory` (`inventory.list`) — paginated, `btn-check` filter chips (All / Damaged / Clean / PQA Pending), search input with HTMX debounce. | 2h | Handler + factory + template |
| 4.3 | **Damage detail handler** — `GET /inventory/{id}` (`inventory.detail`) — product identity card, status toggle buttons, damage notes form, photo grid, PQA card. | 2h | Handler + factory + template |

### Mid-morning/afternoon session (≈6h)

| # | Task | Est. | Files |
|---|---|---|---|
| 4.4 | **Damage notes + status update endpoint** — `POST /inventory/{id}/status` — updates `product_status`; increments status history; HTMX swap status badge + history list. | 1h | Handler |
| 4.5 | **Photo upload endpoint** — `POST /inventory/{id}/photos` — accepts multipart; stores file; inserts `product_image` row; returns HTMX partial with new photo grid. Validate MIME type and file size at the boundary. | 2h | Handler + factory |
| 4.6 | **PQA email — Send Images modal** — wire the Bootstrap modal submit to `POST /inventory/{id}/pqa-email`; reads store `pqa_email`; sends via `axleus-mailer`; toast confirmation via HTMX. | 2h | Handler + `axleus-mailer` integration |
| 4.7 | **Settings handler** — `GET /settings` (`user.settings`) — profile form, notification switches, store config, Change Password modal. `POST /settings/profile`, `POST /settings/password`. | 2h | Handler + factory + template |

### Evening session (≈6h) 🔁

| # | Task | Est. | Files |
|---|---|---|---|
| 4.8 | **🔁 `webware/composer-plugin` — Milestone 1 scaffold** — new package `src/webware-composer-plugin/`; `composer.json` with `"type": "composer-plugin"`; `PluginInterface` + `EventSubscriberInterface`; subscribes to `post-package-install`, `post-package-update`, `post-package-uninstall`; Milestone 1 action: delete `data/cache/config-cache.php` and `data/cache/acl.cache` if present. | 2h | `src/webware-composer-plugin/` |
| 4.9 | **🔁 `webware/composer-plugin` — `extra.webware` reader** — read `extra.webware.config-provider` from installed package and splice into `config/config.php` (mirrors `laminas-component-installer` pattern). | 2h | Plugin class |
| 4.10 | **🔁 Ticket module scaffold** — entities (`Ticket`, `TicketItem`), repository, list + detail handlers, route wiring. DB: `011_ticket.sql`, `012_ticket_item.sql`. | 2h | `src/Ticket/src/` (or within `src/App/`) |
| 4.11 | **🔁 Transfer module scaffold** — entities, repository, list handler. DB: `013_transfer.sql`, `014_transfer_item.sql`. | 2h | `src/Transfer/src/` |

---

## Session Notes — May 11, 2026

### Resolved Issues
- **Performance**: `/admin` double `acl_version` SELECT fixed — `AclRepository::fetchVersion()` memoized; cleared by `incrementVersion()`
- **ACL UI**: Add Rule modal privilege select was showing all privileges for all resources — fixed with `data-resource-pk` filtering via JS
- **Manifest upload — `userId = 0` FK violation**: Session stores `DefaultUser`; `$user->id` is always `null`. Fixed by adding `'id'` to details array in `UserRepository::hydrate()`; read via `$user->getDetail('id')`
- **`fgetcsv()` deprecation**: Explicit 5-arg call: `fgetcsv($handle, 0, ',', '"', '')`
- **`execute()->current()` returns `false`**: PhpDb does not normalize empty result — guarded in `resolveMajorCodeId()` and `upsertSkuCatalogue()`
- **Raw exception in toast**: Changed `catch (RuntimeException)` to `catch (Throwable)`; logger pulled from `$request->getAttribute(LoggerInterface::class)`; generic user message shown
- **CSV file orphaned on DB failure**: `cleanupFile()` helper called on empty-CSV early return and in catch block; `$finalPath ?? $tmpPath` covers all failure points
- **Upload form defaulting to today's date**: `received_date` input now blank by default — parser uses CSV consignment date unless user explicitly overrides

### Key Architecture Decisions Deferred
- **Centralized error handling**: Future `App\Exception\ExceptionInterface` with `getSystemMessage()` + pipeline error middleware. Components throw typed exceptions; one middleware logs + toasts + rethrows to `ErrorHandler`/`axleus-log`. Not implemented yet — current placeholder (`Throwable` catch + request logger) is acceptable.
- **No-JS fallback for upload form**: `GET /manifest/upload` retained pending IT dept policy on JavaScript requirement.

### Route Corrections (final)
| Method | Path | Note |
|---|---|---|
| `GET` | `/manifests` | List — plural |
| `GET` | `/manifest/upload` | Upload form — singular (no-JS fallback) |
| `POST` | `/manifest/upload` | Process upload — singular (creates one manifest) |
| `GET` | `/manifest/{id}` | Detail — singular |

### `displayId()` format
`{storeId}-{mmdd}` e.g. `207-0427`. Year visible in date field in context; not in ID itself.
DC date on manifest = dispatch date at DC (typically 1 day before store receipt). Override field allows correction.

---

## Carry-Forward Items (not expected this sprint)

| Item | Notes |
|---|---|
| `webware/composer-plugin` Milestone 2 — ACL DB writes | Depends on plugin scaffold (4.8–4.9) |
| `webware/composer-plugin` Milestone 3 — Schema migration runner | Depends on Milestone 2 |
| Analytics — deeper drill-down pages | Per-manifest breakdown, per-SKU damage history |
| `LoginCommand` — rate limiting / lockout | If not resolved in 3.3 |
| `RegistrationHandler` — fix `hasHeader` → `getAttribute` | See session-context note on `HtmxRequestHeader` |
| Full PHPUnit test coverage pass | ACL, User, Manifest, Inventory modules |
| PHPStan level 9 pass | Run `./vendor/bin/phpstan analyse` across all `src/` |
| php-cs-fixer pass | `./vendor/bin/php-cs-fixer fix` |
| PR #12 review + merge | After ACL listener wiring + admin UI complete |
| `mezzio-async` reintegration planning | TrueAsync extension stability check |

---

## Prerequisites Before Block 1

- [ ] Dev server running: `php -S 0.0.0.0:8080 -t public/`
- [ ] DB seeded clean: re-run all `data/schema/*.sql` files in order
- [ ] Config cache cleared: `rm -f data/cache/config-cache.php data/cache/acl.cache`
- [ ] Read `docs/planning/feature-admin-acl-listener-wiring-1.md` §9 (caching) before touching `AclBuilder`
- [ ] Load `htmx-mezzio` SKILL before any template work (Block 2 onwards)
- [ ] Load `phpdb` SKILL before any repository write methods (Block 2.12)
- [ ] Load `webware-coding-standard` SKILL when writing new PHP files

---

## Key File References

| File | Relevance |
|---|---|
| `src/webware-acl/src/Event/AclBuiltEvent.php` | First task — augment in 1.1 |
| `src/webware-acl/src/AclBuilder.php` | Second task — update in 1.2 |
| `src/webware-admin/src/ConfigProvider.php` | Wire listeners in 1.7 |
| `data/schema/999_seed.sql` | Remove admin.dashboard rows in 1.8 |
| `docs/planning/feature-admin-acl-listener-wiring-1.md` | Full ACL wiring plan |
| `.github/session-context.md` | Living context — update at end of each block |
| `.github/skills/htmx-mezzio/SKILL.md` | Load before any template work |
| `.github/skills/phpdb/SKILL.md` | Load before any repository write work |
