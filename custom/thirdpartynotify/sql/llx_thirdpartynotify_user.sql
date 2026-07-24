CREATE TABLE IF NOT EXISTS llx_thirdpartynotify_user (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL DEFAULT 1,
	fk_user integer NOT NULL,
	context_type varchar(64) NOT NULL DEFAULT 'thirdparty_messaging',
	fk_context integer NOT NULL DEFAULT 0,
	active tinyint NOT NULL DEFAULT 1,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer DEFAULT NULL,
	fk_user_modif integer DEFAULT NULL,
	UNIQUE KEY uk_thirdpartynotify_user_context (entity, context_type, fk_context, fk_user),
	KEY idx_thirdpartynotify_user_entity_context (entity, context_type, fk_context),
	KEY idx_thirdpartynotify_user_user (fk_user)
) ENGINE=innodb;
