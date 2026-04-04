-- ============================================================
-- TASK TRACKER - Schema MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS tasktracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tasktracker;

CREATE TABLE IF NOT EXISTS WP_TT_UTENTI (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user        VARCHAR(50)  NOT NULL UNIQUE,
    pwd         VARCHAR(255) NOT NULL,
    descrizione VARCHAR(255),
    mail        VARCHAR(150),
    gruppo      VARCHAR(100)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS WP_TT_SESSIONI (
    sessionId            CHAR(50)   NOT NULL PRIMARY KEY,
    user                 VARCHAR(50) NOT NULL,
    data_ora_inizio      DATETIME    NOT NULL,
    data_ora_ultima_call DATETIME    NOT NULL,
    se_attivo            TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS WP_TT_ARGOMENTI (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    id_utente          INT          NULL,
    nome               VARCHAR(150) NOT NULL,
    id_argomento_padre INT          NULL,
    descrizione        VARCHAR(255),
    colore             VARCHAR(20)  DEFAULT '#607D8B',
    icona              VARCHAR(80)  DEFAULT 'mdi-folder',
    se_chiuso          TINYINT(1)   NOT NULL DEFAULT 0,
    se_pausa           TINYINT(1)   NOT NULL DEFAULT 0,
    se_personale       TINYINT(1)   NOT NULL DEFAULT 1,
    flag1              VARCHAR(255),
    flag2              VARCHAR(255),
    flag3              VARCHAR(255),
    FOREIGN KEY (id_argomento_padre) REFERENCES WP_TT_ARGOMENTI(id),
    FOREIGN KEY (id_utente)          REFERENCES WP_TT_UTENTI(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS WP_TT_AZIONE (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS WP_TT_TASK (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    id_utente    INT         NOT NULL,
    id_argomento INT         NOT NULL,
    id_azione    INT         NULL,
    se_chiuso    TINYINT(1)   NOT NULL DEFAULT 0,
    descrizione  VARCHAR(1024),
    flag1        VARCHAR(255),
    flag2        VARCHAR(255),
    flag3        VARCHAR(255),
    FOREIGN KEY (id_utente)    REFERENCES WP_TT_UTENTI(id),
    FOREIGN KEY (id_argomento) REFERENCES WP_TT_ARGOMENTI(id),
    FOREIGN KEY (id_azione)    REFERENCES WP_TT_AZIONE(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS WP_TT_TASK_LOG (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    id_task         INT      NOT NULL,
    descrizione     VARCHAR(1024),
    data_ora_inizio DATETIME NOT NULL,
    data_ora_fine   DATETIME NULL,
    note            TEXT,
    FOREIGN KEY (id_task) REFERENCES WP_TT_TASK(id)
) ENGINE=InnoDB;

-- ============================================================
-- SEED
-- ============================================================
-- pwd: admin123
INSERT IGNORE INTO WP_TT_UTENTI (user, pwd, descrizione, gruppo) VALUES
('admin','$2y$10$IcX3iSKBRDl7EAHmyBxoCeH20iC81Cnqpz4l11CZOHOWibLuES/1y','Amministratore','admin');

INSERT IGNORE INTO WP_TT_AZIONE (nome) VALUES
('Sviluppo'),('Analisi'),('Assistenza'),('Riunione'),('Test'),('Documentazione'),('Altro');

-- id=1 riservato a PAUSA
INSERT INTO WP_TT_ARGOMENTI (id,nome,id_argomento_padre,descrizione,colore,icona,se_pausa) VALUES
(1,'PAUSA',    NULL,'Pausa / interruzione','#9E9E9E','mdi-coffee',1)
ON DUPLICATE KEY UPDATE id=id;
