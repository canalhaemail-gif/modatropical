<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo nao permitido.']);
    exit;
}

if (!pagbank_is_enabled()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'PagBank desabilitado.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$rawBody = is_string($rawBody) ? trim($rawBody) : '';
$contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));

$notificationCode = trim((string) ($_POST['notificationCode'] ?? $_GET['notificationCode'] ?? ''));
$notificationType = trim((string) ($_POST['notificationType'] ?? $_GET['notificationType'] ?? ''));

if ($notificationCode !== '') {
    pagbank_log_webhook('Codigo legado de notificacao recebido.', [
        'notification_code' => $notificationCode,
        'notification_type' => $notificationType,
        'post' => $_POST,
    ]);

    http_response_code(202);
    echo json_encode([
        'ok' => true,
        'message' => 'Codigo de notificacao recebido.',
        'notification_code' => $notificationCode,
    ]);
    exit;
}

if ($rawBody === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payload vazio.']);
    exit;
}

$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    pagbank_log_webhook('Payload invalido.', ['raw' => substr($rawBody, 0, 500)]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payload JSON invalido.']);
    exit;
}

$signature = pagbank_signature_header($_SERVER);

if ($signature !== '' && !pagbank_validate_webhook_signature($rawBody, $_SERVER)) {
    pagbank_log_webhook('Assinatura invalida.', ['signature' => $signature]);
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Assinatura invalida.']);
    exit;
}

$paymentData = pagbank_extract_webhook_payment_data($payload, $rawBody);
$order = pagbank_find_order_by_payment_reference($paymentData);

if (!$order) {
    pagbank_log_webhook('Evento recebido sem pedido local correspondente.', [
        'payment_data' => $paymentData,
        'content_type' => $contentType,
    ]);

    http_response_code(202);
    echo json_encode([
        'ok' => true,
        'message' => 'Evento recebido, mas nenhum pedido local foi encontrado.',
    ]);
    exit;
}

order_apply_pagbank_payment_data((int) $order['id'], $paymentData);

echo json_encode([
    'ok' => true,
    'pedido_id' => (int) $order['id'],
    'codigo_rastreio' => (string) ($order['codigo_rastreio'] ?? ''),
    'payment_status' => (string) ($paymentData['payment_status'] ?? ''),
]);
