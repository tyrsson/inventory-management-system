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
| Factories | `AclFactory`, `BuildAccessControlMiddlewareFactory`, `SaveRuleHandlerFactory`, `UpdateRuleTypeHandlerFactory`, `SaveRoleHandlerFactory`, `DeleteRoleHandlerFactory` |
| Handlers | `SaveRuleHandler`, `UpdateRuleTypeHandler`, `SaveRoleHandler`, `DeleteRoleHandler` |
| Middleware | `BuildAccessControlMiddleware`, `ProcessRuleMiddleware` (minor) |
| Config file | `acl.global.php` — strip mutable sections only |
| Constants | `Container/Configuration.php` — remove `LOCAL_CONFIG_FILE` |

**Explicitly out of scope — do not touch:**

- All `Command` classes — they are pure data carriers, unchanged
- All templates and partials
- `RouteProvider`, `ProcessRoleMiddleware`, `ProcessRuleMiddleware` (except one line)
- `AuthorizationMiddleware`, `IdentityMiddleware`
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

**What changes:** Reads from DB instead of config.

- In `__invoke()`: get `AdapterInterface` from container; query `acl_role` and `acl_rule`
  instead of reading `$config['roles']`, `$config['allow']`, `$config['deny']`
- `addRoles()`: receives rows from `SELECT id, role_id, parent_id FROM acl_role`
- `addResources()`: derive unique `resource_id` values from `SELECT DISTINCT resource_id FROM acl_rule`
  (no separate resource table — RouteCollector is source of truth for what routes exist)
- `applyRules()`: receives rows from `SELECT type, role_id, resource_id, assertions FROM acl_rule`
- **Bug fix included:** `buildAssertion()` currently tries `class_exists($fqcn)` but config
  (and DB) stores alias strings like `'Store Owned Resource'`. Inject `AssertionManager` and
  resolve via `$assertionManager->get($alias)` instead of `new $fqcn()`.

### 2. `Admin/Middleware/BuildAccessControlMiddleware.php`

**What changes:** Reads from DB instead of config; stale-data workaround removed.

- Constructor: remove `private array $config`; add `private AdapterInterface $adapter`
- `process()`: remove `$request->getAttribute(AclInterface::class) ?? $this->config` — gone entirely
- `$configRoles`: query `SELECT role_id, parent_id FROM acl_role`; decode JSON parent_id
- `$configAllow` / `$configDeny`: query `SELECT type, role_id, resource_id, assertions FROM acl_rule`; decode JSON assertions
- `$configResources`: derived from the union of resource_ids in the rule rows (same logic, different source)
- Everything downstream (protectedRoutes, unprotectedRoutes, rules view model, inherited detection) — **unchanged**

### 3. `Admin/Middleware/Container/BuildAccessControlMiddlewareFactory.php`

- Remove: `$config[AclInterface::class]` injection
- Add: `$container->get(AdapterInterface::class)`
- Add: `AssertionManager` (already injected, no change)

### 4. `Admin/CommandHandler/SaveRuleHandler.php`

**What changes:** INSERT/UPDATE DB row instead of ConfigSaveEvent.

- Constructor: remove `array $config`, `EventDispatcherInterface`; add `AdapterInterface $adapter`
- `handle()`: `INSERT INTO acl_rule (type, role_id, resource_id, assertions) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE type = VALUES(type), assertions = VALUES(assertions)`
- Remove all `ConfigSaveEvent` / `ConfigBustCacheEvent` code
- Result payload: return the inserted/updated row id or null (no view model needed — same as before)

### 5. `Admin/CommandHandler/Container/SaveRuleHandlerFactory.php`

- Remove: config + EventDispatcher injection
- Add: `AdapterInterface`

### 6. `Admin/CommandHandler/UpdateRuleTypeHandler.php`

**What changes:** UPDATE DB row instead of config read-modify-write.

- Constructor: remove `array $config`, `EventDispatcherInterface`; add `AdapterInterface $adapter`
- `handle()`:
  1. `UPDATE acl_rule SET type = :newType WHERE role_id = :roleId AND resource_id = :resourceId`
  2. Cascade — query direct children: `SELECT role_id FROM acl_role WHERE JSON_CONTAINS(parent_id, JSON_QUOTE(:roleId))`
     For each child with no existing rule for `$resourceId`, INSERT explicit `$oldType` rule with empty assertions
  3. Return `CommandStatus::Success` — no payload needed (stale-data workaround removed)
- Remove all `ConfigSaveEvent` / `ConfigBustCacheEvent` / `AclInterface::class` payload code

### 7. `Admin/CommandHandler/Container/UpdateRuleTypeHandlerFactory.php`

- Remove: config + EventDispatcher injection
- Add: `AdapterInterface`

### 8. `Admin/CommandHandler/SaveRoleHandler.php`

**What changes:** Implement the `@todo` — INSERT/UPDATE `acl_role`.

- Constructor: remove `array $config`; add `AdapterInterface $adapter`
- `handle()`: `INSERT INTO acl_role (role_id, parent_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE parent_id = VALUES(parent_id)`

### 9. `Admin/CommandHandler/Container/SaveRoleHandlerFactory.php`

- Remove: config injection
- Add: `AdapterInterface`

### 10. `Admin/CommandHandler/DeleteRoleHandler.php`

**What changes:** Implement the `@todo` — DELETE from `acl_role`.

- Constructor: remove `array $config`; add `AdapterInterface $adapter`
- `handle()`: `DELETE FROM acl_role WHERE role_id = :roleId`
- **Decision:** orphaned `acl_rule` rows (rules referencing a deleted role) are left in place.
  They become inert — the role no longer exists in Laminas ACL so the rules are never applied.
  A follow-up cleanup query can be added later if needed.

### 11. `Admin/CommandHandler/Container/DeleteRoleHandlerFactory.php`

- Remove: config injection
- Add: `AdapterInterface`

### 12. `Admin/Middleware/ProcessRuleMiddleware.php`

**One line removed:** The `$request->withAttribute(AclInterface::class, $result->getResult())`
line added as the stale-data workaround is no longer needed. Remove it.

### 13. `Container/Configuration.php`

- Remove: `LOCAL_CONFIG_FILE` constant — no more config writes

### 14. `config/autoload/acl.global.php`

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
3. Run migrations against DB
4. Modify `AclFactory` (+ fix assertion resolution bug)
5. Modify `BuildAccessControlMiddleware` + its factory
6. Modify `SaveRuleHandler` + factory
7. Modify `UpdateRuleTypeHandler` + factory
8. Implement `SaveRoleHandler` + factory
9. Implement `DeleteRoleHandler` + factory
10. Remove stale-data line from `ProcessRuleMiddleware`
11. Remove `LOCAL_CONFIG_FILE` from `Configuration.php`
12. Strip `acl.global.php`
13. Verify: page loads, rules display, toggle works, toast appears

---

## Known Risks

- **Cascade query syntax:** `JSON_CONTAINS(parent_id, JSON_QUOTE(:roleId))` requires MySQL 5.7+.
  Confirmed supported by the project's MySQL version (see `docker/database/mysql/`).
- **`AclFactory` assertion bug:** Currently broken for alias-string assertions regardless of
  this migration. Fix is included in step 4 above.
- **`acl.global.php` is still the write target for `ConfigSaveEvent`** until step 12.
  Do not run the app in a half-migrated state.
