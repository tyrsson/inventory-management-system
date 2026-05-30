# ACL: Config → Database Migration Plan

## Decision Context

The config-file-driven ACL was adopted to simplify the initial implementation. It has
reached its ceiling:

- `AclFactory`, `BuildAccessControlMiddleware`, and all handlers share a `private array $config`
  frozen at DI container build time. Every mutation requires in-request workarounds.
- Atomic writes are impossible — file read-modify-write is collision-prone, incompatible
  with the planned async (TrueAsync) runtime.
- Assertion management requires stale render-time data baked into HTMX `hx-vals`, creating
  race conditions when type and assertion changes interleave.

**Decision:** Move `roles` and `rules` to DB. The DB becomes the single mutable source of
truth. All other ACL plumbing (routes, middleware pipeline, templates, commands, HTMX
wiring) remains unchanged.

---

## Scope Boundary

**In scope — only these files change:**

| Category | File |
|----------|------|
| Schema | 2 new migration files |
| Seed | `999_seed.sql` additions |
| New | `Middleware/AclMiddleware.php` + `Middleware/Container/AclMiddlewareFactory.php` |
| New | `Repository/RoleRepository.php` + `Repository/Container/RoleRepositoryFactory.php` |
| New | `Repository/RuleRepository.php` + `Repository/Container/RuleRepositoryFactory.php` |
| Modified factories | `AclFactory`, `BuildAccessControlMiddlewareFactory`, `SaveRuleHandlerFactory`, `UpdateRuleTypeHandlerFactory`, `SaveRoleHandlerFactory`, `DeleteRoleHandlerFactory`, `AuthorizationMiddlewareFactory` |
| Handlers | `SaveRuleHandler`, `UpdateRuleTypeHandler`, `SaveRoleHandler`, `DeleteRoleHandler` |
| Middleware | `AclFactory` (simplified), `BuildAccessControlMiddleware`, `AuthorizationMiddleware` (read attribute), `ProcessRuleMiddleware` (minor) |
| Pipeline | `config/pipeline.php` — add `AclMiddleware` |
| Config file | `acl.global.php` — strip mutable sections only |
| Constants | `Container/Configuration.php` — remove `LOCAL_CONFIG_FILE` |

**Explicitly out of scope — do not touch:**

- All `Command` classes — they are pure data carriers, unchanged
- All templates and partials
- `RouteProvider`, `ProcessRoleMiddleware`, `ProcessRuleMiddleware` (except one line)
- `IdentityMiddleware`
- `AclOverviewHandler`, `RoleListHandler`, `ResourceListHandler`
- `AssertionManager`, `OwnershipAssertion`
- `AclInterface`, `Acl`, `Entity/Role`, `Role/*`, `Http/*`
- The HTMX `htmx.process()` and offcanvas wiring in `app.js`

---

## Schema

### `data/schema/016_acl_role.sql`

```sql
-- =============================================================================
-- acl_role
-- Stores ACL roles and their parent role relationships.
-- parent_id is a JSON array of parent role_id strings (Laminas ACL supports
-- multiple inheritance). role_id is a plain VARCHAR — no FK to user.role_id
-- since roles are managed independently of users.
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `acl_role`;
CREATE TABLE IF NOT EXISTS `acl_role` (
    id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    role_id   VARCHAR(50)   NOT NULL,
    parent_id JSON          NULL COMMENT 'Array of parent role_id strings, e.g. ["Guest","Member"]',
    PRIMARY KEY (id),
    UNIQUE KEY uq_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
```

### `data/schema/017_acl_rule.sql`

```sql
-- =============================================================================
-- acl_rule
-- Stores allow/deny rules. One row per (role_id, resource_id) pair — enforced
-- by unique key, so a role can only have one rule type per resource.
-- assertions is a JSON array of AssertionManager alias strings,
-- e.g. ["Store Owned Resource"].
-- resource_id is the Mezzio route name string.
-- role_id is a plain VARCHAR matching acl_role.role_id (no FK — same pattern
-- as user.role_id).
-- Protected routes are derived at runtime by comparing RouteCollector routes
-- against the set of resource_ids that appear in this table.
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `acl_rule`;
CREATE TABLE IF NOT EXISTS `acl_rule` (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    type        ENUM('allow','deny') NOT NULL,
    role_id     VARCHAR(50)    NOT NULL,
    resource_id VARCHAR(255)   NOT NULL,
    assertions  JSON           NOT NULL DEFAULT (JSON_ARRAY()),
    PRIMARY KEY (id),
    UNIQUE KEY uq_rule (role_id, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
```

