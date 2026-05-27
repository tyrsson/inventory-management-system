-- =============================================================================
-- transfer_item
-- Each AO# staged and confirmed for an outbound transfer.
-- Depends on: transfer, product, user
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS transfer_item;
CREATE TABLE IF NOT EXISTS transfer_item (
    id           INT UNSIGNED AUTO_INCREMENT,
    transfer_id  INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NOT NULL,
    confirmed_by INT UNSIGNED NOT NULL,
    confirmed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transfer_item (transfer_id, product_id),
    CONSTRAINT fk_xferi_transfer     FOREIGN KEY (transfer_id)  REFERENCES transfer (id),
    CONSTRAINT fk_xferi_product      FOREIGN KEY (product_id)   REFERENCES product  (id),
    CONSTRAINT fk_xferi_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES `user`     (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
