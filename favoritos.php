<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

function favorites_request_wants_json(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return posted_value('ajax') === '1'
        || $requestedWith === 'xmlhttprequest'
        || str_contains($accept, 'application/json');
}

function favorites_respond_json(int $statusCode, bool $success, string $message, array $extra = []): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

if (!is_post()) {
    redirect('minha-conta.php');
}

$expectsJson = favorites_request_wants_json();
$customer = current_customer();

if (
    $customer === null
    || (int) ($customer['ativo'] ?? 0) !== 1
    || empty($customer['email_verificado_em'])
) {
    if ($expectsJson) {
        favorites_respond_json(401, false, 'Entre na sua conta para salvar pecas.', [
            'login_url' => app_url('entrar.php'),
        ]);
    }

    set_flash('error', 'Entre na sua conta para salvar pecas.');
    redirect('entrar.php');
}

if (!verify_csrf_token(posted_value('csrf_token'))) {
    if ($expectsJson) {
        favorites_respond_json(419, false, 'Sessao expirada. Tente novamente.');
    }

    set_flash('error', 'Sessao expirada. Tente novamente.');
    redirect('minha-conta.php');
}

$productId = (int) posted_value('product_id');
$products = storefront_fetch_products_by_ids([$productId]);
$product = $products[$productId] ?? null;

if ($product === null) {
    if ($expectsJson) {
        favorites_respond_json(404, false, 'Produto indisponivel.');
    }

    set_flash('error', 'Produto indisponivel.');
    redirect('minha-conta.php');
}

$saved = customer_toggle_favorite_product((int) $customer['id'], $productId);
$savedCount = customer_favorite_count((int) $customer['id']);
$message = $saved
    ? 'Peca adicionada aos seus salvos.'
    : 'Peca removida dos seus salvos.';

if ($expectsJson) {
    favorites_respond_json(200, true, $message, [
        'saved' => $saved,
        'saved_count' => $savedCount,
    ]);
}

set_flash('success', $message);
redirect('minha-conta.php');
