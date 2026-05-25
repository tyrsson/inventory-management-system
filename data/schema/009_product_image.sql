-- =============================================================================
-- product_image
-- Photos attached to damaged products.  filename is the path relative to the
-- configured image storage root (local filesystem for v1).
-- Images are purged by a cleanup job when the parent product is removed from
-- inventory.
-- Depends on: product, user
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS product_image;
CREATE TABLE IF NOT EXISTS product_image (
    id          INT UNSIGNED AUTO_INCREMENT,
    product_id  INT UNSIGNED NOT NULL,
    filename    VARCHAR(255) NOT NULL COMMENT 'Path relative to image storage root',
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pi_product (product_id),
    CONSTRAINT fk_pi_product     FOREIGN KEY (product_id)  REFERENCES product (id),
    CONSTRAINT fk_pi_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES `user`    (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
