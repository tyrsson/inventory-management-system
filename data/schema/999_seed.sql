-- =============================================================================
-- Reference data seed — run after all table files (001–014)
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Roles (ordered by ascending privilege level)
-- guest is the base role for unauthenticated requests; all others inherit from it.
-- -----------------------------------------------------------------------------
INSERT INTO role (role_id) VALUES
    ('guest'),
    ('member'),
    ('Sales'),
    ('Warehouse'),
    ('Warehouse Supervisor'),
    ('Credit Manager'),
    ('DC Warehouse'),
    ('Manager'),
    ('Administrator'),
    ('Developer')
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- -----------------------------------------------------------------------------
-- Sample stores
-- Replace pqa_email values with real addresses before deploying.
-- -----------------------------------------------------------------------------
INSERT INTO store (store_number, city, state, pqa_email) VALUES
    (207, 'Leeds',      'AL', 'pqa-207@example.com'),
    (112, 'Birmingham', 'AL', 'pqa-112@example.com')
ON DUPLICATE KEY UPDATE
    city      = VALUES(city),
    state     = VALUES(state),
    pqa_email = VALUES(pqa_email);

-- -----------------------------------------------------------------------------
-- ACL: Resources
-- -----------------------------------------------------------------------------
INSERT INTO acl_resource (resource_id, label, `system`) VALUES
    ('public',  'Public', 1),
    ('user',    'User',   1)
ON DUPLICATE KEY UPDATE label = VALUES(label), `system` = VALUES(`system`);

-- -----------------------------------------------------------------------------
-- ACL: Privileges
-- public resource
-- -----------------------------------------------------------------------------
INSERT INTO acl_privilege (resource_pk, privilege_id, label)
SELECT r.resource_pk, p.privilege_id, p.label
FROM acl_resource r
JOIN (
    SELECT 'read' AS privilege_id, 'Read' AS label
) p ON r.resource_id = 'public'
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- user resource — standard CRUD only; no named-action privileges
INSERT INTO acl_privilege (resource_pk, privilege_id, label)
SELECT r.resource_pk, p.privilege_id, p.label
FROM acl_resource r
JOIN (
    SELECT 'read'   AS privilege_id, 'Read'   AS label UNION ALL
    SELECT 'create',                 'Create'          UNION ALL
    SELECT 'update',                 'Update'          UNION ALL
    SELECT 'delete',                 'Delete'
) p ON r.resource_id = 'user'
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- -----------------------------------------------------------------------------
-- ACL: Role inheritance
-- guest is the root; all authenticated roles inherit from guest.
-- Higher roles inherit from their direct parent.
-- -----------------------------------------------------------------------------
INSERT INTO acl_role_parent (role_pk, parent_pk)
SELECT child.id, parent.id
FROM role child
JOIN role parent ON (
    (child.role_id = 'member'              AND parent.role_id = 'guest')             OR
    (child.role_id = 'Sales'               AND parent.role_id = 'member')            OR
    (child.role_id = 'Warehouse'           AND parent.role_id = 'member')            OR
    (child.role_id = 'Warehouse Supervisor' AND parent.role_id = 'Warehouse')        OR
    (child.role_id = 'Credit Manager'      AND parent.role_id = 'Warehouse Supervisor') OR
    (child.role_id = 'DC Warehouse'        AND parent.role_id = 'Warehouse Supervisor') OR
    (child.role_id = 'Manager'             AND parent.role_id = 'DC Warehouse')      OR
    (child.role_id = 'Administrator'       AND parent.role_id = 'Manager')     OR
    (child.role_id = 'Developer'            AND parent.role_id = 'Administrator')
)
ON DUPLICATE KEY UPDATE parent_pk = VALUES(parent_pk);

-- -----------------------------------------------------------------------------
-- ACL: Baseline rules
-- guest: public/read, user/read (login + register forms), user/create (form POSTs)
-- Authenticated roles (member+): deny user/read and user/create to block access
-- to login and register pages; they have no business submitting those forms.
-- -----------------------------------------------------------------------------