### `data/schema/999_seed.sql` additions

Seed data derived from current `acl.global.php`. Add to existing seed file:

```sql
-- acl_role seed (from acl.global.php 'roles' section)
INSERT INTO `acl_role` (role_id, parent_id) VALUES
('Guest',               JSON_ARRAY()),
('Member',              JSON_ARRAY('Guest')),
('Administrator',       JSON_ARRAY('Member', 'Manager')),
('Developer',           JSON_ARRAY('Administrator')),
('Warehouse',           JSON_ARRAY('Member')),
('Sales',               JSON_ARRAY('Member')),
('Collections',         JSON_ARRAY('Member')),
('Warehouse Supervisor',JSON_ARRAY('Warehouse')),
('Assistant Manager',   JSON_ARRAY('Sales', 'Warehouse', 'Collections')),
('Manager',             JSON_ARRAY('Assistant Manager', 'Warehouse Supervisor'));

-- acl_rule seed (from acl.global.php 'allow' and 'deny' sections)
INSERT INTO `acl_rule` (type, role_id, resource_id, assertions) VALUES
-- Guest allow
('allow','Guest','user.manager.session.read',         JSON_ARRAY()),
('allow','Guest','user.manager.session.create',        JSON_ARRAY()),
('allow','Guest','user.manager.register.read',         JSON_ARRAY()),
('allow','Guest','user.manager.register.create',       JSON_ARRAY()),
('allow','Guest','user.manager.verify.email.read',     JSON_ARRAY()),
('allow','Guest','user.manager.resend.verification.read',  JSON_ARRAY()),
('allow','Guest','user.manager.resend.verification.create',JSON_ARRAY()),
('allow','Guest','app.api.ping',                       JSON_ARRAY()),
-- Member allow
('allow','Member','user.manager.logout.read',          JSON_ARRAY()),
('allow','Member','app.dashboard',                     JSON_ARRAY()),
('allow','Member','ims.manifest.list',                 JSON_ARRAY()),
('allow','Member','ims.manifest.detail',               JSON_ARRAY()),
('allow','Member','app.api.ping',                      JSON_ARRAY()),
-- Member deny
('deny','Member','user.manager.session.read',          JSON_ARRAY()),
('deny','Member','user.manager.session.create',        JSON_ARRAY()),
('deny','Member','user.manager.register.read',         JSON_ARRAY()),
('deny','Member','user.manager.register.create',       JSON_ARRAY()),
('deny','Member','user.manager.verify.email.read',     JSON_ARRAY()),
('deny','Member','user.manager.resend.verification.read',  JSON_ARRAY()),
('deny','Member','user.manager.resend.verification.create',JSON_ARRAY()),
-- Administrator allow
('allow','Administrator','webware.admin.user.manager',         JSON_ARRAY()),
('allow','Administrator','webware.admin.user.manager.create',  JSON_ARRAY()),
('allow','Administrator','webware.admin.user.manager.update',  JSON_ARRAY()),
('allow','Administrator','webware.admin.user.manager.toggle.update', JSON_ARRAY()),
('allow','Administrator','webware.admin.dashboard.read',       JSON_ARRAY()),
-- Developer allow
('allow','Developer','webware.admin.acl.manager', JSON_ARRAY()),
-- Warehouse allow
('allow','Warehouse','ims.manifest.upload',       JSON_ARRAY('Store Owned Resource')),
('allow','Warehouse','ims.manifest.upload.store', JSON_ARRAY('Store Owned Resource')),
-- Warehouse Supervisor allow
('allow','Warehouse Supervisor','admin.manifest', JSON_ARRAY());
```

---

