-- ============================================================
-- Migrazione: Aggiunta campo priorita a WP_TT_TASK
-- ============================================================

USE tasktracker;

-- Aggiunge la colonna priorita a WP_TT_TASK se non esiste già
ALTER TABLE WP_TT_TASK 
ADD COLUMN priorita ENUM('P1','P2','P3','P4','P5','P6') DEFAULT 'P3' 
AFTER tags;

-- Verifica che la colonna sia stata creata correttamente
DESCRIBE WP_TT_TASK;

-- Visualizza alcuni task per confermare
SELECT id, descrizione, priorita FROM WP_TT_TASK LIMIT 5;
