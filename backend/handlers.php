<?php
// ============================================================
// handlers.php  –  Logica per ogni func
// ============================================================

// ============================================================
// LOGIN
// ============================================================
function handleLogin(array $data): void {
    $user = trim($data['user'] ?? '');
    $pwd  = $data['pwd'] ?? '';
    if (!$user || !$pwd) err('Credenziali mancanti');

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, pwd, descrizione, gruppo FROM WP_TT_UTENTI WHERE user = ?');
    $stmt->execute([$user]);
    $row  = $stmt->fetch();

    if (!$row || !((password_verify($pwd, $row['pwd']))||($pwd==$row['pwd']))) err('Credenziali non valide', 5);

    // Disattiva sessioni precedenti
    $db->prepare('UPDATE WP_TT_SESSIONI SET se_attivo=0 WHERE user=?')->execute([$user]);

    $sessionId = generateSessionId();
    $now       = date('Y-m-d H:i:s');
    $db->prepare(
        'INSERT INTO WP_TT_SESSIONI (sessionId,user,data_ora_inizio,data_ora_ultima_call,se_attivo)
         VALUES (?,?,?,?,1)'
    )->execute([$sessionId, $user, $now, $now]);

    ok(['sessionId' => $sessionId, 'idUtente' => (int)$row['id'], 'descrizione' => $row['descrizione'], 'gruppo' => $row['gruppo']]);
}

// ============================================================
// LOGOUT
// ============================================================
function handleLogout(array $payload): void {
    $sessionId = $payload['sessionId'] ?? '';
    if ($sessionId) {
        getDB()->prepare('UPDATE WP_TT_SESSIONI SET se_attivo=0 WHERE sessionId=?')
               ->execute([$sessionId]);
    }
    ok();
}

// ============================================================
// GET  –  dispatcher per tabella
// ============================================================
function handleGet(array $payload): void {
    $sess = requireSession($payload);
    $data = $payload['data'] ?? [];
    $tab  = strtolower($data['tab'] ?? '');
    $f    = $data['filtro'] ?? [];

    switch ($tab) {
        case 'argomenti':   getArgomenti($f, $sess);   break;
        case 'azioni':      getAzioni($sess);           break;
        case 'task':        getTask($f, $sess);         break;
        case 'task_log':    getTaskLog($f, $sess);      break;
        case 'task_attivo': getTaskAttivo($sess);       break;
        case 'utenti':      getUtenti($sess);           break;
        default: err("Tabella '$tab' non supportata");
    }
}

function getArgomenti(array $f, array $sess): void {
    $db    = getDB();
    $where = ['1=1'];
    $bind  = [];

    if (!empty($f['idPadre'])) {
        $where[] = 'a.id_argomento_padre = ?';
        $bind[]  = $f['idPadre'];
    } elseif (array_key_exists('idPadre', $f) && $f['idPadre'] === null) {
        $where[] = 'a.id_argomento_padre IS NULL';
    }

    if (!isset($f['seMostraChiusi']) || !$f['seMostraChiusi']) {
        $where[] = 'a.se_chiuso = 0';
    }

    // Visibili solo gli argomenti propri o condivisi (id_utente NULL)
    $where[] = '(a.id_utente IS NULL OR a.id_utente = ?)';
    $bind[]  = $sess['idUtente'];
    if (!empty($f['testo'])) {
        $where[] = '(a.nome LIKE ? OR a.descrizione LIKE ?)';
        $t = '%'.$f['testo'].'%';
        $bind[] = $t; $bind[] = $t;
    }

    $sql = 'SELECT a.*, 
                   (SELECT COUNT(*) FROM WP_TT_ARGOMENTI c WHERE c.id_argomento_padre=a.id AND c.se_chiuso=0) as num_figli
              FROM WP_TT_ARGOMENTI a
             WHERE '.implode(' AND ', $where).'
             ORDER BY a.se_pausa DESC, a.nome';

    $stmt = $db->prepare($sql);
    $stmt->execute($bind);
    ok(['elenco' => $stmt->fetchAll()]);
}

function getAzioni(array $sess): void {
    $rows = getDB()->query('SELECT * FROM WP_TT_AZIONE ORDER BY nome')->fetchAll();
    ok(['elenco' => $rows]);
}

function getUtenti(array $sess): void {
    if ($sess['gruppo'] !== 'admin') err('Non autorizzato');
    $rows = getDB()->query('SELECT id, user, descrizione FROM WP_TT_UTENTI ORDER BY descrizione')->fetchAll();
    ok(['elenco' => $rows]);
}