## PHP Changes

### 1. `Container/AclFactory.php`

**What changes:** Simplified to bare instantiation only — no DB queries, no config reads.

- Returns a new empty `Acl` instance wrapping a bare `LaminasAcl`
- The Developer blanket-allow and all role/rule population moves to `AclMiddleware`
- Still registered in the DI container as `AclInterface::class` so the container entry
  exists, but downstream consumers that need a populated ACL read from the request
  attribute set by `AclMiddleware` instead

### 2. `Middleware/AclMiddleware.php` *(new file)*

**What it does:** Builds the fully populated Laminas ACL per-request from the DB and sets
it as a `AclInterface::class` request attribute.

- Constructor: `RoleRepository $roleRepository`, `RuleRepository $ruleRepository`,
  `AssertionManager $assertionManager`
- `process()`:
  1. `$roleRepository->fetchAll()` — build roles with topological sort
  2. `$ruleRepository->fetchDistinctResourceIds()` — register resources
  3. `$ruleRepository->fetchAll()` — apply rules; resolve assertion alias strings via
     `$this->assertionManager->get($alias)`
  4. Apply Developer blanket-allow (same logic currently in `AclFactory`)
  5. `$request->withAttribute(AclInterface::class, $populatedAcl)`
  6. Call `$handler->handle($request)`
- **Bug fix included:** assertion resolution uses `AssertionManager::get($alias)` —
  fixes the existing broken `class_exists($fqcn)` path in the current `AclFactory`

### 3. `Middleware/Container/AclMiddlewareFactory.php` *(new file)*

- Injects `RoleRepository`, `RuleRepository`, `AssertionManager`

### 4. `config/pipeline.php`

- Add `AclMiddleware` to the pipeline before `AuthorizationMiddleware`

### 5. `Repository/RoleRepository.php` *(new file)*

Wraps a `TableGateway` on `acl_role`. Exposes only the methods required by the files
in scope — nothing else.

| Method | SQL | Used by |
|--------|-----|---------|
| `fetchAll(): array` | `SELECT role_id, parent_id FROM acl_role` | `AclMiddleware`, `BuildAccessControlMiddleware` |
| `fetchDirectChildren(string $roleId): array` | `SELECT role_id FROM acl_role WHERE JSON_CONTAINS(parent_id, JSON_QUOTE(?))` | `UpdateRuleTypeHandler` (cascade) |
| `save(string $roleId, array $parents): void` | `INSERT ... ON DUPLICATE KEY UPDATE parent_id = VALUES(parent_id)` | `SaveRoleHandler` |
| `delete(string $roleId): void` | `DELETE FROM acl_role WHERE role_id = ?` | `DeleteRoleHandler` |

`parent_id` JSON encoding/decoding is handled inside the repository — callers pass and
receive plain PHP arrays.

### 6. `Repository/Container/RoleRepositoryFactory.php` *(new file)*

- Constructs a `TableGateway` for `acl_role` using `AdapterInterface`, passes to
  `RoleRepository`

### 7. `Repository/RuleRepository.php` *(new file)*

Wraps a `TableGateway` on `acl_rule`. Exposes only the methods required by the files
in scope — nothing else.

| Method | SQL | Used by |
|--------|-----|---------|
| `fetchAll(): array` | `SELECT type, role_id, resource_id, assertions FROM acl_rule` | `AclMiddleware`, `BuildAccessControlMiddleware` |
| `fetchDistinctResourceIds(): array` | `SELECT DISTINCT resource_id FROM acl_rule` | `AclMiddleware` |
| `findByRoleAndResource(string $roleId, string $resourceId): ?array` | `SELECT ... WHERE role_id = ? AND resource_id = ?` | `UpdateRuleTypeHandler` (cascade check) |
| `save(string $type, string $roleId, string $resourceId, array $assertions): void` | `INSERT ... ON DUPLICATE KEY UPDATE type = VALUES(type), assertions = VALUES(assertions)` | `SaveRuleHandler`, `UpdateRuleTypeHandler` (cascade inserts) |
| `updateType(string $roleId, string $resourceId, string $newType): void` | `UPDATE acl_rule SET type = ? WHERE role_id = ? AND resource_id = ?` | `UpdateRuleTypeHandler` |