-- guest allow: public → read
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.privilege_pk, pr.privilege_pk, 'allow'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id = 'read'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'public'
WHERE ro.role_id = 'guest'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- guest allow: user → read (login form GET, register form GET)
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id = 'read'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'user'
WHERE ro.role_id = 'guest'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- guest allow: user → create (login form POST, register form POST)
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id = 'create'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'user'
WHERE ro.role_id = 'guest'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- Authenticated roles deny: user → read
-- Denying on member propagates down the entire authenticated hierarchy.
-- Prevents authenticated users reaching login / register pages.
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'deny'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id = 'read'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'user'
WHERE ro.role_id = 'member'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- Authenticated roles deny: user → create
-- Prevents authenticated users from submitting login / register forms.
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'deny'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id = 'create'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'user'
WHERE ro.role_id = 'member'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- -----------------------------------------------------------------------------
-- ACL: Additional resources — dashboard, admin.user
-- -----------------------------------------------------------------------------
INSERT INTO acl_resource (resource_id, label, `system`) VALUES
    ('dashboard',       'Dashboard',                  1),
    ('admin.user',      'Admin — User Management',    1),
    ('admin.manifest',  'Admin — Manifest Management',1)
ON DUPLICATE KEY UPDATE label = VALUES(label), `system` = VALUES(`system`);

-- -----------------------------------------------------------------------------
-- ACL: Privileges for dashboard + admin.manifest (read only)
-- -----------------------------------------------------------------------------
INSERT INTO acl_privilege (resource_pk, privilege_id, label)
SELECT r.resource_pk, 'read', 'Read'
FROM acl_resource r WHERE r.resource_id IN ('dashboard', 'admin.manifest')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- -----------------------------------------------------------------------------
-- ACL: Privileges for admin.user
-- -----------------------------------------------------------------------------
INSERT INTO acl_privilege (resource_pk, privilege_id, label)
SELECT r.resource_pk, p.privilege_id, p.label
FROM acl_resource r
JOIN (
    SELECT 'read'   AS privilege_id, 'Read'   AS label UNION ALL
    SELECT 'create',                 'Create'          UNION ALL
    SELECT 'update',                 'Update'          UNION ALL
    SELECT 'delete',                 'Delete'
) p ON r.resource_id = 'admin.user'
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- -----------------------------------------------------------------------------
-- ACL: Rules — dashboard (authenticated roles only)
-- member is the base authenticated role; grant here propagates to all roles
-- via the hierarchy.
-- -----------------------------------------------------------------------------
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id = 'read'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'dashboard'
WHERE ro.role_id = 'member'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- -----------------------------------------------------------------------------
-- ACL: Rules — user/logout
-- guest denied; all authenticated roles allowed via member.
-- -----------------------------------------------------------------------------
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id = 'logout'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'user'
WHERE ro.role_id = 'member'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- -----------------------------------------------------------------------------
-- ACL: Rules — admin.user (Manager and Administrator)
-- Manager inherits to Administrator via hierarchy; grant Manager only.
-- -----------------------------------------------------------------------------
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id IN ('read','create','update','delete')
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'admin.user'
WHERE ro.role_id = 'Manager'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- -----------------------------------------------------------------------------
-- ACL: Route → resource + privilege mappings
-- -----------------------------------------------------------------------------
INSERT INTO acl_route_privilege (route_name, resource_pk, privilege_pk)
SELECT routes.route_name, pr.resource_pk, pr.privilege_pk
FROM (
    -- Public / login / register pages
    SELECT 'user.login'              AS route_name, 'user'       AS resource_id, 'login'    AS privilege_id UNION ALL
    SELECT 'user.login.post',                       'user',                      'login'                    UNION ALL
    SELECT 'user.register',                         'user',                      'register'                 UNION ALL
    SELECT 'user.register.post',                    'user',                      'register'                 UNION ALL
    -- Email verification — public
    SELECT 'user.verify-email',                     'public',                    'read'                     UNION ALL
    SELECT 'user.verify-email.resend',              'public',                    'read'                     UNION ALL
    -- Authenticated user routes
    SELECT 'user.logout',                           'user',                      'logout'                   UNION ALL
    -- Dashboard
    SELECT 'dashboard',                             'dashboard',                 'read'                     UNION ALL
    SELECT 'api.ping',                              'public',                    'read'                     UNION ALL
    -- Admin user management
    SELECT 'admin.user.list.read',                    'admin.user',                'read'                     UNION ALL
    SELECT 'admin.user.create',                     'admin.user',                'create'                   UNION ALL
    SELECT 'admin.user.update',                     'admin.user',                'update'                   UNION ALL
    SELECT 'admin.user.toggle.update',              'admin.user',                'update'
) AS routes
JOIN acl_resource  re ON re.resource_id  = routes.resource_id
JOIN acl_privilege pr ON pr.privilege_id = routes.privilege_id AND pr.resource_pk = re.resource_pk
ON DUPLICATE KEY UPDATE resource_pk = VALUES(resource_pk), privilege_pk = VALUES(privilege_pk);

