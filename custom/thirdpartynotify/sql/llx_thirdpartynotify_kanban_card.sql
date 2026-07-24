CREATE TABLE IF NOT EXISTS llx_thirdpartynotify_kanban_card (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL DEFAULT 1,
	fk_user_dest integer NOT NULL,
	fk_actioncomm integer NOT NULL,
	fk_soc integer NOT NULL,
	event_ref varchar(32) NOT NULL,
	event_label varchar(255) NOT NULL,
	event_date_start datetime DEFAULT NULL,
	event_date_end datetime DEFAULT NULL,
	contacts_json text DEFAULT NULL,
	status varchar(32) NOT NULL DEFAULT 'pending',
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uk_thirdpartynotify_kanban_user_event (entity, fk_user_dest, fk_actioncomm),
	KEY idx_thirdpartynotify_kanban_user_status (entity, fk_user_dest, status)
) ENGINE=innodb;