`assertions` JSON encoding/decoding is handled inside the repository — callers pass and
receive plain PHP arrays.

### 8. `Repository/Container/RuleRepositoryFactory.php` *(new file)*

- Constructs a `TableGateway` for `acl_rule` using `AdapterInterface`, passes to
  `RuleRepository`

### 9. `Middleware/AuthorizationMiddleware.php`

**What changes:** Reads the populated ACL from the request attribute instead of
constructor injection.

- Remove: `AclInterface $acl` constructor parameter
- In `process()`: `$acl = $request->getAttribute(AclInterface::class)` — fail fast with
  a 500 if the attribute is absent (means `AclMiddleware` was not in the pipeline)

### 10. `Middleware/Container/AuthorizationMiddlewareFactory.php`

- Remove: `$container->get(AclInterface::class)` injection

### 11. `Admin/Middleware/BuildAccessControlMiddleware.php`

**What changes:** Reads from DB via repositories instead of config; stale-data workaround removed.

- Constructor: remove `private array $config`; add `private RoleRepository $roleRepository`,
  `private RuleRepository $ruleRepository`
- `process()`: remove `$request->getAttribute(AclInterface::class) ?? $this->config` — gone entirely
- `$configRoles`: `$this->roleRepository->fetchAll()` — returns `[roleId => parents[]]`
- `$configAllow` / `$configDeny`: `$this->ruleRepository->fetchAll()` — returns rows;
  split by `type` into allow/deny maps
- `$configResources`: derived from the union of resource_ids in the rule rows (same logic,
  different source)
- Everything downstream (protectedRoutes, unprotectedRoutes, rules view model, inherited
  detection) — **unchanged**

### 12. `Admin/Middleware/Container/BuildAccessControlMiddlewareFactory.php`

- Remove: `$config[AclInterface::class]` injection
- Add: `RoleRepository`, `RuleRepository`
- `AssertionManager` (already injected, no change)

### 13. `Admin/CommandHandler/SaveRuleHandler.php`

**What changes:** INSERT/UPDATE via `RuleRepository` instead of `ConfigSaveEvent`.

- Constructor: remove `array $config`, `EventDispatcherInterface`; add
  `RuleRepository $ruleRepository`
- `handle()`: `$this->ruleRepository->save($command->type, $command->roleId,
  $command->resourceId, $command->assertions)`
- Remove all `ConfigSaveEvent` / `ConfigBustCacheEvent` code

### 14. `Admin/CommandHandler/Container/SaveRuleHandlerFactory.php`

- Remove: config + EventDispatcher injection
- Add: `RuleRepository`

### 15. `Admin/CommandHandler/UpdateRuleTypeHandler.php`

**What changes:** UPDATE via repositories instead of config read-modify-write.

- Constructor: remove `array $config`, `EventDispatcherInterface`; add
  `RoleRepository $roleRepository`, `RuleRepository $ruleRepository`
- `handle()`:
  1. `$this->ruleRepository->updateType($roleId, $resourceId, $newType)`
  2. Cascade — `$this->roleRepository->fetchDirectChildren($roleId)`: for each child
     where `$this->ruleRepository->findByRoleAndResource($child, $resourceId) === null`,
     call `$this->ruleRepository->save($oldType, $child, $resourceId, [])`
  3. Return `CommandStatus::Success` — no payload needed (stale-data workaround removed)
- Remove all `ConfigSaveEvent` / `ConfigBustCacheEvent` / `AclInterface::class` payload code

### 16. `Admin/CommandHandler/Container/UpdateRuleTypeHandlerFactory.php`

- Remove: config + EventDispatcher injection
- Add: `RoleRepository`, `RuleRepository`

### 17. `Admin/CommandHandler/SaveRoleHandler.php`

**What changes:** Implement the `@todo` — save via `RoleRepository`.

- Constructor: remove `array $config`; add `RoleRepository $roleRepository`
- `handle()`: `$this->roleRepository->save($command->roleId, $command->parents)`

