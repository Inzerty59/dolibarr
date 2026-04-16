CREATE TABLE llx_monplugin_todo (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	description text,
	titre text, 
	status integer DEFAULT 0 NOT NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer
);
