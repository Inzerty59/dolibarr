-- T6 - Associates one project with one ticket template.
-- The selector is rendered by a projectcard hook, then this association is
-- saved by the PROJECT_CREATE/PROJECT_MODIFY trigger.
CREATE TABLE IF NOT EXISTS llx_tickets_project_template (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_project integer NOT NULL,
  fk_template integer DEFAULT NULL,
  datec datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_create integer NULL,
  fk_user_modif integer NULL,

  UNIQUE KEY uk_tickets_project_template_project (fk_project),
  KEY idx_tickets_project_template_template (fk_template)
) ENGINE=innodb;
