<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$state = trim((string) ($_REQUEST['state'] ?? ''));
$oauthState = social_oauth_consume_state($state);

if (!$oauthState) {
    set_flash('error', 'A autenticacao social expirou. Tente novamente.');
    redirect('entrar.php');
}

$provider = trim(strtolower((string) ($oauthState['provider'] ?? '')));
$mode = social_auth_mode((string) ($oauthState['mode'] ?? 'login'));
$targetRedirect = $mode === 'link' ? 'minha-conta.php#contas-conectadas' : 'entrar.php';
$errorDescription = trim((string) ($_REQUEST['error_description'] ?? $_REQUEST['error_message'] ?? ''));

if ($errorDescription !== '') {
    set_flash('error', $errorDescription);
    redirect($targetRedirect);
}

if (!social_provider_exists($provider)) {
    set_flash('error', 'O provedor informado e invalido.');
    redirect($targetRedirect);
}

if ($mode === 'link') {
    require_customer_auth();
    $currentCustomer = current_customer();

    if ((int) ($currentCustomer['id'] ?? 0) !== (int) ($oauthState['customer_id'] ?? 0)) {
        set_flash('error', 'Sua sessao mudou durante o vinculo. Entre novamente e tente outra vez.');
        redirect('entrar.php');
    }
}

if ($provider === 'facebook') {
    $profileResult = facebook_exchange_code_for_profile((string) ($_GET['code'] ?? ''));
} elseif ($provider === 'tiktok') {
    $profileResult = tiktok_exchange_code_for_profile((string) ($_GET['code'] ?? ''));
} else {
    $profileResult = apple_exchange_code_for_profile(
        (string) ($_POST['code'] ?? $_GET['code'] ?? ''),
        (string) ($_POST['user'] ?? '')
    );
}

if (!$profileResult['success']) {
    set_flash('error', (string) ($profileResult['error'] ?? 'Nao foi possivel concluir a autenticacao social.'));
    redirect($targetRedirect);
}

if ($mode === 'link') {
    $result = social_auth_connect_identity(
        (int) ($oauthState['customer_id'] ?? 0),
        $provider,
        (string) ($profileResult['provider_user_id'] ?? ''),
        (string) ($profileResult['email'] ?? ''),
        (string) ($profileResult['name'] ?? '')
    );

    if (!$result['success']) {
        set_flash('error', (string) ($result['error'] ?? 'Nao foi possivel vincular a conta.'));
        redirect('minha-conta.php#contas-conectadas');
    }

    $successLabel = social_provider_label($provider);
    $message = ($result['reason'] ?? '') === 'already_linked'
        ? $successLabel . ' ja estava vinculado a sua conta.'
        : $successLabel . ' vinculado com sucesso.';

    set_flash('success', $message);
    redirect('minha-conta.php#contas-conectadas');
}

$result = social_auth_complete_login(
    $provider,
    (string) ($profileResult['provider_user_id'] ?? ''),
    (string) ($profileResult['email'] ?? ''),
    (string) ($profileResult['name'] ?? '')
);

if (!$result['success']) {
    set_flash('error', (string) ($result['error'] ?? 'Nao foi possivel concluir o login social.'));
    redirect('entrar.php');
}

if (!empty($result['profile_incomplete'])) {
    set_flash('success', 'Conta conectada com ' . social_provider_label($provider) . '. Agora complete seus dados para liberar pedidos e entrega.');
    redirect('completar-cadastro.php');
}

set_flash('success', 'Login com ' . social_provider_label($provider) . ' realizado com sucesso.');
redirect('');
