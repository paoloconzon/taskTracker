-- ============================================================
-- SEED DATA
-- ============================================================

-- ------------------------------------------------------------
-- 1. AZIONI
-- ------------------------------------------------------------
INSERT IGNORE INTO WP_TT_AZIONE (nome) VALUES
  ('analisi'),
  ('assistenza'),
  ('check'),
  ('debug'),
  ('demo'),
  ('esercizi'),
  ('formazione'),
  ('mail'),
  ('pausa'),
  ('query'),
  ('rilascio'),
  ('riunione'),
  ('studio'),
  ('sviluppo'),
  ('test'),
  ('varie');


-- ------------------------------------------------------------
-- 2. ARGOMENTI — livello AREA (radice, senza parent)
--    se_personale = 0  → visibili a tutti gli utenti
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, descrizione, mantis, colore, icona, se_personale) VALUES
--  nome            descrizione                                       mantis   colore      icona                            personale
  ('ARTICOLI',    'Anagrafica articoli',                            '9985',  '#1565C0', 'mdi-package-variant-closed',    0),
  ('BRASILE',     'Fiscalità e progetti Brasile',                   '10350', '#1B5E20', 'mdi-earth',                     0),
  ('CALCE',       'Calce 2.0',                                      '9934',  '#78909C', 'mdi-wall',                      0),
  ('COMMERCIALE', 'Gestione cliente',                               '10349', '#E65100', 'mdi-handshake',                 0),
  ('DWH',         'Business Intelligence / Data Warehouse',         '9984',  '#4A148C', 'mdi-chart-bar',                 0),
  ('FATTURAZIONE','Ciclo fatturazione',                             '9933',  '#006064', 'mdi-file-document-edit',        0),
  ('FORMAZIONE',  'Attività di formazione',                         '9939',  '#F57F17', 'mdi-school',                    0),
  ('FORNITORI',   'Gestione fornitori',                             '9987',  '#4E342E', 'mdi-truck-delivery',            0),
  ('HELPDESK',    'Supporto helpdesk',                              '10347', '#B71C1C', 'mdi-headset',                   0),
  ('INCAS',       'Incas - automazione magazzino',                  '9938',  '#263238', 'mdi-robot-industrial',          0),
  ('LOGISTICA',   'Ufficio spedizioni e logistica',                 '10348', '#2E7D32', 'mdi-truck',                     0),
  ('MOLVINA',     'Progetto Molvina',                               '10397', '#0D47A1', 'mdi-factory',                   0),
  ('OTC',         'Order To Cash - ciclo attivo',                   '10028', '#00695C', 'mdi-cash-register',             0),
  ('PRIMAVERA',   'Fassalusa 2.0',                                  '9935',  '#558B2F', 'mdi-sprout',                    0),
  ('REVISORI',    'Attività per revisori',                          '9969',  '#311B92', 'mdi-file-search',               0),
  ('SALAQUADRI',  'Sale quadri',                                    '9936',  '#880E4F', 'mdi-palette',                   0),
  ('SCUOLA',      'Attività scolastiche',                           NULL,    '#283593', 'mdi-book-open-page-variant',    0),
  ('SKYLINE',     'Progetto Skyline',                               '9780',  '#37474F', 'mdi-city',                      0),
  ('VARIE',       'Attività varie non categorizzate',               '10351', '#616161', 'mdi-dots-horizontal-circle',    0),
  ('VISTEX',      'S4/Hana - migrazione ciclo attivo',              '9583',  '#BF360C', 'mdi-application-cog',           0),
  ('WFA',         'Work Force Automation',                          '9937',  '#E65100', 'mdi-account-hard-hat',          0);


-- ------------------------------------------------------------
-- 3. ARGOMENTI — livello SOTTOAREA (figli)
--    se_personale = 1  → visibili solo al proprietario
--    mantis: valorizzato dove ha un codice proprio,
--            NULL dove eredita dal padre (vedi UPDATE finale)
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, id_argomento_padre, descrizione, mantis, colore, icona, se_personale)

-- BRASILE ↓
SELECT 'DIFAL',                             id, 'Differenziale aliquota ICMS',               '10255', '#1B5E20', 'mdi-percent',             1
  FROM WP_TT_ARGOMENTI WHERE nome = 'BRASILE'      AND id_argomento_padre IS NULL UNION ALL
SELECT 'RIFORMA_FISCALE',                   id, 'Riforma fiscale brasiliana',                 '10196', '#1B5E20', 'mdi-scale-balance',       1
  FROM WP_TT_ARGOMENTI WHERE nome = 'BRASILE'      AND id_argomento_padre IS NULL UNION ALL
SELECT 'TASSE',                             id, 'Tassazione Brasile',                         '9989',  '#1B5E20', 'mdi-cash-multiple',       1
  FROM WP_TT_ARGOMENTI WHERE nome = 'BRASILE'      AND id_argomento_padre IS NULL UNION ALL
SELECT 'VENDA_A_ORDEM',                     id, 'Vendita su ordine (Brasile)',                 '10290', '#1B5E20', 'mdi-cart-arrow-right',    1
  FROM WP_TT_ARGOMENTI WHERE nome = 'BRASILE'      AND id_argomento_padre IS NULL UNION ALL
