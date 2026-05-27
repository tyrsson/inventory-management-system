-- =============================================================================
-- major_code
-- Farmers product category codes (called "Major Codes" internally).
-- Managed by supervisors/managers via the Settings page.
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS major_code;
CREATE TABLE IF NOT EXISTS major_code (
    id          SMALLINT UNSIGNED AUTO_INCREMENT,
    code        VARCHAR(20)  NOT NULL,
    description VARCHAR(100) NOT NULL,
    params       JSON               NULL     COMMENT 'Plugin extension data',
    PRIMARY KEY (id),
    UNIQUE KEY uq_major_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
