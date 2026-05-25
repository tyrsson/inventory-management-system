-- =============================================================================
-- sku_catalogue
-- Keyed by the 6-digit Farmers SKU.  Rows are inserted on first encounter of a
-- SKU during manifest scanning and updated whenever richer data is available.
-- This auto-filling lookup eliminates re-entry of known SKUs over time.
-- Depends on: major_code
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS sku_catalogue;
CREATE TABLE IF NOT EXISTS sku_catalogue (
    sku           MEDIUMINT UNSIGNED NOT NULL COMMENT '6-digit Farmers SKU',
    description   VARCHAR(255)       NOT NULL DEFAULT '',
    vendor        VARCHAR(50)        NOT NULL DEFAULT '' COMMENT 'DC vendor abbreviation (e.g. EMBY)',
    vendor_model  VARCHAR(50)        NOT NULL DEFAULT '',
    major_code_id SMALLINT UNSIGNED  NULL,
    updated_at    DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
    params       JSON               NULL     COMMENT 'Plugin extension data',
    PRIMARY KEY (sku),
    CONSTRAINT fk_sku_major_code FOREIGN KEY (major_code_id) REFERENCES major_code (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
