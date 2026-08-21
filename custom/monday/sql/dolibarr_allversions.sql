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

CREATE TABLE IF NOT EXISTS llx_monday_client_need_client (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    label varchar(255) NOT NULL,
    fk_city_option integer NOT NULL DEFAULT 0,
    position integer NOT NULL DEFAULT 0,
    datec datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS llx_monday_client_need_city_option (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    label varchar(255) NOT NULL,
    position integer NOT NULL DEFAULT 0,
    UNIQUE KEY uk_monday_client_need_city_label (label)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO llx_monday_client_need_city_option (label, position) VALUES ('Lille', 0), ('Paris', 1);

CREATE TABLE IF NOT EXISTS llx_monday_client_need_item (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_client integer NOT NULL,
    label varchar(255) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'running',
    position integer NOT NULL DEFAULT 0,
    datec datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_monday_client_need_item_client (fk_client),
    INDEX idx_monday_client_need_item_status (status)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;
