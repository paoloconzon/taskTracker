-- ============================================================
-- SEED DATA 2  –  Argomenti demo generici
-- Macroaree: SCUOLA / LAVORI DI CASA / VOLONTARIATO
-- ============================================================

-- ------------------------------------------------------------
-- MACROAREE (livello radice)
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, descrizione, colore, icona, se_personale) VALUES
  ('SCUOLA',         'Attività scolastiche e studio',         '#1565C0', 'mdi-school',              1),
  ('LAVORI DI CASA', 'Manutenzione e gestione domestica',     '#2E7D32', 'mdi-home-wrench',         1),
  ('VOLONTARIATO',   'Attività di volontariato e comunità',   '#E65100', 'mdi-hand-heart',          1),
  ('SPORT',          'Allenamento e attività fisiche',        '#00838F', 'mdi-run',                 1),
  ('HOBBY',          'Passatempi e interessi personali',      '#6A1B9A', 'mdi-palette',             1),
  ('SALUTE',         'Visite mediche e benessere',            '#C62828', 'mdi-heart-pulse',         1),
  ('FAMIGLIA',       'Commissioni e impegni familiari',       '#F57F17', 'mdi-account-group',       1);

-- ------------------------------------------------------------
-- SCUOLA → sottoaree
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, id_argomento_padre, descrizione, colore, icona, se_personale)
SELECT 'Matematica',    id, 'Esercizi e studio di matematica',       '#1565C0', 'mdi-calculator-variant',   1 FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Italiano',      id, 'Lettura, scrittura e grammatica',       '#1565C0', 'mdi-book-open-variant',    1 FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Inglese',       id, 'Lingua inglese e conversazione',        '#1565C0', 'mdi-translate',            1 FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Scienze',       id, 'Fisica, chimica e biologia',            '#1565C0', 'mdi-flask',                1 FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Storia',        id, 'Storia e geografia',                    '#1565C0', 'mdi-map-legend',           1 FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Informatica',   id, 'Programmazione e uso del computer',     '#1565C0', 'mdi-laptop',               1 FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Compiti',       id, 'Compiti a casa e ripasso',              '#1565C0', 'mdi-pencil-box',           1 FROM WP_TT_ARGOMENTI WHERE nome = 'SCUOLA' AND id_argomento_padre IS NULL;

-- ------------------------------------------------------------
-- LAVORI DI CASA → sottoaree
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, id_argomento_padre, descrizione, colore, icona, se_personale)
SELECT 'Pulizie',       id, 'Pulizia e riordino degli ambienti',     '#2E7D32', 'mdi-broom',                1 FROM WP_TT_ARGOMENTI WHERE nome = 'LAVORI DI CASA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Cucina',        id, 'Preparazione pasti e spesa',            '#2E7D32', 'mdi-chef-hat',             1 FROM WP_TT_ARGOMENTI WHERE nome = 'LAVORI DI CASA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Giardinaggio',  id, 'Cura del giardino e delle piante',      '#2E7D32', 'mdi-flower',               1 FROM WP_TT_ARGOMENTI WHERE nome = 'LAVORI DI CASA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Riparazioni',   id, 'Piccole riparazioni e fai da te',       '#2E7D32', 'mdi-hammer-screwdriver',   1 FROM WP_TT_ARGOMENTI WHERE nome = 'LAVORI DI CASA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Burocrazia',    id, 'Bollette, pratiche e documenti',        '#2E7D32', 'mdi-file-document-edit',   1 FROM WP_TT_ARGOMENTI WHERE nome = 'LAVORI DI CASA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Spesa',         id, 'Acquisti e commissioni',                '#2E7D32', 'mdi-cart',                 1 FROM WP_TT_ARGOMENTI WHERE nome = 'LAVORI DI CASA' AND id_argomento_padre IS NULL;

-- ------------------------------------------------------------
-- VOLONTARIATO → sottoaree
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, id_argomento_padre, descrizione, colore, icona, se_personale)
SELECT 'Croce Rossa',   id, 'Turni e formazione Croce Rossa',        '#E65100', 'mdi-ambulance',            1 FROM WP_TT_ARGOMENTI WHERE nome = 'VOLONTARIATO' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Oratorio',      id, 'Attività parrocchiali e oratorio',      '#E65100', 'mdi-church',               1 FROM WP_TT_ARGOMENTI WHERE nome = 'VOLONTARIATO' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Ambiente',      id, 'Raccolta rifiuti e cura del verde',     '#E65100', 'mdi-leaf',                 1 FROM WP_TT_ARGOMENTI WHERE nome = 'VOLONTARIATO' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Doposcuola',    id, 'Supporto scolastico a ragazzi',         '#E65100', 'mdi-account-school',       1 FROM WP_TT_ARGOMENTI WHERE nome = 'VOLONTARIATO' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Organizzazione',id, 'Pianificazione eventi e riunioni',      '#E65100', 'mdi-calendar-check',       1 FROM WP_TT_ARGOMENTI WHERE nome = 'VOLONTARIATO' AND id_argomento_padre IS NULL;

