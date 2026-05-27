-- =============================================================================
-- product
-- One row per physical piece, created when a manifest item is confirmed.
-- A 2-chair box (case_qty = 2) on a manifest produces 2 product rows, both
-- carrying the same ao_number and case_qty = 2.  Each piece has independent
-- status tracking from day one.  Piece counts on this table are plain COUNT(*).
-- There is NO unique constraint on ao_number — multiple pieces from one box
-- legitimately share the same AO#.
--
-- removed_at is NULL while the item is in active inventory.  It is stamped when
-- the item exits via one of the four removal workflows:
--   ticket          — delivered or picked up by a customer
--   transfer        — sent to another store
--   pqa_resolution  — disposed/returned after PQA credit received
--   adjustment      — manual manager adjustment
--
-- customer_name is set on ticket removal as the anti-fraud record that Celerant
-- does not provide.
--
-- Depends on: manifest_item, store, sku_catalogue, user
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS product;
CREATE TABLE IF NOT EXISTS product (
    id               INT UNSIGNED       AUTO_INCREMENT,
    manifest_item_id INT UNSIGNED       NOT NULL,
    store_id         SMALLINT UNSIGNED  NOT NULL,
    ao_number        VARCHAR(20)        NOT NULL,
    sku              MEDIUMINT UNSIGNED NOT NULL,
    vsn              VARCHAR(30)        NOT NULL DEFAULT '',
    specs            VARCHAR(255)       NOT NULL DEFAULT '',
    case_qty         SMALLINT UNSIGNED  NOT NULL DEFAULT 1
                     COMMENT 'Original pieces-per-box from the manifest scan. This row is always one physical piece; case_qty records the box it came from (e.g. 2 for a 2-chair set).',
    serial_number    VARCHAR(100)       NULL
                     COMMENT 'Manufacturer serial number; only present on serialised products',
    customer_name    VARCHAR(200)       NULL
                     COMMENT 'Set on ticket removal for anti-fraud record',
    removed_at       DATETIME           NULL
                     COMMENT 'NULL = in active inventory',
    removed_reason   ENUM(
        'Ticket',
        'Transfer',
        'PQA Resolution',
        'Adjustment'
    )                                   NULL,
    removed_by       INT UNSIGNED       NULL,
    created_at       DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    params       JSON               NULL     COMMENT 'Plugin extension data',
    PRIMARY KEY (id),
    KEY idx_product_store_active (store_id, removed_at),
    KEY idx_product_ao (ao_number),
    KEY idx_product_sku (sku),
    KEY idx_product_serial (serial_number),
    CONSTRAINT fk_product_manifest_item FOREIGN KEY (manifest_item_id) REFERENCES manifest_item (id),
    CONSTRAINT fk_product_store         FOREIGN KEY (store_id)         REFERENCES store          (store_number),
    CONSTRAINT fk_product_sku           FOREIGN KEY (sku)              REFERENCES sku_catalogue  (sku),
    CONSTRAINT fk_product_removed_by    FOREIGN KEY (removed_by)       REFERENCES `user`           (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
