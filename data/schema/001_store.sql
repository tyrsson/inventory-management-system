-- =============================================================================
-- store
-- Primary identifier is the Farmers store number (e.g. 207 = Leeds AL).
-- pqa_email is the mailbox monitored by the PQA system; damage images are sent
-- here so the PQA system can auto-associate them with a case.
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS store;
CREATE TABLE IF NOT EXISTS store (
    store_number SMALLINT UNSIGNED  NOT NULL COMMENT 'Farmers store number (e.g. 207)',
    city         VARCHAR(100)       NOT NULL,
    state        CHAR(2)            NOT NULL COMMENT 'Two-letter US state abbreviation',
    pqa_email    VARCHAR(255)       NOT NULL COMMENT 'PQA system mailbox for damage images',
    params       JSON               NULL     COMMENT 'Plugin extension data',
    PRIMARY KEY (store_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
