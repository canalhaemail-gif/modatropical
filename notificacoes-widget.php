<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$customer = current_customer();

if (
    !$customer
    || (int) ($customer['ativo'] ?? 0) !== 1
    || empty($customer['email_verificado_em'])
) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sessao indisponivel.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$customerId = (int) ($customer['id'] ?? 0);
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 6;
$limit = max(1, min(100, $limit));
$snapshot = customer_notifications_snapshot($customerId, $limit);
$returnTo = (string) ($_SERVER['HTTP_REFERER'] ?? 'notificacoes.php');

echo json_encode([
    'success' => true,
    'unread_count' => (int) ($snapshot['unread_count'] ?? 0),
    'html' => render_customer_notifications_widget(
        $snapshot['notifications'] ?? [],
        (int) ($snapshot['unread_count'] ?? 0),
        $returnTo
    ),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
