<?php
// ============================================================
// index.php  –  Unico entry point POST
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['result'=>1,'message'=>'Solo POST','data'=>new stdClass()]); exit; }

require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/handlers.php';

// Leggi payload JSON
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!$payload || !isset($payload['func'])) {
    echo json_encode(['result'=>1,'message'=>'Payload JSON non valido o func mancante','data'=>new stdClass()]);
    exit;
}

$func = strtolower(trim($payload['func']));

try {
    switch ($func) {
        case 'login':   handleLogin($payload['data'] ?? []);  break;
        case 'logout':  handleLogout($payload);               break;
        case 'get':     handleGet($payload);                  break;
        case 'put':     handlePut($payload);                  break;
        case 'del':     handleDel($payload);                  break;
        case 'action':          handleAction($payload);         break;
        case 'cambio_password': handleCambioPassword($payload); break;
        case 'mantis_import':      handleMantisImport($payload);                                                break;
        case 'get_mantis_export':  getMantisExport($payload['data']['filtro'] ?? [], requireSession($payload)); break;
        case 'set_esportato_mantis': setEsportatoMantis($payload['data'] ?? [], requireSession($payload));   break;
        case 'get_profilo':     handleGetProfilo($payload);     break;
        case 'save_profilo':    handleSaveProfilo($payload);    break;
        default:
            echo json_encode(['result'=>1,'message'=>"Funzione '$func' non riconosciuta",'data'=>new stdClass()]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['result'=>99,'message'=>'Errore server: '.$e->getMessage(),'data'=>new stdClass()]);
}
