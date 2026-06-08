-- =============================================================================
-- Reference data seed -- run after all table migrations
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Stores
-- Replace pqa_email values with real addresses before deploying.
-- -----------------------------------------------------------------------------
INSERT INTO store (store_number, city, state, pqaEmail) VALUES
    (207, 'Leeds',      'AL', 'pqa-207@example.com'),
    (112, 'Birmingham', 'AL', 'pqa-112@example.com')
ON DUPLICATE KEY UPDATE
    city      = VALUES(city),
    state     = VALUES(state),
    pqaEmail  = VALUES(pqaEmail);

-- -----------------------------------------------------------------------------
-- Developer seed user -- Joey Smith (Developer, Store 207)
-- role_id is a plain VARCHAR -- no FK, no subquery needed.
-- ON DUPLICATE KEY covers both the PK and the uq_user_email unique key.
-- -----------------------------------------------------------------------------
INSERT INTO `user` (storeId, roleId, firstName, lastName, email, passwordHash, active)
VALUES (207, '["Developer"]', 'Joey', 'Smith', 'jsmith@webinertia.net',
        '$2y$12$5oaeB9aVIDGlccWGxAlHhuQg9mBL6RHxgGBHTHe9/03nXCCofAfBG',
        1)
ON DUPLICATE KEY UPDATE
    storeId       = VALUES(storeId),
    roleId        = VALUES(roleId),
    firstName     = VALUES(firstName),
    lastName      = VALUES(lastName),
    passwordHash  = VALUES(passwordHash),
    active        = VALUES(active);

-- -----------------------------------------------------------------------------
-- ACL roles
-- -----------------------------------------------------------------------------
INSERT INTO `acl_role` (roleId, parentId) VALUES
('Guest',                JSON_ARRAY()),
('Member',               JSON_ARRAY('Guest')),
('Warehouse',            JSON_ARRAY('Member')),
('Sales',                JSON_ARRAY('Member')),
('Collections',          JSON_ARRAY('Member')),
('Warehouse Supervisor', JSON_ARRAY('Warehouse')),
('Assistant Manager',    JSON_ARRAY('Sales', 'Warehouse', 'Collections')),
('Manager',              JSON_ARRAY('Assistant Manager', 'Warehouse Supervisor')),
('Administrator',        JSON_ARRAY('Member', 'Manager')),
('Developer',            JSON_ARRAY('Administrator'))
ON DUPLICATE KEY UPDATE parentId = VALUES(parentId);

-- -----------------------------------------------------------------------------
-- ACL rules
-- -----------------------------------------------------------------------------
INSERT INTO `acl_rule` (type, roleId, resourceId, assertions) VALUES
-- Guest allow
('Allow','Guest','user.manager.session.read',                          JSON_ARRAY()),
('Allow','Guest','user.manager.session.create',                        JSON_ARRAY()),
('Allow','Guest','user.manager.register.read',                         JSON_ARRAY()),
('Allow','Guest','user.manager.register.create',                       JSON_ARRAY()),
('Allow','Guest','user.manager.verify.email.read',                     JSON_ARRAY()),
('Allow','Guest','user.manager.resend.verification.read',              JSON_ARRAY()),
('Allow','Guest','user.manager.resend.verification.create',            JSON_ARRAY()),
('Allow','Guest','app.api.ping',                                       JSON_ARRAY()),
-- Member allow
('Allow','Member','user.manager.logout.read',                          JSON_ARRAY()),
('Allow','Member','app.dashboard',                                     JSON_ARRAY()),
('Allow','Member','ims.manifest.list',                                 JSON_ARRAY()),
('Allow','Member','ims.manifest.detail',                               JSON_ARRAY()),
('Allow','Member','app.api.ping',                                      JSON_ARRAY()),
-- Member deny
('Deny','Member','user.manager.session.read',                          JSON_ARRAY()),
('Deny','Member','user.manager.session.create',                        JSON_ARRAY()),
('Deny','Member','user.manager.register.read',                         JSON_ARRAY()),
('Deny','Member','user.manager.register.create',                       JSON_ARRAY()),
('Deny','Member','user.manager.verify.email.read',                     JSON_ARRAY()),
('Deny','Member','user.manager.resend.verification.read',              JSON_ARRAY()),
('Deny','Member','user.manager.resend.verification.create',            JSON_ARRAY()),
-- Administrator allow
('Allow','Administrator','webware.admin.user.manager',                 JSON_ARRAY()),
('Allow','Administrator','webware.admin.user.manager.create',          JSON_ARRAY()),
('Allow','Administrator','webware.admin.user.manager.update',          JSON_ARRAY()),
('Allow','Administrator','webware.admin.user.manager.toggle.update',   JSON_ARRAY()),
('Allow','Administrator','webware.admin.dashboard.read',               JSON_ARRAY()),
-- Developer allow
('Allow','Developer','webware.admin.acl.manager',                      JSON_ARRAY()),
-- Warehouse allow
('Allow','Warehouse','ims.manifest.upload',       JSON_ARRAY('Ownership')),
('Allow','Warehouse','ims.manifest.upload.store', JSON_ARRAY('Ownership')),
-- Warehouse Supervisor allow
('Allow','Warehouse Supervisor','admin.manifest',                      JSON_ARRAY())
ON DUPLICATE KEY UPDATE
    type       = VALUES(type),
    assertions = VALUES(assertions);

-- -----------------------------------------------------------------------------
-- ACL manager child rules (explicit rows so BuildAccessControlMiddleware can
-- read the parent/child relationship directly without resource-tree traversal)
-- -----------------------------------------------------------------------------
INSERT INTO `acl_rule` (type, roleId, resourceId, assertions, parentResourceId) VALUES
('Allow','Developer','webware.admin.acl.manager.role.read',          JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.role.add.modal',      JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.role.edit.modal',     JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.rule.create',       JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.rule.update',       JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.rule.delete',       JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.rule.delete.modal', JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.role.create',       JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.role.update',       JSON_ARRAY(), 'webware.admin.acl.manager'),
('Allow','Developer','webware.admin.acl.manager.role.delete',       JSON_ARRAY(), 'webware.admin.acl.manager')
ON DUPLICATE KEY UPDATE
    type             = VALUES(type),
    assertions       = VALUES(assertions),
    parentResourceId = VALUES(parentResourceId);
