<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_message_batch_status.php';

require_admin_auth();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!message_queue_tables_ready()) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'queue_unavailable',
        'message' => 'A fila de mensagens ainda nao esta pronta no banco.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

$publicId = trim((string) ($_GET['public_id'] ?? ''));
$batchId = (int) ($_GET['id'] ?? 0);
$hasExplicitLookup = $publicId !== '' || $batchId > 0;
$payload = [];

if ($publicId !== '') {
    $payload = admin_message_batch_progress_from_public_id($publicId);
} elseif ($batchId > 0) {
    $payload = admin_message_batch_progress_from_batch_id($batchId);
} else {
    $payload = admin_message_batch_progress_from_last_session();
}

if ($payload === []) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'error' => 'batch_not_found',
        'message' => $hasExplicitLookup
            ? 'Lote nao encontrado.'
            : 'Nenhum lote recente para acompanhar.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

$_SESSION['admin_message_last_batch_public_id'] = (string) ($payload['public_id'] ?? '');

echo json_encode([
    'ok' => true,
    'batch' => $payload,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