function getTask(array $f, array $sess): void {
    $db    = getDB();
    $where = ['t.id_utente = ?'];
    $bind  = [$sess['idUtente']];

    if (!isset($f['seMostraChiusi']) || !$f['seMostraChiusi']) {
        $where[] = 't.se_chiuso = 0';
    }
    // Escludi pausa
    $where[] = 'arg.se_pausa = 0';

    if (!empty($f['testo'])) {
        $where[] = '(t.descrizione LIKE ? OR arg.nome LIKE ? OR p1.nome LIKE ? OR p2.nome LIKE ?)';
        $t = '%'.$f['testo'].'%';
        $bind[] = $t; $bind[] = $t; $bind[] = $t; $bind[] = $t;
    }

    if (!empty($f['daData'])) {
        $da      = DateTime::createFromFormat('Ymd', $f['daData'])->format('Y-m-d');
        $where[] = 'EXISTS (SELECT 1 FROM WP_TT_TASK_LOG tl2 WHERE tl2.id_task = t.id AND DATE(tl2.data_ora_inizio) >= ?)';
        $bind[]  = $da;
    }
    if (!empty($f['aData'])) {
        $a       = DateTime::createFromFormat('Ymd', $f['aData'])->format('Y-m-d');
        $where[] = 'EXISTS (SELECT 1 FROM WP_TT_TASK_LOG tl2 WHERE tl2.id_task = t.id AND DATE(tl2.data_ora_inizio) <= ?)';
        $bind[]  = $a;
    }

    // JOIN espliciti su 3 livelli: il path NONNO/PADRE/FIGLIO viene composto in PHP
    // (evita CTE ricorsive dentro subquery scalari che MySQL non supporta correttamente)
    $sql = 'SELECT t.*,
                   arg.nome               AS argomento_nome,
                   arg.colore             AS argomento_colore,
                   arg.icona              AS argomento_icona,
                   arg.id_argomento_padre AS arg_id_padre,
                   p1.nome                AS arg_padre_nome,
                   p1.id_argomento_padre  AS arg_id_nonno,
                   p2.nome                AS arg_nonno_nome,
                   az.nome                AS azione_nome,
                   (SELECT tl.data_ora_inizio
                      FROM WP_TT_TASK_LOG tl
                     WHERE tl.id_task = t.id
                     ORDER BY tl.id DESC LIMIT 1)                   AS ultimo_inizio,
                   (SELECT SUM(TIMESTAMPDIFF(SECOND,
                               tl.data_ora_inizio, IFNULL(tl.data_ora_fine,NOW())))
                      FROM WP_TT_TASK_LOG tl
                     WHERE tl.id_task = t.id)                       AS secondi_totali
              FROM WP_TT_TASK t
              JOIN WP_TT_ARGOMENTI arg ON arg.id  = t.id_argomento
              LEFT JOIN WP_TT_ARGOMENTI p1  ON p1.id   = arg.id_argomento_padre
              LEFT JOIN WP_TT_ARGOMENTI p2  ON p2.id   = p1.id_argomento_padre
              LEFT JOIN WP_TT_AZIONE    az  ON az.id    = t.id_azione
             WHERE '.implode(' AND ', $where).'
             ORDER BY t.id DESC
             LIMIT 200';

    $stmt = $db->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll();

    // Costruisce argomento_path: NONNO / PADRE / FIGLIO
    foreach ($rows as &$row) {
        $parts = array_filter([
            $row['arg_nonno_nome'],
            $row['arg_padre_nome'],
            $row['argomento_nome'],
        ]);
        $row['argomento_path'] = implode(' / ', $parts);
        unset($row['arg_id_padre'], $row['arg_id_nonno'],
              $row['arg_padre_nome'], $row['arg_nonno_nome']);
        $row['ultimo_inizio'] = toFront($row['ultimo_inizio']);
    }

    ok(['elenco' => $rows]);
}

