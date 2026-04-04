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
    $stmt = $db->prepare('SELECT id, pwd FROM WP_TT_UTENTI WHERE user = ?');
    $stmt->execute([$user]);
    $row  = $stmt->fetch();

    //if (!$row || !password_verify($pwd, $row['pwd'])) err('Credenziali non valide', 5);
    if (!$row || !($pwd==$row['pwd'])) err('Credenziali non valide', 5);

    // Disattiva sessioni precedenti
    $db->prepare('UPDATE WP_TT_SESSIONI SET se_attivo=0 WHERE user=?')->execute([$user]);

    $sessionId = generateSessionId();
    $now       = date('Y-m-d H:i:s');
    $db->prepare(
        'INSERT INTO WP_TT_SESSIONI (sessionId,user,data_ora_inizio,data_ora_ultima_call,se_attivo)
         VALUES (?,?,?,?,1)'
    )->execute([$sessionId, $user, $now, $now]);

    ok(['sessionId' => $sessionId, 'idUtente' => (int)$row['id']]);
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
        $where[] = '(t.descrizione LIKE ? OR arg.nome LIKE ?)';
        $t = '%'.$f['testo'].'%';
        $bind[] = $t; $bind[] = $t;
    }

    $sql = 'SELECT t.*,
                   arg.nome      as argomento_nome,
                   arg.colore    as argomento_colore,
                   arg.icona     as argomento_icona,
                   az.nome       as azione_nome,
                   -- percorso albero
                   (SELECT GROUP_CONCAT(a2.nome ORDER BY a2.id SEPARATOR "/")
                    FROM WP_TT_ARGOMENTI a2
                    WHERE a2.id IN (
                        SELECT id FROM (
                          WITH RECURSIVE anc(id,id_argomento_padre) AS (
                            SELECT id,id_argomento_padre FROM WP_TT_ARGOMENTI WHERE id=arg.id
                            UNION ALL
                            SELECT a3.id,a3.id_argomento_padre FROM WP_TT_ARGOMENTI a3 JOIN anc ON a3.id=anc.id_argomento_padre
                          ) SELECT id FROM anc
                        ) ids
                    )
                   ) as argomento_path,
                   -- ultimo log aperto
                   (SELECT tl.data_ora_inizio FROM WP_TT_TASK_LOG tl WHERE tl.id_task=t.id ORDER BY tl.id DESC LIMIT 1) as ultimo_inizio,
                   -- somma minuti
                   (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.data_ora_inizio, IFNULL(tl.data_ora_fine,NOW())))
                    FROM WP_TT_TASK_LOG tl WHERE tl.id_task=t.id) as secondi_totali
              FROM WP_TT_TASK t
              JOIN WP_TT_ARGOMENTI arg ON arg.id=t.id_argomento
              LEFT JOIN WP_TT_AZIONE az  ON az.id=t.id_azione
             WHERE '.implode(' AND ', $where).'
             ORDER BY t.id DESC
             LIMIT 200';

    $stmt = $db->prepare($sql);
    $stmt->execute($bind);
    ok(['elenco' => $stmt->fetchAll()]);
}

function getTaskLog(array $f, array $sess): void {
    $db    = getDB();
    $where = ['t.id_utente = ?'];
    $bind  = [$sess['idUtente']];

    $daData = $f['daData'] ?? date('Ymd');
    $aData  = $f['aData']  ?? date('Ymd');

    $where[] = 'DATE(tl.data_ora_inizio) >= ?';
    $bind[]  = DateTime::createFromFormat('Ymd', $daData)->format('Y-m-d');
    $where[] = 'DATE(tl.data_ora_inizio) <= ?';
    $bind[]  = DateTime::createFromFormat('Ymd', $aData)->format('Y-m-d');

    if (!empty($f['testo'])) {
        $where[] = '(tl.descrizione LIKE ? OR tl.note LIKE ? OR arg.nome LIKE ? OR az.nome LIKE ?)';
        $t = '%'.$f['testo'].'%';
        $bind[] = $t; $bind[] = $t; $bind[] = $t; $bind[] = $t;
    }

    $sql = 'SELECT tl.*,
                   t.id_argomento,
                   t.se_chiuso as task_chiuso,
                   arg.nome   as argomento_nome,
                   arg.colore as argomento_colore,
                   arg.icona  as argomento_icona,
                   arg.se_pausa,
                   az.nome    as azione_nome,
                   TIMESTAMPDIFF(SECOND, tl.data_ora_inizio, IFNULL(tl.data_ora_fine, NOW())) as secondi,
                   -- path argomento
                   (SELECT GROUP_CONCAT(a2.nome ORDER BY CHAR_LENGTH(a2.nome) SEPARATOR "/")
                    FROM WP_TT_ARGOMENTI a2
                    WHERE a2.id = arg.id OR a2.id = arg.id_argomento_padre
                   ) as argomento_path
              FROM WP_TT_TASK_LOG tl
              JOIN WP_TT_TASK t      ON t.id = tl.id_task
              JOIN WP_TT_ARGOMENTI arg ON arg.id = t.id_argomento
              LEFT JOIN WP_TT_AZIONE az ON az.id = t.id_azione
             WHERE '.implode(' AND ', $where).'
             ORDER BY tl.data_ora_inizio DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll();

    // Calcola totale secondi
    $totaleSecondi = array_sum(array_column($rows, 'secondi'));

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
                                   se_chiuso=?,flag1=?,flag2=?,flag3=?
              WHERE id=?'
        )->execute([
            $v['nome'], $v['id_argomento_padre'] ?: null, $v['descrizione'] ?? null,
            $v['colore'] ?? '#607D8B', $v['icona'] ?? 'mdi-folder',
            $v['se_chiuso'] ? 1 : 0,
            $v['flag1'] ?? null, $v['flag2'] ?? null, $v['flag3'] ?? null,
            $v['id']
        ]);
        ok(['id' => (int)$v['id']]);
    } else {
        $db->prepare(
            'INSERT INTO WP_TT_ARGOMENTI (nome,id_argomento_padre,descrizione,colore,icona,se_chiuso,flag1,flag2,flag3)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $v['nome'], $v['id_argomento_padre'] ?: null, $v['descrizione'] ?? null,
            $v['colore'] ?? '#607D8B', $v['icona'] ?? 'mdi-folder',
            $v['se_chiuso'] ? 1 : 0,
            $v['flag1'] ?? null, $v['flag2'] ?? null, $v['flag3'] ?? null,
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
            'UPDATE WP_TT_TASK SET id_argomento=?,id_azione=?,se_chiuso=?,descrizione=?,flag1=?,flag2=?,flag3=?
              WHERE id=? AND id_utente=?'
        )->execute([
            $v['id_argomento'], $v['id_azione'] ?: null,
            $v['se_chiuso'] ? 1 : 0, $v['descrizione'] ?? null,
            $v['flag1'] ?? null, $v['flag2'] ?? null, $v['flag3'] ?? null,
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
            'INSERT INTO WP_TT_TASK (id_utente,id_argomento,id_azione,se_chiuso,descrizione,flag1,flag2,flag3)
             VALUES (?,?,?,0,?,?,?,?)'
        )->execute([
            $sess['idUtente'], $v['id_argomento'], $v['id_azione'] ?: null,
            $v['descrizione'] ?? null,
            $v['flag1'] ?? null, $v['flag2'] ?? null, $v['flag3'] ?? null,
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

function doFermaLog(array $sess): void {
    $db  = getDB();
    $now = date('Y-m-d H:i:s');
    chiudiTaskAttivo($sess['idUtente'], $db, $now);
    ok();
}
