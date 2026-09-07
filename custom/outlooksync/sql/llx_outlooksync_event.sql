CREATE TABLE llx_outlooksync_event (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL DEFAULT 1,
	fk_actioncomm integer NOT NULL,
	fk_user integer NOT NULL,
	user_email varchar(255) NOT NULL,
	outlook_event_id varchar(255) NOT NULL,
	content_hash varchar(64) DEFAULT NULL,
	attendees_hash varchar(64) DEFAULT NULL,
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	last_error text,
	UNIQUE KEY uk_outlooksync_event_action_user (entity, fk_actioncomm, fk_user),
	UNIQUE KEY uk_outlooksync_event_outlook_user (entity, user_email, outlook_event_id)
) ENGINE=innodb;