function getTaskLog(array $f, array $sess): void {
    $db    = getDB();
    $isAdmin = ($sess['gruppo'] === 'admin');
    if ($isAdmin) {
        $where = ['1=1'];
        $bind  = [];
    } else {
        $where = ['t.id_utente = ?'];
        $bind  = [$sess['idUtente']];
    }

    $daData = $f['daData'] ?? date('Ymd');
    $aData  = $f['aData']  ?? date('Ymd');

    $where[] = 'DATE(tl.data_ora_inizio) >= ?';
    $bind[]  = DateTime::createFromFormat('Ymd', $daData)->format('Y-m-d');
    $where[] = 'DATE(tl.data_ora_inizio) <= ?';
    $bind[]  = DateTime::createFromFormat('Ymd', $aData)->format('Y-m-d');

    // Filtro per utente specifico (solo admin)
    if ($isAdmin && !empty($f['idUtente'])) {
        $where[] = 't.id_utente = ?';
        $bind[]  = (int)$f['idUtente'];
    }

    if (!empty($f['testo'])) {
        $where[] = '(tl.descrizione LIKE ? OR tl.note LIKE ? OR arg.nome LIKE ? OR p1.nome LIKE ? OR p2.nome LIKE ? OR az.nome LIKE ?)';
        $t = '%'.$f['testo'].'%';
        $bind[] = $t; $bind[] = $t; $bind[] = $t; $bind[] = $t; $bind[] = $t; $bind[] = $t;
    }

    $sql = 'SELECT tl.*,
                   t.id_argomento,
                   t.id_utente  as id_utente_task,
                   t.se_chiuso as task_chiuso,
                   t.mantis     as task_mantis,
                   t.ticket     as task_ticket,
                   arg.nome   as argomento_nome,
                   arg.colore as argomento_colore,
                   arg.icona  as argomento_icona,
                   arg.se_pausa,
                   p1.nome    as arg_padre_nome,
                   p2.nome    as arg_nonno_nome,
                   az.nome    as azione_nome,
                   u.descrizione as utente_descrizione,
                   TIMESTAMPDIFF(SECOND, tl.data_ora_inizio, IFNULL(tl.data_ora_fine, NOW())) as secondi,
                   -- path argomento
                   (SELECT GROUP_CONCAT(a2.nome ORDER BY CHAR_LENGTH(a2.nome) SEPARATOR "/")
                    FROM WP_TT_ARGOMENTI a2
                    WHERE a2.id = arg.id OR a2.id = arg.id_argomento_padre
                   ) as argomento_path
              FROM WP_TT_TASK_LOG tl
              JOIN WP_TT_TASK t      ON t.id = tl.id_task
              JOIN WP_TT_ARGOMENTI arg ON arg.id = t.id_argomento
              LEFT JOIN WP_TT_ARGOMENTI p1 ON p1.id = arg.id_argomento_padre
              LEFT JOIN WP_TT_ARGOMENTI p2 ON p2.id = p1.id_argomento_padre
              LEFT JOIN WP_TT_AZIONE az ON az.id = t.id_azione
              JOIN WP_TT_UTENTI u ON u.id = t.id_utente
             WHERE '.implode(' AND ', $where).'
             ORDER BY tl.data_ora_inizio DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll();

    // Calcola totale secondi escludendo le pause
    $totaleSecondi = array_sum(array_map(
        fn($r) => $r['se_pausa'] ? 0 : (int)$r['secondi'],
        $rows
    ));

    // Formatta date per frontend
    foreach ($rows as &$r) {
        $r['data_ora_inizio'] = toFront($r['data_ora_inizio']);
        $r['data_ora_fine']   = toFront($r['data_ora_fine']);
    }

    ok(['elenco' => $rows, 'totaleSecondi' => $totaleSecondi]);
}

function getTaskAttivo(array $sess): void {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT t.*,
                arg.nome   as argomento_nome,
                arg.colore as argomento_colore,
                arg.icona  as argomento_icona,
                arg.se_pausa,
                az.nome    as azione_nome,
                tl.id      as log_id,
                tl.data_ora_inizio as log_inizio,
                tl.descrizione     as log_descrizione,
                tl.note            as log_note
           FROM WP_TT_TASK t
           JOIN WP_TT_ARGOMENTI arg ON arg.id = t.id_argomento
           LEFT JOIN WP_TT_AZIONE az ON az.id = t.id_azione
           JOIN WP_TT_TASK_LOG tl ON tl.id_task = t.id AND tl.data_ora_fine IS NULL
          WHERE t.id_utente = ? AND t.se_chiuso = 0
          ORDER BY tl.id DESC
          LIMIT 1'
    );
    $stmt->execute([$sess['idUtente']]);
    $task = $stmt->fetch();

    if ($task) {
        $task['log_inizio'] = toFront($task['log_inizio']);
    }
    ok(['task' => $task ?: null]);
}

