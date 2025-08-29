-- Table pour stocker les fichiers associés aux tâches
CREATE TABLE llx_myworkspace_task_file (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_task int(11) NOT NULL,
    original_name varchar(255) NOT NULL,
    filename varchar(255) NOT NULL,
    filesize int(11) NOT NULL,
    mimetype varchar(100),
    fk_user int(11) NOT NULL,
    datec datetime NOT NULL,
    INDEX idx_fk_task (fk_task),
    INDEX idx_fk_user (fk_user)
) ENGINE=innodb;
