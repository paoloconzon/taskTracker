<?php
// ============================================================
// helpers.php  –  Risposta JSON, sessioni, validazione
// ============================================================

function ok($data = [], string $message = 'ok'): void {
    echo json_encode(['result' => 0, 'message' => $message, 'data' => $data]);
    exit;
}

function err(string $message, int $code = 1): void {
    echo json_encode(['result' => $code, 'message' => $message, 'data' => new stdClass()]);
    exit;
}

function warn(string $message, $data = []): void {
    echo json_encode(['result' => -1, 'message' => $message, 'data' => $data]);
    exit;
}

// -----------------------------------------------------------
// Genera sessionId univoco
function generateSessionId(): string {
    return bin2hex(random_bytes(25)); // 50 char hex
}

// -----------------------------------------------------------
// Valida la sessione nel payload e aggiorna ultima_call
// Ritorna ['user'=>..., 'idUtente'=>...]
function requireSession(array $payload): array {
    $sessionId = $payload['sessionId'] ?? '';
    if (!$sessionId) err('sessionId mancante', 2);

    $db  = getDB();
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare(
        'SELECT s.sessionId, s.user, u.id as idUtente, u.gruppo
           FROM WP_TT_SESSIONI s
           JOIN  WP_TT_UTENTI u ON u.user = s.user
          WHERE s.sessionId = ? AND s.se_attivo = 1'
    );
    $stmt->execute([$sessionId]);
    $sess = $stmt->fetch();

    if (!$sess) err('Sessione non valida o scaduta', 3);

    // Controlla TTL
    if (SESSION_TTL_MIN > 0) {
        $stmtTTL = $db->prepare(
            'SELECT data_ora_ultima_call FROM  WP_TT_SESSIONI WHERE sessionId = ?'
        );
        $stmtTTL->execute([$sessionId]);
        $row = $stmtTTL->fetch();
        $lastCall = strtotime($row['data_ora_ultima_call']);
        if ((time() - $lastCall) > SESSION_TTL_MIN * 60) {
            $db->prepare('UPDATE WP_TT_SESSIONI SET se_attivo=0 WHERE sessionId=?')
               ->execute([$sessionId]);
            err('Sessione scaduta, effettuare nuovamente il login', 3);
        }
    }

    // Aggiorna ultima chiamata
    $db->prepare('UPDATE  WP_TT_SESSIONI SET data_ora_ultima_call=? WHERE sessionId=?')
       ->execute([$now, $sessionId]);

    return ['user' => $sess['user'], 'idUtente' => (int)$sess['idUtente'], 'gruppo' => $sess['gruppo'] ?? ''];
}

// -----------------------------------------------------------
// Converte datetime DB (Y-m-d H:i:s) <-> frontend (YYYYMMDD HHNNSS)
function toFront(?string $dt): ?string {
    if (!$dt) return null;
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $dt);
    return $d ? $d->format('Ymd His') : $dt;
}

function fromFront(?string $dt): ?string {
    if (!$dt) return null;
    // Accetta "YYYYMMDD HHNNSS" oppure già ISO
    $dt = trim($dt);
    $d  = DateTime::createFromFormat('Ymd His', $dt)
       ?: DateTime::createFromFormat('Y-m-d H:i:s', $dt)
       ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $dt);
    return $d ? $d->format('Y-m-d H:i:s') : null;
}