// ============================================================
// PUT  –  inserisce o aggiorna un record
// ============================================================
function handlePut(array $payload): void {
    $sess   = requireSession($payload);
    $data   = $payload['data'] ?? [];
    $tab    = strtolower($data['tab'] ?? '');
    $valori = $data['valori'] ?? [];

    switch ($tab) {
        case 'argomenti': putArgomento($valori, $sess);  break;
        case 'azione':    putAzione($valori, $sess);     break;
        case 'task':      putTask($valori, $sess);       break;
        case 'task_log':  putTaskLog($valori, $sess);    break;
        default: err("Tabella '$tab' non supportata per put");
    }
}

function putArgomento(array $v, array $sess): void {
    $db = getDB();
    if (!empty($v['id'])) {
        $db->prepare(
            'UPDATE WP_TT_ARGOMENTI SET nome=?,id_argomento_padre=?,descrizione=?,colore=?,icona=?,
                                   se_chiuso=?,se_personale=?,mantis=?,ticket=?,tags=?
              WHERE id=?'
        )->execute([
            $v['nome'], $v['id_argomento_padre'] ?: null, $v['descrizione'] ?? null,
            $v['colore'] ?? '#607D8B', $v['icona'] ?? 'mdi-folder',
            $v['se_chiuso'] ? 1 : 0,
            isset($v['se_personale']) ? ($v['se_personale'] ? 1 : 0) : 1,
            $v['mantis'] ?? null, $v['ticket'] ?? null, $v['tags'] ?? null,
            $v['id']
        ]);
        ok(['id' => (int)$v['id']]);
    } else {
        $db->prepare(
            'INSERT INTO WP_TT_ARGOMENTI (id_utente,nome,id_argomento_padre,descrizione,colore,icona,se_chiuso,se_personale,mantis,ticket,tags)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $sess['idUtente'],
            $v['nome'], $v['id_argomento_padre'] ?: null, $v['descrizione'] ?? null,
            $v['colore'] ?? '#607D8B', $v['icona'] ?? 'mdi-folder',
            $v['se_chiuso'] ? 1 : 0,
            isset($v['se_personale']) ? ($v['se_personale'] ? 1 : 0) : 1,
            $v['mantis'] ?? null, $v['ticket'] ?? null, $v['tags'] ?? null,
        ]);
        ok(['id' => (int)$db->lastInsertId()]);
    }
}

function putAzione(array $v, array $sess): void {
    $db = getDB();
    if (!empty($v['id'])) {
        $db->prepare('UPDATE WP_TT_AZIONE SET nome=? WHERE id=?')->execute([$v['nome'], $v['id']]);
        ok(['id' => (int)$v['id']]);
    } else {
        $db->prepare('INSERT INTO WP_TT_AZIONE (nome) VALUES (?)')->execute([$v['nome']]);
        ok(['id' => (int)$db->lastInsertId()]);
    }
}

function putTask(array $v, array $sess): void {
    $db  = getDB();
    $now = date('Y-m-d H:i:s');

    if (!empty($v['id'])) {
        // Aggiornamento task esistente
        $db->prepare(
            'UPDATE WP_TT_TASK SET id_argomento=?,id_azione=?,se_chiuso=?,descrizione=?,mantis=?,ticket=?,tags=?
              WHERE id=? AND id_utente=?'
        )->execute([
            $v['id_argomento'], $v['id_azione'] ?: null,
            $v['se_chiuso'] ? 1 : 0, $v['descrizione'] ?? null,
            $v['mantis'] ?? null, $v['ticket'] ?? null, $v['tags'] ?? null,
            $v['id'], $sess['idUtente']
        ]);

        // Se chiude il task, chiude anche il log aperto
        if (!empty($v['se_chiuso'])) {
            $db->prepare(
                'UPDATE WP_TT_TASK_LOG SET data_ora_fine=? WHERE id_task=? AND data_ora_fine IS NULL'
            )->execute([$now, $v['id']]);
        }
        ok(['id' => (int)$v['id']]);
    } else {
        // Nuovo task: chiude prima il task attivo
        chiudiTaskAttivo($sess['idUtente'], $db, $now);

        $db->prepare(
            'INSERT INTO WP_TT_TASK (id_utente,id_argomento,id_azione,se_chiuso,descrizione,mantis,ticket,tags)
             VALUES (?,?,?,0,?,?,?,?)'
        )->execute([
            $sess['idUtente'], $v['id_argomento'], $v['id_azione'] ?: null,
            $v['descrizione'] ?? null,
            $v['mantis'] ?? null, $v['ticket'] ?? null, $v['tags'] ?? null,
        ]);
        $taskId = (int)$db->lastInsertId();

        // Crea il primo log
        $db->prepare(
            'INSERT INTO WP_TT_TASK_LOG (id_task,descrizione,data_ora_inizio)
             VALUES (?,?,?)'
        )->execute([$taskId, $v['descrizione'] ?? null, $now]);

        ok(['id' => $taskId, 'logInizio' => toFront($now)]);
    }
}

