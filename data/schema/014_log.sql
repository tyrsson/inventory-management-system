-- =============================================================================
-- log
-- Application log records written by axleus-log / Monolog PhpDbHandler.
-- Depends on: (none — user_identifier is informational, not a FK)
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `log`;
CREATE TABLE IF NOT EXISTS `log` (
    id              BIGINT UNSIGNED   AUTO_INCREMENT,
    channel         VARCHAR(64)       NOT NULL DEFAULT '',
    level           VARCHAR(20)       NOT NULL DEFAULT '',
    uuid            VARCHAR(36)       NULL,
    message         TEXT              NOT NULL,
    time            INT UNSIGNED      NOT NULL COMMENT 'Unix timestamp',
    user_identifier VARCHAR(255)      NULL     COMMENT 'Email or other identity at log time',
    context         JSON              NULL     COMMENT 'Monolog context and extra data',
    PRIMARY KEY (id),
    INDEX idx_log_level   (level),
    INDEX idx_log_channel (channel),
    INDEX idx_log_time    (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
