-- T6 - Stores custom fields attached to ticket templates.
-- These fields are mirrored into Dolibarr extrafields before ticket creation
-- so the final ticket is still persisted by the native extrafields mechanism.
CREATE TABLE IF NOT EXISTS llx_tickets_template_field (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_template integer NOT NULL,

  attrname varchar(128) NOT NULL,
  label varchar(255) NOT NULL,
  type varchar(64) NOT NULL,
  size varchar(64) NULL,

  fieldrequired tinyint DEFAULT 0 NOT NULL,
  fielddefault text NULL,
  param text NULL,

  pos integer DEFAULT 100 NOT NULL,
  enabled tinyint DEFAULT 1 NOT NULL,

  options_json LONGTEXT NULL,

  datec datetime NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_ticket_template_field (fk_template, attrname)
) ENGINE=innodb;