function putTaskLog(array $v, array $sess): void {
    $db  = getDB();
    $now = date('Y-m-d H:i:s');

    $inizio = fromFront($v['data_ora_inizio'] ?? null) ?? $now;
    $fine   = fromFront($v['data_ora_fine']   ?? null);

    if (!empty($v['id'])) {
        $db->prepare(
            'UPDATE WP_TT_TASK_LOG SET descrizione=?,data_ora_inizio=?,data_ora_fine=?,note=?
              WHERE id=?'
        )->execute([$v['descrizione'] ?? null, $inizio, $fine, $v['note'] ?? null, $v['id']]);
        ok(['id' => (int)$v['id']]);
    } else {
        $db->prepare(
            'INSERT INTO WP_TT_TASK_LOG (id_task,descrizione,data_ora_inizio,data_ora_fine,note)
             VALUES (?,?,?,?,?)'
        )->execute([$v['id_task'], $v['descrizione'] ?? null, $inizio, $fine, $v['note'] ?? null]);
        ok(['id' => (int)$db->lastInsertId()]);
    }
}

// ============================================================
// DEL  –  elimina un record
// ============================================================
function handleDel(array $payload): void {
    $sess = requireSession($payload);
    $data = $payload['data'] ?? [];
    $tab  = strtolower($data['tab'] ?? '');
    $id   = (int)($data['id'] ?? 0);
    if (!$id) err('id mancante');

    $db = getDB();
    switch ($tab) {
        case 'argomenti':
            // Controlla che non abbia figli o task
            $figli = $db->prepare('SELECT COUNT(*) as n FROM WP_TT_ARGOMENTI WHERE id_argomento_padre=?');
            $figli->execute([$id]);
            if ($figli->fetch()['n'] > 0) err('Impossibile eliminare: ha argomenti figli');
            $task = $db->prepare('SELECT COUNT(*) as n FROM WP_TT_TASK WHERE id_argomento=?');
            $task->execute([$id]);
            if ($task->fetch()['n'] > 0) err('Impossibile eliminare: ha task associati');
            $db->prepare('DELETE FROM WP_TT_ARGOMENTI WHERE id=? AND se_pausa=0')->execute([$id]);
            break;
        case 'azione':
            $db->prepare('DELETE FROM WP_TT_AZIONE WHERE id=?')->execute([$id]);
            break;
        case 'task_log':
            $db->prepare('DELETE FROM WP_TT_TASK_LOG WHERE id=?')->execute([$id]);
            break;
        default:
            err("Tabella '$tab' non supportata per del");
    }
    ok();
}

// ============================================================
// AZIONI SPECIALI
// ============================================================
function handleAction(array $payload): void {
    $sess   = requireSession($payload);
    $data   = $payload['data'] ?? [];
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'pausa':        doPausa($sess);                         break;
        case 'riprendi':     doRiprendi((int)($data['idTask']??0), $sess); break;
        case 'chiudi_task':  doChiudiTask((int)($data['idTask']??0), $sess); break;
        case 'ferma_log':    doFermaLog($sess);                      break;
        default: err("Action '$action' non riconosciuta");
    }
}

function chiudiTaskAttivo(int $idUtente, PDO $db, string $now): void {
    // Chiude il log aperto del task attivo
    $db->prepare(
        'UPDATE WP_TT_TASK_LOG tl
            JOIN WP_TT_TASK t ON t.id = tl.id_task
           SET tl.data_ora_fine = ?
         WHERE t.id_utente = ? AND t.se_chiuso = 0 AND tl.data_ora_fine IS NULL'
    )->execute([$now, $idUtente]);
}

