-- Suppression des tables existantes (ordre inverse des dépendances)
DROP TABLE IF EXISTS llx_myworkspace_comment_file;
DROP TABLE IF EXISTS llx_myworkspace_task_file;
DROP TABLE IF EXISTS llx_myworkspace_comment;
DROP TABLE IF EXISTS llx_myworkspace_cell;
DROP TABLE IF EXISTS llx_myworkspace_column_option;
DROP TABLE IF EXISTS llx_myworkspace_task;
DROP TABLE IF EXISTS llx_myworkspace_column;
DROP TABLE IF EXISTS llx_myworkspace_group;
DROP TABLE IF EXISTS llx_myworkspace;

-- ================================================================================
-- 1. Table principale des espaces de travail
-- ================================================================================
CREATE TABLE llx_myworkspace (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    label varchar(255) NOT NULL,
    position int(11) DEFAULT 0,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_position (position)
) ENGINE=innodb;

-- ================================================================================
-- 2. Table des groupes dans les espaces de travail
-- ================================================================================
CREATE TABLE llx_myworkspace_group (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_workspace int(11) NOT NULL,
    label varchar(255) NOT NULL,
    position int(11) DEFAULT 0,
    collapsed tinyint(1) DEFAULT 0,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fk_workspace (fk_workspace),
    INDEX idx_position (position),
    FOREIGN KEY (fk_workspace) REFERENCES llx_myworkspace(rowid) ON DELETE CASCADE
) ENGINE=innodb;

-- ================================================================================
-- 3. Table des colonnes dans les groupes
-- ================================================================================
CREATE TABLE llx_myworkspace_column (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_workspace int(11) NOT NULL,
    fk_group int(11) NOT NULL,
    label varchar(255) NOT NULL,
    position int(11) DEFAULT 0,
    type varchar(50) DEFAULT 'text',
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fk_workspace (fk_workspace),
    INDEX idx_fk_group (fk_group),
    INDEX idx_position (position),
    FOREIGN KEY (fk_workspace) REFERENCES llx_myworkspace(rowid) ON DELETE CASCADE,
    FOREIGN KEY (fk_group) REFERENCES llx_myworkspace_group(rowid) ON DELETE CASCADE
) ENGINE=innodb;

-- ================================================================================
-- 4. Table des options pour les colonnes de type select/multi-select
-- ================================================================================
CREATE TABLE llx_myworkspace_column_option (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_column int(11) NOT NULL,
    label varchar(255) NOT NULL,
    color varchar(7) DEFAULT '#cccccc',
    position int(11) DEFAULT 0,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fk_column (fk_column),
    INDEX idx_position (position),
    FOREIGN KEY (fk_column) REFERENCES llx_myworkspace_column(rowid) ON DELETE CASCADE,
    UNIQUE KEY unique_option_per_column (fk_column, label)
) ENGINE=innodb;

-- ================================================================================
-- 5. Table des tâches
-- ================================================================================
CREATE TABLE llx_myworkspace_task (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_group int(11) NOT NULL,
    label varchar(255) NOT NULL,
    position int(11) DEFAULT 0,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fk_group (fk_group),
    INDEX idx_position (position),
    FOREIGN KEY (fk_group) REFERENCES llx_myworkspace_group(rowid) ON DELETE CASCADE
) ENGINE=innodb;

-- ================================================================================
-- 6. Table des cellules (valeurs des colonnes pour chaque tâche)
-- ================================================================================
CREATE TABLE llx_myworkspace_cell (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_task int(11) NOT NULL,
    fk_column int(11) NOT NULL,
    value text,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fk_task (fk_task),
    INDEX idx_fk_column (fk_column),
    UNIQUE KEY unique_cell_per_task_column (fk_task, fk_column),
    FOREIGN KEY (fk_task) REFERENCES llx_myworkspace_task(rowid) ON DELETE CASCADE,
    FOREIGN KEY (fk_column) REFERENCES llx_myworkspace_column(rowid) ON DELETE CASCADE
) ENGINE=innodb;

-- ================================================================================
-- 7. Table des commentaires sur les tâches
-- ================================================================================
CREATE TABLE llx_myworkspace_comment (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_task int(11) NOT NULL,
    fk_user int(11) NOT NULL,
    comment text NOT NULL,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fk_task (fk_task),
    INDEX idx_fk_user (fk_user),
    INDEX idx_datec (datec),
    FOREIGN KEY (fk_task) REFERENCES llx_myworkspace_task(rowid) ON DELETE CASCADE
) ENGINE=innodb;

-- ================================================================================
-- 8. Table des fichiers associés aux tâches
-- ================================================================================
CREATE TABLE llx_myworkspace_task_file (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_task int(11) NOT NULL,
    original_name varchar(255) NOT NULL,
    filename varchar(255) NOT NULL,
    filesize int(11) NOT NULL,
    mimetype varchar(100),
    fk_user int(11) NOT NULL,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fk_task (fk_task),
    INDEX idx_fk_user (fk_user),
    INDEX idx_datec (datec),
    FOREIGN KEY (fk_task) REFERENCES llx_myworkspace_task(rowid) ON DELETE CASCADE
) ENGINE=innodb;

-- ================================================================================
-- 9. Table des fichiers associés aux commentaires
-- ================================================================================
CREATE TABLE llx_myworkspace_comment_file (
    rowid int(11) AUTO_INCREMENT PRIMARY KEY,
    fk_comment int(11) NOT NULL,
    original_name varchar(255) NOT NULL,
    filename varchar(255) NOT NULL,
    filesize int(11) NOT NULL,
    mimetype varchar(100),
    fk_user int(11) NOT NULL,
    datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fk_comment (fk_comment),
    INDEX idx_fk_user (fk_user),
    INDEX idx_datec (datec),
    FOREIGN KEY (fk_comment) REFERENCES llx_myworkspace_comment(rowid) ON DELETE CASCADE
) ENGINE=innodb;