-- -----------------------------------------------------------------------------
-- ACL: Rule — member → user → update (with OwnershipAssertion)
-- Grants any authenticated user the ability to update their own user record.
-- The assertion is stored in acl_rule_assertion; AclBuilder attaches it at
-- runtime so the check only passes when editing one's own record.
-- -----------------------------------------------------------------------------
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role ro
JOIN acl_privilege pr ON pr.privilege_id = 'update'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'user'
WHERE ro.role_id = 'member'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- -----------------------------------------------------------------------------
-- ACL: Rule Assertion — member → user → update → OwnershipAssertion
-- Attaches Webware\Acl\Assertion\OwnershipAssertion to the rule above so that
-- AclBuilder loads it at runtime. The check passes only when the identity's
-- user_id matches the resource's user_id (editing one's own record).
-- -----------------------------------------------------------------------------
INSERT INTO acl_rule_assertion (rule_pk, assertion, mode, sort_order)
SELECT ar.id, 'Webware\\Acl\\Assertion\\OwnershipAssertion', 'all', 0
FROM acl_rule ar
JOIN role          ro ON ro.id           = ar.role_pk
JOIN acl_privilege pr ON pr.resource_pk  = ar.resource_pk AND pr.privilege_pk = ar.privilege_pk
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk
WHERE ro.role_id        = 'member'
  AND re.resource_id    = 'user'
  AND pr.privilege_id   = 'update'
  AND ar.type           = 'allow'
ON DUPLICATE KEY UPDATE assertion = VALUES(assertion);


-- -----------------------------------------------------------------------------
-- ACL: Store-scoped resources
-- manifest, product, product_image, ticket, transfer, store.settings
-- Global catalogue resources: sku_catalogue, major_code
-- -----------------------------------------------------------------------------
INSERT INTO acl_resource (resource_id, label, `system`) VALUES
    ('manifest',      'Manifest',       1),
    ('product',       'Product',        1),
    ('product_image', 'Product Image',  1),
    ('ticket',        'Ticket',         1),
    ('transfer',      'Transfer',       1),
    ('store.settings','Store Settings', 1),
    ('sku_catalogue',  'SKU Catalogue', 1),
    ('major_code',     'Major Code',    1)
ON DUPLICATE KEY UPDATE label = VALUES(label), `system` = VALUES(`system`);

-- -----------------------------------------------------------------------------
-- ACL: Privileges — standard CRUD for store-scoped resources
-- -----------------------------------------------------------------------------
INSERT INTO acl_privilege (resource_pk, privilege_id, label)
SELECT r.resource_pk, p.privilege_id, p.label
FROM acl_resource r
JOIN (
    SELECT 'read'   AS privilege_id, 'Read'   AS label UNION ALL
    SELECT 'create',                 'Create'          UNION ALL
    SELECT 'update',                 'Update'          UNION ALL
    SELECT 'delete',                 'Delete'
) p ON r.resource_id IN ('manifest','product','product_image','ticket','transfer')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- store.settings: read + update only (rows created with the store — no create/delete)
INSERT INTO acl_privilege (resource_pk, privilege_id, label)
SELECT r.resource_pk, p.privilege_id, p.label
FROM acl_resource r
JOIN (
    SELECT 'read'   AS privilege_id, 'Read'   AS label UNION ALL
    SELECT 'update',                 'Update'
) p ON r.resource_id = 'store.settings'
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- sku_catalogue and major_code: full CRUD
INSERT INTO acl_privilege (resource_pk, privilege_id, label)
SELECT r.resource_pk, p.privilege_id, p.label
FROM acl_resource r
JOIN (
    SELECT 'read'   AS privilege_id, 'Read'   AS label UNION ALL
    SELECT 'create',                 'Create'          UNION ALL
    SELECT 'update',                 'Update'          UNION ALL
    SELECT 'delete',                 'Delete'
) p ON r.resource_id IN ('sku_catalogue','major_code')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- -----------------------------------------------------------------------------
-- ACL: Rules — read grants (no assertion, cross-store intentional)
-- member: read on all store-scoped resources + global catalogue resources
-- -----------------------------------------------------------------------------
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role          ro
JOIN acl_privilege pr ON pr.privilege_id = 'read'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk
    AND re.resource_id IN ('manifest','product','product_image','ticket','transfer','sku_catalogue','major_code')
