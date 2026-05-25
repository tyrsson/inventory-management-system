-- =============================================================================
-- manifest_item
-- One row per scanned AO# on a manifest.
--
-- case_qty > 1 means the AO# represents a bundled case (e.g. 2 chairs in one
-- box under one AO#).  At manifest confirmation the application expands the
-- case into case_qty individual product rows, each carrying the same ao_number
-- and the same case_qty value for provenance.  Piece counts on the product
-- table are therefore plain COUNT(*); SUM(case_qty) is only needed here when
-- summarising manifest receiving totals (lines vs pieces).
--
-- UNIQUE KEY on (manifest_id, ao_number): one AO# cannot appear twice on the
-- same manifest — the case_qty column handles multi-piece boxes.
--
-- Depends on: manifest, sku_catalogue, user
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS manifest_item;
CREATE TABLE IF NOT EXISTS manifest_item (
    id          INT UNSIGNED       AUTO_INCREMENT,
    manifest_id INT UNSIGNED       NOT NULL,
    ao_number   VARCHAR(20)        NOT NULL COMMENT 'AO# / Tag ID from SKU card (e.g. A006523361)',
    sku         MEDIUMINT UNSIGNED NOT NULL,
    vsn         VARCHAR(30)        NOT NULL DEFAULT '' COMMENT 'Vendor Stock Number from SKU card',
    specs       VARCHAR(255)       NOT NULL DEFAULT '' COMMENT 'Finish / Cover / Size / ST from SKU card',
    case_qty    SMALLINT UNSIGNED  NOT NULL DEFAULT 1,
    is_damaged  TINYINT(1)         NOT NULL DEFAULT 0 COMMENT 'Flagged damaged at time of scan',
    notes       TEXT               NULL     COMMENT 'Scan-time damage notes',
    scanned_by  INT UNSIGNED       NOT NULL,
    scanned_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    params       JSON               NULL     COMMENT 'Plugin extension data',
    PRIMARY KEY (id),
    UNIQUE KEY uq_manifest_item_ao (manifest_id, ao_number),
    KEY idx_mi_ao (ao_number),
    CONSTRAINT fk_mi_manifest   FOREIGN KEY (manifest_id) REFERENCES manifest      (id),
    CONSTRAINT fk_mi_sku        FOREIGN KEY (sku)         REFERENCES sku_catalogue (sku),
    CONSTRAINT fk_mi_scanned_by FOREIGN KEY (scanned_by)  REFERENCES `user`          (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