function doPausa(array $sess): void {
    $db  = getDB();
    $now = date('Y-m-d H:i:s');
    chiudiTaskAttivo($sess['idUtente'], $db, $now);


    // Crea task PAUSA
    $db->prepare(
        'INSERT INTO WP_TT_TASK (id_utente,id_argomento,se_chiuso) VALUES (?,?,0)'
    )->execute([$sess['idUtente'], ARGOMENTO_PAUSA_ID]);
    $pausaId = (int)$db->lastInsertId();

    $db->prepare(
        'INSERT INTO WP_TT_TASK_LOG (id_task,data_ora_inizio) VALUES (?,?)'
    )->execute([$pausaId, $now]);

    ok(['idTaskPausa' => $pausaId]);
}

function doRiprendi(int $idTask, array $sess): void {
    if (!$idTask) err('idTask mancante');
    $db  = getDB();
    $now = date('Y-m-d H:i:s');
    chiudiTaskAttivo($sess['idUtente'], $db, $now);

    // Riapre il task
    $db->prepare('UPDATE WP_TT_TASK SET se_chiuso=0 WHERE id=? AND id_utente=?')
       ->execute([$idTask, $sess['idUtente']]);

    // Nuovo log
    $db->prepare('INSERT INTO WP_TT_TASK_LOG (id_task,data_ora_inizio) VALUES (?,?)')
       ->execute([$idTask, $now]);

    ok(['logInizio' => toFront($now)]);
}

function doChiudiTask(int $idTask, array $sess): void {
    if (!$idTask) err('idTask mancante');
    $db  = getDB();
    $now = date('Y-m-d H:i:s');

    $db->prepare(
        'UPDATE WP_TT_TASK_LOG SET data_ora_fine=? WHERE id_task=? AND data_ora_fine IS NULL'
    )->execute([$now, $idTask]);

    $db->prepare(
        'UPDATE WP_TT_TASK SET se_chiuso=1 WHERE id=? AND id_utente=?'
    )->execute([$idTask, $sess['idUtente']]);

    ok();
}

// ============================================================
// MANTIS IMPORT  (raw SOAP XML via curl → mc_issue_add)
// ============================================================
function buildMantisXml(string $user, string $pwd, string $issueId, string $testo, int $minuti): string {
    $esc = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                  xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:man="http://futureware.biz/mantisconnect">
  <soapenv:Header/>
  <soapenv:Body>
    <man:mc_issue_note_add soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
      <username xsi:type="xsd:string">{$esc($user)}</username>
      <password xsi:type="xsd:string">{$esc($pwd)}</password>
      <issue_id xsi:type="xsd:integer">{$esc($issueId)}</issue_id>
      <note xsi:type="man:IssueNoteData">
        <text xsi:type="xsd:string">{$esc($testo)}</text>
        <time_tracking xsi:type="xsd:integer">{$minuti}</time_tracking>
      </note>
    </man:mc_issue_note_add>
  </soapenv:Body>
</soapenv:Envelope>
XML;
}