WHERE ro.role_id = 'member'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- Manager: read on store.settings
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role          ro
JOIN acl_privilege pr ON pr.privilege_id = 'read'
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk AND re.resource_id = 'store.settings'
WHERE ro.role_id = 'Manager'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- Administrator: read + create + update + delete on sku_catalogue, major_code
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role          ro
JOIN acl_privilege pr ON pr.privilege_id IN ('read','create','update','delete')
JOIN acl_resource  re ON re.resource_pk  = pr.resource_pk
    AND re.resource_id IN ('sku_catalogue','major_code')
WHERE ro.role_id = 'Administrator'
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- NOTE: Store-scoped mutation rules (create/update/delete on manifest, product,
-- product_image, ticket, transfer, store.settings) are NOT seeded here as plain
-- SQL rows. They are registered at runtime by RegisterOwnershipAssertionListener
-- (fired on AclBuiltEvent) so that StoreOwnedResourceAssertion can be attached
-- inline. The DB has no mechanism to carry PHP assertion objects.

-- -----------------------------------------------------------------------------
-- Seed user — Joey Smith (Developer, Store 207)
-- -----------------------------------------------------------------------------
INSERT INTO `user` (store_id, role_id, first_name, last_name, email, password_hash, active)
SELECT
    207,
    r.id,
    'Joey',
    'Smith',
    'jsmith@webinertia.net',
    '$2y$12$5oaeB9aVIDGlccWGxAlHhuQg9mBL6RHxgGBHTHe9/03nXCCofAfBG',
    1
FROM role r WHERE r.role_id = 'Developer'
ON DUPLICATE KEY UPDATE
    role_id       = VALUES(role_id),
    password_hash = VALUES(password_hash),
    active        = VALUES(active);

-- -----------------------------------------------------------------------------
-- ACL: Invalidate cache
-- Bump the version counter so AclBuilder discards any stale cached ACL on
-- the next request. Every reseed must end with this statement.
-- -----------------------------------------------------------------------------

-- -----------------------------------------------------------------------------
-- ACL: admin.acl.* resources
-- Each route name IS the resource_id. One resource per route.
-- All 14 admin.acl.* routes are seeded here so they are protected on first
-- deploy. Developer is the only role granted access (via DB rules below);
-- RegisterAclRulesListener handles the in-memory grant for the legacy
-- 'admin.acl' resource name used by the overview handler.
-- -----------------------------------------------------------------------------
INSERT INTO acl_resource (resource_id, label, `system`) VALUES
    ('admin.acl.read',                'ACL — Overview',             1),
    ('admin.acl.roles.read',          'ACL — Roles List',           1),
    ('admin.acl.roles.create',        'ACL — Create Role',          1),
    ('admin.acl.roles.delete',        'ACL — Delete Role',          1),
    ('admin.acl.resources.read',      'ACL — Resources List',       1),
    ('admin.acl.resources.create',    'ACL — Create Resource',      1),
    ('admin.acl.resources.delete',    'ACL — Delete Resource',      1),
    ('admin.acl.resources.protect',   'ACL — Protect Route',        1),
    ('admin.acl.rules.read',          'ACL — Rules List',           1),
    ('admin.acl.rules.create',        'ACL — Create Rule',          1),
    ('admin.acl.rules.update',        'ACL — Update Rule',          1),
    ('admin.acl.rules.delete',        'ACL — Delete Rule',          1),
    ('admin.acl.assertions.create',   'ACL — Create Assertion',     1),
    ('admin.acl.assertions.delete',   'ACL — Delete Assertion',     1)
ON DUPLICATE KEY UPDATE label = VALUES(label), `system` = VALUES(`system`);

