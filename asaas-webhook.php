<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo nao permitido.']);
    exit;
}

if (!asaas_is_enabled()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Asaas desabilitado.']);
    exit;
}

if (!asaas_validate_webhook_auth($_SERVER)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Token do webhook invalido.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$rawBody = is_string($rawBody) ? $rawBody : '';

if ($rawBody === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payload vazio.']);
    exit;
}

$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payload JSON invalido.']);
    exit;
}

$paymentData = asaas_extract_checkout_payment_data($payload);
$externalOrderId = trim((string) ($paymentData['external_order_id'] ?? ''));

if ($externalOrderId === '') {
    http_response_code(202);
    echo json_encode([
        'ok' => true,
        'message' => 'Evento recebido, mas sem checkout id.',
    ]);
    exit;
}

$statement = db()->prepare(
    'SELECT *
     FROM pedidos
     WHERE payment_external_order_id = :external_order_id
       AND payment_provider = :payment_provider
     LIMIT 1'
);
$statement->execute([
    'external_order_id' => $externalOrderId,
    'payment_provider' => 'asaas',
]);
$order = $statement->fetch() ?: null;

if (!$order) {
    http_response_code(202);
    echo json_encode([
        'ok' => true,
        'message' => 'Evento recebido, mas nenhum pedido local foi encontrado.',
    ]);
    exit;
}

order_apply_asaas_payment_data((int) $order['id'], $paymentData);

echo json_encode([
    'ok' => true,
    'pedido_id' => (int) $order['id'],
    'codigo_rastreio' => (string) ($order['codigo_rastreio'] ?? ''),
    'payment_status' => (string) ($paymentData['payment_status'] ?? ''),
]);
