CREATE TABLE IF NOT EXISTS llx_tickets_email_template ( 
    rowid integer AUTO_INCREMENT PRIMARY KEY, 
    entity integer DEFAULT 1 NOT NULL, fk_project integer NULL, 
    event_code varchar(64) NOT NULL, fk_email_template integer NOT NULL, 
    active tinyint DEFAULT 1 NOT NULL, datec datetime NULL, 
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    UNIQUE KEY uk_tickets_email_template (entity, fk_project, event_code), 
    INDEX idx_tickets_email_template_project (fk_project), 
    INDEX idx_tickets_email_template_event (event_code), 
    INDEX idx_tickets_email_template_email_template (fk_email_template) 
) ENGINE=innodb;