function handleMantisImport(array $payload): void {
    $sess  = requireSession($payload);
    $data  = $payload['data'] ?? [];
    $righe = $data['righe']   ?? [];

    // Credenziali Mantis dell'utente
    $db   = getDB();
    $stmt = $db->prepare('SELECT mantis_user, mantis_pwd, mantis_wsdl FROM WP_TT_UTENTI WHERE id = ?');
    $stmt->execute([$sess['idUtente']]);
    $utente = $stmt->fetch();

    if (empty($utente['mantis_user'])) err('Utente Mantis non configurato nel profilo');
    if (empty($utente['mantis_pwd']))  err('Password Mantis non configurata nel profilo');

    // Ricava endpoint SOAP dall'URL WSDL (rimuove ?wsdl)
    $endpoint = preg_replace('/\?wsdl\s*$/i', '', trim($utente['mantis_wsdl'] ?? ''));
    if (!$endpoint) err('URL SOAP Mantis non configurato nel profilo');

    // Raggruppa per mantis, scarta righe senza mantis
    $gruppi = [];
    foreach ($righe as $r) {
        $mantisId = trim($r['mantis'] ?? '');
        if ($mantisId === '') continue;
        $gruppi[$mantisId][] = $r;
    }
    if (empty($gruppi)) err('Nessuna riga con il campo Mantis valorizzato');

    $risultati    = [];
    $idsEsportati = [];

    foreach ($gruppi as $mantisId => $rows) {
        $secondiTot  = 0;
        $blocchi     = [];

        foreach ($rows as $r) {
            $giorno  = (new DateTime($r['giorno']))->format('d/m/y');
            $tempo   = mantisFormatDurata((int)$r['secondi_totali']);
            $ticket  = trim($r['ticket'] ?? '');
            $riga2   = ($ticket ? "ticket n.$ticket" : "attivita n.{$r['id_task']}") . " - tempo $tempo";
            $desc    = trim(str_replace(["\r\n", "\r", "\n"], "\n", $r['descrizioni'] ?? ''));
            $blocchi[]   = "attività giorno $giorno\n$riga2\n$desc";
            $secondiTot += (int)$r['secondi_totali'];
        }

        $testo   = implode("\n\n", $blocchi);
        $minuti  = (int)round($secondiTot / 60);

        $xml = buildMantisXml(
            $utente['mantis_user'],
            $utente['mantis_pwd'],
            $mantisId,
            $testo,
            $minuti
        );

        try {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $xml,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: text/xml; charset=utf-8',
                    'SOAPAction: "mc_issue_note_add"',
                    'Content-Length: ' . strlen($xml),
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $risultati[$mantisId] = [
                    'ok'       => false,
                    'errore'   => "cURL error: $curlError",
                    'endpoint' => $endpoint,
                    'xml'      => $xml,
                    'response' => null,
                    'httpCode' => 0,
                ];
                continue;
            }

            // Estrae ID nota dalla risposta
            $noteId = null;
            if ($response && preg_match('/<(?:[^:>\s]+:)?return[^>]*>(\d+)</', $response, $m)) {
                $noteId = (int)$m[1];
            }

            $ok = ($httpCode >= 200 && $httpCode < 300);
            $risultati[$mantisId] = [
                'ok'       => $ok,
                'note_id'  => $noteId,
                'endpoint' => $endpoint,
                'xml'      => $xml,
                'response' => $response,
                'httpCode' => $httpCode,
            ];
            if (!$ok) continue;

            foreach ($rows as $r) {
                foreach (explode(',', $r['ids_log']) as $id) {
                    $idsEsportati[] = (int)$id;
                }
            }
        } catch (Throwable $e) {
            $risultati[$mantisId] = [
                'ok'       => false,
                'errore'   => $e->getMessage(),
                'endpoint' => $endpoint,
                'xml'      => $xml,
                'response' => null,
                'httpCode' => 0,
            ];
        }
    }

    // Marca come esportati solo i log inseriti con successo
    if ($idsEsportati) {
        $ph   = implode(',', array_fill(0, count($idsEsportati), '?'));
        $bind = array_merge($idsEsportati, [$sess['idUtente']]);
        $db->prepare(
            "UPDATE WP_TT_TASK_LOG tl
               JOIN WP_TT_TASK t ON t.id = tl.id_task
                SET tl.se_esportato_mantis = 1
              WHERE tl.id IN ($ph) AND t.id_utente = ?"
        )->execute($bind);
    }

    ok(['risultati' => $risultati]);
}

function mantisFormatDurata(int $sec): string {
    $h = (int)floor($sec / 3600);
    $m = (int)floor(($sec % 3600) / 60);
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}

// ============================================================
// MANTIS EXPORT
// ============================================================
function getMantisExport(array $f, array $sess): void {
    $db = getDB();

    $daData = $f['daData'] ?? date('Ymd', strtotime('-30 days'));
    $aData  = $f['aData']  ?? date('Ymd');
    $da     = DateTime::createFromFormat('Ymd', $daData)->format('Y-m-d');
    $a      = DateTime::createFromFormat('Ymd', $aData)->format('Y-m-d');

    $sql = 'SELECT
              DATE(tl.data_ora_inizio)                                              AS giorno,
              t.mantis,
              t.ticket,
              t.tags,
              tl.id_task,
              arg.nome                                                               AS argomento_nome,
              arg.colore                                                             AS argomento_colore,
              arg.icona                                                              AS argomento_icona,
              p1.nome                                                                AS arg_padre_nome,
              SUM(TIMESTAMPDIFF(SECOND, tl.data_ora_inizio,
                                IFNULL(tl.data_ora_fine, NOW())))                   AS secondi_totali,
              GROUP_CONCAT(DISTINCT tl.descrizione
                           ORDER BY tl.id SEPARATOR "\n")                           AS descrizioni,
              GROUP_CONCAT(tl.id ORDER BY tl.id SEPARATOR ",")                     AS ids_log
            FROM WP_TT_TASK_LOG tl
            JOIN WP_TT_TASK        t   ON t.id    = tl.id_task
            JOIN WP_TT_ARGOMENTI   arg ON arg.id  = t.id_argomento
            LEFT JOIN WP_TT_ARGOMENTI p1 ON p1.id = arg.id_argomento_padre
            WHERE tl.se_esportato_mantis = 0
              AND tl.data_ora_fine IS NOT NULL
              AND DATE(tl.data_ora_inizio) >= ?
              AND DATE(tl.data_ora_inizio) <= ?
              AND t.id_utente = ?
              AND arg.se_pausa = 0
            GROUP BY DATE(tl.data_ora_inizio), t.mantis, t.ticket, tl.id_task
            ORDER BY giorno DESC, t.mantis, t.ticket, tl.id_task';

    $stmt = $db->prepare($sql);
    $stmt->execute([$da, $a, $sess['idUtente']]);
    ok(['elenco' => $stmt->fetchAll()]);
}