### 18. `Admin/CommandHandler/Container/SaveRoleHandlerFactory.php`

- Remove: config injection
- Add: `RoleRepository`

### 19. `Admin/CommandHandler/DeleteRoleHandler.php`

**What changes:** Implement the `@todo` — delete via `RoleRepository`.

- Constructor: remove `array $config`; add `RoleRepository $roleRepository`
- `handle()`: `$this->roleRepository->delete($command->roleId)`
- **Decision:** orphaned `acl_rule` rows for a deleted role are left in place.
  They become inert — the role no longer exists so rules are never applied.

### 20. `Admin/CommandHandler/Container/DeleteRoleHandlerFactory.php`

- Remove: config injection
- Add: `RoleRepository`

### 21. `Admin/Middleware/ProcessRuleMiddleware.php`

**One line removed:** The `$request->withAttribute(AclInterface::class, $result->getResult())`
line added as the stale-data workaround is no longer needed. Remove it.

### 22. `Container/Configuration.php`

- Remove: `LOCAL_CONFIG_FILE` constant — no more config writes

### 23. `config/autoload/acl.global.php`

**Strip mutable sections; keep structural config only.**

Remove: `roles`, `resources`, `allow`, `deny`

Keep:
```php
'Webware\\Acl\\AclInterface' => [
    'login_path'             => '/user.manager/login',
    'route_param_map'        => [],
    'forbidden_redirect'     => '/',
    'forbidden_template'     => null,
    'admin_route_segment'    => 'acl.manager',
    'admin_route_name_prefix'=> 'acl.manager.',
],
```

---

## What Is NOT Changing

The following are confirmed unchanged — do not touch them:

- `SaveRuleCommand`, `UpdateRuleTypeCommand`, `SaveRoleCommand`, `DeleteRoleCommand`
- `RouteProvider`
- `ProcessRoleMiddleware` + its factory
- `AclOverviewHandler`, `RoleListHandler`, `ResourceListHandler` + their factories
- `AuthorizationMiddleware`, `IdentityMiddleware` + their factories
- `AssertionManager`, `AssertionManagerFactory`
- `OwnershipAssertion`
- `AclInterface`, `Acl`, `Entity/Role`, `Role/*`, `Http/*`, `Exception/*`
- `admin-acl.phtml` and all partials
- `public/assets/js/app.js`
- All `ConfigProvider` command_map entries (command → handler mapping stays identical)

---

## Execution Order

1. Write migration files `016_acl_role.sql`, `017_acl_rule.sql`
2. Add seed entries to `999_seed.sql`
3. Run migrations + seed against DB
4. Create `RoleRepository` + `RoleRepositoryFactory`
5. Create `RuleRepository` + `RuleRepositoryFactory`
6. Register both repositories in `ConfigProvider::getDependencies()`
7. Simplify `AclFactory` (bare instantiation only)
8. Create `AclMiddleware` + `AclMiddlewareFactory`
9. Add `AclMiddleware` to `config/pipeline.php`
10. Modify `AuthorizationMiddleware` + factory (read from request attribute)
11. Modify `BuildAccessControlMiddleware` + its factory (use repositories)
12. Modify `SaveRuleHandler` + factory
13. Modify `UpdateRuleTypeHandler` + factory
14. Implement `SaveRoleHandler` + factory
15. Implement `DeleteRoleHandler` + factory
16. Remove stale-data line from `ProcessRuleMiddleware`
17. Remove `LOCAL_CONFIG_FILE` from `Configuration.php`
18. Strip `acl.global.php`
19. Verify: page loads, rules display, toggle works, toast appears

---

## Known Risks

- **Cascade query syntax:** `JSON_CONTAINS(parent_id, JSON_QUOTE(:roleId))` requires MySQL 5.7+.
  Confirmed supported by the project's MySQL version (see `docker/database/mysql/`).
- **`AclFactory` assertion bug:** Currently broken for alias-string assertions regardless of
  this migration. Fix is included in step 4 above.
- **`acl.global.php` is still the write target for `ConfigSaveEvent`** until step 12.
  Do not run the app in a half-migrated state.
