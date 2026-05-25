-- =============================================================================
-- Reference data seed -- run after all table migrations
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Stores
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
-- Developer seed user -- Joey Smith (Developer, Store 207)
-- role_id is a plain VARCHAR -- no FK, no subquery needed.
-- ON DUPLICATE KEY covers both the PK and the uq_user_email unique key.
-- -----------------------------------------------------------------------------
INSERT INTO `user` (store_id, role_id, first_name, last_name, email, password_hash, active)
VALUES (207, '["Developer"]', 'Joey', 'Smith', 'jsmith@webinertia.net',
        '$2y$12$5oaeB9aVIDGlccWGxAlHhuQg9mBL6RHxgGBHTHe9/03nXCCofAfBG',
        1)
ON DUPLICATE KEY UPDATE
    store_id      = VALUES(store_id),
    role_id       = VALUES(role_id),
    first_name    = VALUES(first_name),
    last_name     = VALUES(last_name),
    password_hash = VALUES(password_hash),
    active        = VALUES(active);