SELECT 'NF_COMPLEMENTAR',                   id, 'Nota fiscal complementare',                  '10371', '#1B5E20', 'mdi-file-plus',           1
  FROM WP_TT_ARGOMENTI WHERE nome = 'BRASILE'      AND id_argomento_padre IS NULL UNION ALL
SELECT 'ICMSST',                            id, 'ICMS Substituição Tributária',               '10396', '#1B5E20', 'mdi-swap-horizontal',     1
  FROM WP_TT_ARGOMENTI WHERE nome = 'BRASILE'      AND id_argomento_padre IS NULL UNION ALL

-- COMMERCIALE ↓
SELECT 'fwblocchi',                         id, 'Gestione blocchi cliente',                   '9974',  '#E65100', 'mdi-view-grid-plus',      1
  FROM WP_TT_ARGOMENTI WHERE nome = 'COMMERCIALE'  AND id_argomento_padre IS NULL UNION ALL

-- FATTURAZIONE ↓
SELECT 'INTERCOMPANY',                      id, 'Fatturazione intercompany',                  '9884',  '#006064', 'mdi-swap-horizontal-bold',1
  FROM WP_TT_ARGOMENTI WHERE nome = 'FATTURAZIONE' AND id_argomento_padre IS NULL UNION ALL
SELECT 'RAPPRESENTANTE FISCALE PORTOGHESE', id, 'Rappresentante fiscale Portogallo',          '10395', '#006064', 'mdi-flag',                1
  FROM WP_TT_ARGOMENTI WHERE nome = 'FATTURAZIONE' AND id_argomento_padre IS NULL UNION ALL

-- INCAS ↓
SELECT 'ARTICOLI',                          id, 'Articoli magazzino Incas',                   '8861',  '#263238', 'mdi-barcode-scan',        1
  FROM WP_TT_ARGOMENTI WHERE nome = 'INCAS'        AND id_argomento_padre IS NULL UNION ALL

-- SALAQUADRI ↓
SELECT 'CALCE',                             id, 'Sala quadri Calce',                          '10198', '#880E4F', 'mdi-wall',                1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SALAQUADRI'   AND id_argomento_padre IS NULL UNION ALL
SELECT 'CALLIANO_GYPTECH',                  id, 'Sala quadri Calliano/Gyptech',               '10345', '#880E4F', 'mdi-domain',              1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SALAQUADRI'   AND id_argomento_padre IS NULL UNION ALL
SELECT 'ENERGIA',                           id, 'Sala quadri Energia',                        '9797',  '#880E4F', 'mdi-lightning-bolt',      1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SALAQUADRI'   AND id_argomento_padre IS NULL UNION ALL
SELECT 'TARANCON',                          id, 'Sala quadri Tarancon',                       '10344', '#880E4F', 'mdi-domain',              1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SALAQUADRI'   AND id_argomento_padre IS NULL UNION ALL
SELECT 'PAITONE',                           id, 'Sala quadri Paitone',                        '10370', '#880E4F', 'mdi-domain',              1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SALAQUADRI'   AND id_argomento_padre IS NULL UNION ALL

-- SKYLINE ↓
SELECT 'VILLARUBIO',                        id, 'Skyline - sede Villarubio',                  '10256', '#37474F', 'mdi-warehouse',           1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SKYLINE'      AND id_argomento_padre IS NULL UNION ALL

-- WFA ↓
SELECT 'LIFEBOAT',                          id, 'WFA - progetto Lifeboat',                    '10197', '#E65100', 'mdi-lifebuoy',            1
  FROM WP_TT_ARGOMENTI WHERE nome = 'WFA'          AND id_argomento_padre IS NULL UNION ALL

-- SCUOLA ↓  (mantis NULL → erediterà dal padre con l'UPDATE finale)
SELECT 'TECNOLOGIA',                        id, 'Materia: Tecnologia',                        NULL,    '#283593', 'mdi-laptop',              1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA'       AND id_argomento_padre IS NULL UNION ALL
SELECT 'MECCANICA',                         id, 'Materia: Meccanica',                         NULL,    '#283593', 'mdi-cog',                 1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA'       AND id_argomento_padre IS NULL UNION ALL
SELECT 'DPOI',                              id, 'Materia: DPOI',                              NULL,    '#283593', 'mdi-clipboard-list',      1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA'       AND id_argomento_padre IS NULL UNION ALL
SELECT 'SISTEMI',                           id, 'Materia: Sistemi',                           NULL,    '#283593', 'mdi-server',              1
  FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA'       AND id_argomento_padre IS NULL;


-- ------------------------------------------------------------
-- 4. Eredita mantis dal padre dove il figlio non ce l'ha
-- ------------------------------------------------------------
UPDATE WP_TT_ARGOMENTI a
  JOIN WP_TT_ARGOMENTI p ON p.id = a.id_argomento_padre
   SET a.mantis = p.mantis
 WHERE a.mantis IS NULL
   AND p.mantis IS NOT NULL;


-- ------------------------------------------------------------
-- 5. Assicura che PAUSA sia non-personale
-- ------------------------------------------------------------
UPDATE WP_TT_ARGOMENTI SET se_personale = 0 WHERE se_pausa = 1;
