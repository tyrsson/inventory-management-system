-- =============================================================================
-- product_status
-- Multi-flag status table.  A product can hold several flags simultaneously
-- (e.g. damaged + floor, or damaged + pending_pqa).
-- One row per active flag; remove the row to clear the flag.
-- Depends on: product, user
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS product_status;
CREATE TABLE IF NOT EXISTS product_status (
    id         INT UNSIGNED AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    status     ENUM(
        'Overstock',
        'Damaged',
        'Floor',
        'Pending PQA',
        'Bargain Center',
        'Reparable',
        'Non Reparable'
    ) NOT NULL,
    set_by     INT UNSIGNED NOT NULL,
    set_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_status_flag (product_id, status),
    CONSTRAINT fk_ps_product FOREIGN KEY (product_id) REFERENCES product (id),
    CONSTRAINT fk_ps_set_by  FOREIGN KEY (set_by)     REFERENCES `user`    (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
