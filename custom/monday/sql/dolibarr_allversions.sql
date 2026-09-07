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

CREATE TABLE IF NOT EXISTS llx_monday_graph_sync_state (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    mailbox_email varchar(255) NOT NULL,
    folder_name varchar(64) NOT NULL DEFAULT 'SentItems',
    delta_link text,
    last_success_at datetime DEFAULT NULL,
    last_error text,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_monday_graph_sync_state_mailbox_folder (mailbox_email, folder_name)
) ENGINE=innodb;

ALTER TABLE llx_myworkspace_task_file
    ADD COLUMN IF NOT EXISTS fk_inbound_email int(11) DEFAULT NULL AFTER fk_task;

ALTER TABLE llx_myworkspace_task_file
    ADD INDEX IF NOT EXISTS idx_fk_inbound_email (fk_inbound_email);

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
    status varchar(20) NOT NULL DEFAULT 'active',
    mail varchar(255) DEFAULT NULL,
    contact varchar(255) DEFAULT NULL,
    telephone varchar(255) DEFAULT NULL,
    poste_occupe varchar(255) DEFAULT NULL,
    datec datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_monday_client_need_client_status (status)
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
