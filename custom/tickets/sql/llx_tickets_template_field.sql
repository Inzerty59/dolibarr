CREATE TABLE IF NOT EXISTS llx_tickets_template_field (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_template integer NOT NULL,
  attrname varchar(128) NOT NULL,
  label varchar(255) NULL,
  type varchar(64) NULL,
  size varchar(64) NULL,
  required tinyint DEFAULT 0 NOT NULL,
  position integer DEFAULT 0 NOT NULL,
  default_value text NULL,
  enabled tinyint DEFAULT 1 NOT NULL,
  definition_json LONGTEXT NULL
) ENGINE=innodb;