-- ------------------------------------------------------------
-- SPORT → sottoaree
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, id_argomento_padre, descrizione, colore, icona, se_personale)
SELECT 'Palestra',      id, 'Allenamento in palestra',               '#00838F', 'mdi-dumbbell',             1 FROM WP_TT_ARGOMENTI WHERE nome = 'SPORT' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Corsa',         id, 'Corsa e camminata all''aperto',         '#00838F', 'mdi-run-fast',             1 FROM WP_TT_ARGOMENTI WHERE nome = 'SPORT' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Ciclismo',      id, 'Uscite in bici',                        '#00838F', 'mdi-bike',                 1 FROM WP_TT_ARGOMENTI WHERE nome = 'SPORT' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Nuoto',         id, 'Sessioni in piscina',                   '#00838F', 'mdi-swim',                 1 FROM WP_TT_ARGOMENTI WHERE nome = 'SPORT' AND id_argomento_padre IS NULL;

-- ------------------------------------------------------------
-- HOBBY → sottoaree
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, id_argomento_padre, descrizione, colore, icona, se_personale)
SELECT 'Lettura',       id, 'Libri e articoli',                      '#6A1B9A', 'mdi-book-open-page-variant', 1 FROM WP_TT_ARGOMENTI WHERE nome = 'HOBBY' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Fotografia',    id, 'Scatti e post-produzione',              '#6A1B9A', 'mdi-camera',               1 FROM WP_TT_ARGOMENTI WHERE nome = 'HOBBY' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Musica',        id, 'Suonare e ascolto',                     '#6A1B9A', 'mdi-music',                1 FROM WP_TT_ARGOMENTI WHERE nome = 'HOBBY' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Videogiochi',   id, 'Gaming e streaming',                   '#6A1B9A', 'mdi-controller-classic',   1 FROM WP_TT_ARGOMENTI WHERE nome = 'HOBBY' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Cucito',        id, 'Cucito, maglia e creatività',           '#6A1B9A', 'mdi-needle',               1 FROM WP_TT_ARGOMENTI WHERE nome = 'HOBBY' AND id_argomento_padre IS NULL;

-- ------------------------------------------------------------
-- SALUTE → sottoaree
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, id_argomento_padre, descrizione, colore, icona, se_personale)
SELECT 'Visite',        id, 'Visite mediche e specialistiche',       '#C62828', 'mdi-stethoscope',          1 FROM WP_TT_ARGOMENTI WHERE nome = 'SALUTE' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Farmaci',       id, 'Gestione terapie e farmaci',            '#C62828', 'mdi-pill',                 1 FROM WP_TT_ARGOMENTI WHERE nome = 'SALUTE' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Benessere',     id, 'Meditazione e cura personale',          '#C62828', 'mdi-spa',                  1 FROM WP_TT_ARGOMENTI WHERE nome = 'SALUTE' AND id_argomento_padre IS NULL;

-- ------------------------------------------------------------
-- FAMIGLIA → sottoaree
-- ------------------------------------------------------------
INSERT INTO WP_TT_ARGOMENTI (nome, id_argomento_padre, descrizione, colore, icona, se_personale)
SELECT 'Figli',         id, 'Attività con i figli',                  '#F57F17', 'mdi-baby-face',            1 FROM WP_TT_ARGOMENTI WHERE nome = 'FAMIGLIA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Genitori',      id, 'Supporto a genitori e parenti',         '#F57F17', 'mdi-human-male-female',    1 FROM WP_TT_ARGOMENTI WHERE nome = 'FAMIGLIA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Animali',       id, 'Cura degli animali domestici',          '#F57F17', 'mdi-paw',                  1 FROM WP_TT_ARGOMENTI WHERE nome = 'FAMIGLIA' AND id_argomento_padre IS NULL UNION ALL
SELECT 'Viaggi',        id, 'Pianificazione e vacanze in famiglia',  '#F57F17', 'mdi-airplane',             1 FROM WP_TT_ARGOMENTI WHERE nome = 'FAMIGLIA' AND id_argomento_padre IS NULL;
