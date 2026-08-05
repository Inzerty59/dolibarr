--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--

ALTER TABLE llx_myworkspace_task
    ADD COLUMN IF NOT EXISTS inbound_email_token varchar(64) DEFAULT NULL AFTER position;

ALTER TABLE llx_myworkspace_task
    ADD UNIQUE KEY IF NOT EXISTS uk_inbound_email_token (inbound_email_token);

CREATE TABLE IF NOT EXISTS llx_monday_inbound_email (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    message_key varchar(64) NOT NULL,
    fk_task int(11) NOT NULL,
    fk_comment int(11) NOT NULL DEFAULT 0,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_monday_inbound_email_message_key (message_key),
    INDEX idx_monday_inbound_email_fk_task (fk_task)
) ENGINE=innodb;

ALTER TABLE llx_myworkspace_task_file
    ADD COLUMN IF NOT EXISTS fk_inbound_email int(11) DEFAULT NULL AFTER fk_task;

ALTER TABLE llx_myworkspace_task_file
    ADD INDEX IF NOT EXISTS idx_fk_inbound_email (fk_inbound_email);
