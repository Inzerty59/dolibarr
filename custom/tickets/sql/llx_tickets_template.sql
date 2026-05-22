-- T6 - Stores ticket template headers.
-- One row is one reusable business template. Native ticket fields are not
-- stored here; this table only names and activates custom template definitions.
CREATE TABLE IF NOT EXISTS llx_tickets_template (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  label varchar(255) NOT NULL,
  active tinyint DEFAULT 1 NOT NULL,
  datec datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_create integer NULL,
  fk_user_modif integer NULL
) ENGINE=innodb;
