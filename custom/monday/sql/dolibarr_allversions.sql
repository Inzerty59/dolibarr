--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--

CREATE TABLE IF NOT EXISTS llx_monday_candidate_retention_mail_log (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_task integer NOT NULL,
    campaign varchar(64) NOT NULL,
    status varchar(16) NOT NULL,
    recipient varchar(255) DEFAULT NULL,
    subject varchar(255) DEFAULT NULL,
    error_message text,
    date_attempt datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_monday_retention_task_campaign (fk_task, campaign),
    INDEX idx_monday_retention_status (status),
    INDEX idx_monday_retention_date_attempt (date_attempt)
) ENGINE=innodb;
