<?php
// ============================================================
// config.php  –  Configurazione DB e costanti
// Copia questo file in config.php e adatta i valori
// ============================================================
define('DB_HOST',    'localhost');
define('DB_NAME',    'tasktracker');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// Durata sessione in minuti (0 = nessuna scadenza)
define('SESSION_TTL_MIN', 480);

// ID argomento PAUSA (seed fisso = 1)
define('ARGOMENTO_PAUSA_ID', 1);
