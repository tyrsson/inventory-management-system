-- =============================================================================
-- session
-- PhpDb-backed session storage.
-- id          : PHP session identifier (32-char hex from random_bytes(16)).
-- payload     : PHP-serialized session data written by ext-session.
-- modified_at : updated on every write; used as the GC reference timestamp.
-- expires_at  : absolute expiry computed from gc_maxlifetime on each write.
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `session`;
CREATE TABLE IF NOT EXISTS `session` (
    id          VARCHAR(64)  NOT NULL                        COMMENT 'PHP session identifier',
    payload     MEDIUMTEXT   NOT NULL                        COMMENT 'PHP-serialized session data',
    modified_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP     COMMENT 'Last write timestamp',
    expires_at  DATETIME     NOT NULL                        COMMENT 'Absolute expiry (NOW + gc_maxlifetime)',
    PRIMARY KEY (id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
