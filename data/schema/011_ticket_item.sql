-- =============================================================================
-- ticket_item
-- Each AO# confirmed during ticket processing.
-- The application stamps product.removed_at when the ticket is completed —
-- not via a DB trigger.
-- Depends on: ticket, product, user
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ticket_item;
CREATE TABLE IF NOT EXISTS ticket_item (
    id           INT UNSIGNED AUTO_INCREMENT,
    ticket_id    INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NOT NULL,
    confirmed_by INT UNSIGNED NOT NULL,
    confirmed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ticket_item (ticket_id, product_id),
    CONSTRAINT fk_ti_ticket       FOREIGN KEY (ticket_id)   REFERENCES ticket  (id),
    CONSTRAINT fk_ti_product      FOREIGN KEY (product_id)  REFERENCES product (id),
    CONSTRAINT fk_ti_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES `user`   (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
