CREATE TABLE llx_outlooksync_state (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL DEFAULT 1,
	user_email varchar(255) NOT NULL,
	delta_link text,
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	last_error text,
	UNIQUE KEY uk_outlooksync_state_mailbox (entity, user_email)
) ENGINE=innodb;
