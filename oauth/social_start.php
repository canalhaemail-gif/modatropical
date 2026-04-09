<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$provider = trim(strtolower((string) ($_GET['provider'] ?? '')));
$mode = social_auth_mode((string) ($_GET['mode'] ?? 'login'));

if (!in_array($provider, ['facebook', 'tiktok'], true) || !social_provider_enabled($provider)) {
    set_flash('error', 'Esse provedor social nao esta disponivel no momento.');
    redirect($mode === 'link' ? 'minha-conta.php#contas-conectadas' : 'entrar.php');
}

if ($mode === 'link') {
    require_customer_auth();
    $customerId = (int) (current_customer()['id'] ?? 0);
} else {
    require_customer_guest();
    $customerId = null;
}

$state = social_oauth_create_state($provider, $mode, $customerId);

if ($provider === 'facebook') {
    redirect(facebook_authorize_url($state));
}

if ($provider === 'tiktok') {
    redirect(tiktok_authorize_url($state));
}

redirect(apple_authorize_url($state));