-- Privileges — derived from HTTP method via METHOD_PRIVILEGE_MAP
INSERT INTO acl_privilege (resource_pk, privilege_id, label)
SELECT r.resource_pk, p.privilege_id, p.label
FROM acl_resource r
JOIN (
    SELECT 'admin.acl.read'              AS resource_id, 'read'   AS privilege_id, 'Read'   AS label UNION ALL
    SELECT 'admin.acl.roles.read',                       'read',                   'Read'            UNION ALL
    SELECT 'admin.acl.roles.create',                     'create',                 'Create'          UNION ALL
    SELECT 'admin.acl.roles.delete',                     'delete',                 'Delete'          UNION ALL
    SELECT 'admin.acl.resources.read',                   'read',                   'Read'            UNION ALL
    SELECT 'admin.acl.resources.create',                 'create',                 'Create'          UNION ALL
    SELECT 'admin.acl.resources.delete',                 'delete',                 'Delete'          UNION ALL
    SELECT 'admin.acl.resources.protect',                'create',                 'Create'          UNION ALL
    SELECT 'admin.acl.rules.read',                       'read',                   'Read'            UNION ALL
    SELECT 'admin.acl.rules.create',                     'create',                 'Create'          UNION ALL
    SELECT 'admin.acl.rules.update',                     'update',                 'Update'          UNION ALL
    SELECT 'admin.acl.rules.delete',                     'delete',                 'Delete'          UNION ALL
    SELECT 'admin.acl.assertions.create',                'create',                 'Create'          UNION ALL
    SELECT 'admin.acl.assertions.delete',                'delete',                 'Delete'
) p ON r.resource_id = p.resource_id
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Allow rules — Developer only
-- Role inheritance propagates this up the chain if needed in future.
INSERT INTO acl_rule (role_pk, resource_pk, privilege_pk, type)
SELECT ro.id, pr.resource_pk, pr.privilege_pk, 'allow'
FROM role ro
JOIN acl_privilege pr ON 1 = 1
JOIN acl_resource  re ON re.resource_pk = pr.resource_pk
WHERE ro.role_id = 'Developer'
  AND re.resource_id IN (
      'admin.acl.read',
      'admin.acl.roles.read',    'admin.acl.roles.create',    'admin.acl.roles.delete',
      'admin.acl.resources.read','admin.acl.resources.create','admin.acl.resources.delete','admin.acl.resources.protect',
      'admin.acl.rules.read',    'admin.acl.rules.create',    'admin.acl.rules.update',   'admin.acl.rules.delete',
      'admin.acl.assertions.create','admin.acl.assertions.delete'
  )
ON DUPLICATE KEY UPDATE type = VALUES(type);

-- Route → resource+privilege mappings
INSERT INTO acl_route_privilege (route_name, resource_pk, privilege_pk)
SELECT routes.route_name, pr.resource_pk, pr.privilege_pk
FROM (
    SELECT 'admin.acl.read'             AS route_name, 'admin.acl.read'             AS resource_id, 'read'   AS privilege_id UNION ALL
    SELECT 'admin.acl.roles.read',                     'admin.acl.roles.read',                      'read'                   UNION ALL
    SELECT 'admin.acl.roles.create',                   'admin.acl.roles.create',                    'create'                 UNION ALL
    SELECT 'admin.acl.roles.delete',                   'admin.acl.roles.delete',                    'delete'                 UNION ALL
    SELECT 'admin.acl.resources.read',                 'admin.acl.resources.read',                  'read'                   UNION ALL
    SELECT 'admin.acl.resources.create',               'admin.acl.resources.create',                'create'                 UNION ALL
    SELECT 'admin.acl.resources.delete',               'admin.acl.resources.delete',                'delete'                 UNION ALL
    SELECT 'admin.acl.resources.protect',              'admin.acl.resources.protect',               'create'                 UNION ALL
    SELECT 'admin.acl.rules.read',                     'admin.acl.rules.read',                      'read'                   UNION ALL
    SELECT 'admin.acl.rules.create',                   'admin.acl.rules.create',                    'create'                 UNION ALL
    SELECT 'admin.acl.rules.update',                   'admin.acl.rules.update',                    'update'                 UNION ALL
    SELECT 'admin.acl.rules.delete',                   'admin.acl.rules.delete',                    'delete'                 UNION ALL
    SELECT 'admin.acl.assertions.create',              'admin.acl.assertions.create',               'create'                 UNION ALL
    SELECT 'admin.acl.assertions.delete',              'admin.acl.assertions.delete',               'delete'
) AS routes
JOIN acl_resource  re ON re.resource_id  = routes.resource_id
JOIN acl_privilege pr ON pr.privilege_id = routes.privilege_id AND pr.resource_pk = re.resource_pk
ON DUPLICATE KEY UPDATE resource_pk = VALUES(resource_pk), privilege_pk = VALUES(privilege_pk);

UPDATE acl_version SET version = version + 1 WHERE id = 1;
