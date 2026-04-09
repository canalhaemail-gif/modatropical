<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$requestedMode = trim((string) ($_GET['mode'] ?? posted_value('mode', '')));
$mode = $requestedMode !== ''
    ? social_auth_mode($requestedMode)
    : (current_customer() ? 'link' : 'login');
$targetRedirect = $mode === 'link' ? 'minha-conta.php#contas-conectadas' : 'entrar.php';

if ($mode === 'link') {
    require_customer_auth();
} else {
    require_customer_guest();
}

if (!google_login_enabled()) {
    set_flash('error', 'Google Login nao esta disponivel no momento.');
    redirect('entrar.php');
}

if (!is_post()) {
    set_flash('error', 'Sessao expirada. Tente novamente.');
    redirect('entrar.php');
}

$credential = trim((string) posted_value('credential'));
$googleCsrfBody = trim((string) posted_value('g_csrf_token'));
$googleCsrfCookie = trim((string) ($_COOKIE['g_csrf_token'] ?? ''));
$isGoogleRedirectPost = $credential !== '' && $googleCsrfBody !== '';

if ($isGoogleRedirectPost) {
    if ($googleCsrfCookie === '' || !hash_equals($googleCsrfCookie, $googleCsrfBody)) {
        set_flash('error', 'Nao foi possivel validar a seguranca do login Google. Tente novamente.');
        redirect($targetRedirect);
    }
} elseif (!verify_csrf_token(posted_value('csrf_token'))) {
    set_flash('error', 'Sessao expirada. Tente novamente.');
    redirect('entrar.php');
}

try {
    $verification = verify_google_identity_token($credential);
} catch (Throwable $exception) {
    error_log('Google OAuth endpoint failure: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    set_flash('error', 'Nao foi possivel entrar com Google agora.');
    redirect($targetRedirect);
}

if (!$verification['success']) {
    set_flash('error', (string) ($verification['error'] ?? 'Nao foi possivel entrar com Google agora.'));
    redirect($targetRedirect);
}

$claims = $verification['claims'];

if ($mode === 'link') {
    $result = social_auth_connect_identity(
        (int) (current_customer()['id'] ?? 0),
        'google',
        trim((string) ($claims['sub'] ?? '')),
        normalize_email((string) ($claims['email'] ?? '')),
        normalize_person_name((string) ($claims['name'] ?? ''))
    );

    if (!$result['success']) {
        set_flash('error', (string) ($result['error'] ?? 'Nao foi possivel vincular o Google agora.'));
        redirect('minha-conta.php#contas-conectadas');
    }

    $message = ($result['reason'] ?? '') === 'already_linked'
        ? 'Google ja estava vinculado a sua conta.'
        : 'Google vinculado com sucesso.';

    set_flash('success', $message);
    redirect('minha-conta.php#contas-conectadas');
}

$result = social_auth_complete_login(
    'google',
    trim((string) ($claims['sub'] ?? '')),
    normalize_email((string) ($claims['email'] ?? '')),
    normalize_person_name((string) ($claims['name'] ?? ''))
);

if (!$result['success']) {
    set_flash('error', (string) ($result['error'] ?? 'Nao foi possivel entrar com Google agora.'));
    redirect($targetRedirect);
}

if (!empty($result['profile_incomplete'])) {
    set_flash('success', 'Conta conectada com Google. Agora complete seus dados para liberar pedidos e entrega.');
    redirect('completar-cadastro.php');
}

set_flash('success', 'Login com Google realizado com sucesso.');
redirect('');