function setEsportatoMantis(array $data, array $sess): void {
    $idsLog = array_map('intval', $data['idsLog'] ?? []);
    if (empty($idsLog)) err('Nessun record selezionato');

    $db           = getDB();
    $placeholders = implode(',', array_fill(0, count($idsLog), '?'));
    $bind         = array_merge($idsLog, [$sess['idUtente']]);

    $db->prepare(
        "UPDATE WP_TT_TASK_LOG tl
           JOIN WP_TT_TASK t ON t.id = tl.id_task
            SET tl.se_esportato_mantis = 1
          WHERE tl.id IN ($placeholders)
            AND t.id_utente = ?"
    )->execute($bind);

    ok();
}

// ============================================================
// PROFILO UTENTE  (GET + SAVE)
// ============================================================
function handleGetProfilo(array $payload): void {
    $sess = requireSession($payload);
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT mantis_user, mantis_wsdl FROM WP_TT_UTENTI WHERE id = ?'
    );
    $stmt->execute([$sess['idUtente']]);
    $row = $stmt->fetch();
    // mantis_pwd non viene mai restituita al frontend
    ok([
        'mantis_user' => $row['mantis_user'] ?? '',
        'mantis_wsdl' => $row['mantis_wsdl'] ?? '',
    ]);
}

function handleSaveProfilo(array $payload): void {
    $sess = requireSession($payload);
    $data = $payload['data'] ?? [];

    $db   = getDB();
    $sets = [];
    $bind = [];

    if (array_key_exists('mantis_user', $data)) {
        $sets[] = 'mantis_user = ?';
        $bind[] = $data['mantis_user'] ?: null;
    }
    if (array_key_exists('mantis_wsdl', $data)) {
        $sets[] = 'mantis_wsdl = ?';
        $bind[] = $data['mantis_wsdl'] ?: null;
    }
    // Aggiorna mantis_pwd solo se esplicitamente fornita
    if (!empty($data['mantis_pwd'])) {
        $sets[] = 'mantis_pwd = ?';
        $bind[] = $data['mantis_pwd'];
    }

    if ($sets) {
        $bind[] = $sess['idUtente'];
        $db->prepare('UPDATE WP_TT_UTENTI SET '.implode(', ', $sets).' WHERE id = ?')
           ->execute($bind);
    }
    ok();
}

// ============================================================
// CAMBIO PASSWORD
// ============================================================
function handleCambioPassword(array $payload): void {
    $sess    = requireSession($payload);
    $data    = $payload['data'] ?? [];
    $vecchia = $data['vecchia'] ?? '';
    $nuova   = $data['nuova']   ?? '';

    if (!$vecchia || !$nuova) err('Compilare tutti i campi');
    if (strlen($nuova) < 6)   err('La nuova password deve essere di almeno 6 caratteri');

    $db   = getDB();
    $stmt = $db->prepare('SELECT pwd FROM WP_TT_UTENTI WHERE id = ?');
    $stmt->execute([$sess['idUtente']]);
    $row  = $stmt->fetch();

    if (!$row || !(password_verify($vecchia, $row['pwd']) || $vecchia === $row['pwd'])) {
        err('Password attuale non corretta');
    }

    $hash = password_hash($nuova, PASSWORD_DEFAULT);
    $db->prepare('UPDATE WP_TT_UTENTI SET pwd = ? WHERE id = ?')
       ->execute([$hash, $sess['idUtente']]);

    ok();
}

function doFermaLog(array $sess): void {
    $db  = getDB();
    $now = date('Y-m-d H:i:s');
    chiudiTaskAttivo($sess['idUtente'], $db, $now);
    ok();
}
