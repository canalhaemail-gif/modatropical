<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_guest();

$requestedEmail = normalize_email((string) ($_GET['email'] ?? ''));
$pendingEmail = normalize_email((string) pull_session_value('pending_verification_email', ''));
$prefillEmail = $requestedEmail !== '' ? $requestedEmail : $pendingEmail;
$mailLogPath = (string) pull_session_value('verification_mail_log_path', '');

if (isset($_GET['token']) && trim((string) $_GET['token']) !== '') {
    if (verify_customer_email_with_token((string) $_GET['token'])) {
        set_flash('success', 'Email confirmado com sucesso. Agora voce ja pode entrar na sua conta.');
        redirect('entrar.php');
    }

    set_flash('error', 'O link de confirmacao e invalido ou expirou. Solicite um novo envio.');
    redirect('verificar-email.php' . ($prefillEmail !== '' ? '?email=' . rawurlencode($prefillEmail) : ''));
}

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('verificar-email.php' . ($prefillEmail !== '' ? '?email=' . rawurlencode($prefillEmail) : ''));
    }

    $action = (string) posted_value('action', 'verify');
    $email = normalize_email((string) posted_value('email'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Informe um email valido.');
        redirect('verificar-email.php');
    }

    if ($action === 'resend') {
        $_SESSION['pending_verification_email'] = $email;
        $result = request_customer_email_verification($email);

        if (!empty($result['already_verified'])) {
            set_flash('success', 'Esse email ja esta confirmado. Voce pode entrar normalmente.');
            redirect('entrar.php');
        }

        if (!empty($result['delivered'])) {
            set_flash('success', 'Enviamos um novo codigo e um novo link de confirmacao.');
        } elseif (!empty($result['logged'])) {
            $_SESSION['verification_mail_log_path'] = (string) ($result['log_path'] ?? '');
            set_flash('success', 'No localhost, o email de confirmacao foi salvo localmente para teste.');
        } else {
            set_flash('error', 'Nao foi possivel reenviar agora. Tente novamente em instantes.');
        }

        redirect('verificar-email.php?email=' . rawurlencode($email));
    }

    $code = trim((string) posted_value('code'));

    if ($code === '' || preg_match('/^\d{6}$/', $code) !== 1) {
        set_flash('error', 'Informe o codigo de 6 digitos recebido por email.');
        redirect('verificar-email.php?email=' . rawurlencode($email));
    }

    if (!verify_customer_email_with_code($email, $code)) {
        set_flash('error', 'Codigo invalido ou expirado. Solicite um novo envio se precisar.');
        redirect('verificar-email.php?email=' . rawurlencode($email));
    }

    set_flash('success', 'Email confirmado com sucesso. Agora voce ja pode entrar na sua conta.');
    redirect('entrar.php');
}

$storeSettings = fetch_store_settings();
$pageTitle = 'Confirmar Email';
$bodyClass = 'public-body--auth';
$extraStylesheets = ['assets/css/public-auth.css'];

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <section class="auth-layout">
        <article class="auth-panel">
            <span class="auth-panel__eyebrow">confirmacao</span>
            <h1>Confirme seu email para liberar o acesso.</h1>
            <p>Voce pode digitar o codigo de 6 digitos recebido no email ou usar o botao de confirmacao que enviamos.</p>
            <div class="auth-panel__actions">
                <a class="btn btn--ghost" href="<?= e(app_url('entrar.php')); ?>">Ja tenho conta</a>
                <a class="btn btn--ghost" href="<?= e(app_url('index.php')); ?>">Voltar para a vitrine</a>
            </div>
        </article>

        <article class="auth-form-card">
            <div class="auth-form-card__header">
                <span class="auth-form-card__badge">Verificar</span>
                <h2>Ative sua conta</h2>
            </div>

            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                <div class="form-row">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required autocomplete="email" value="<?= e((string) posted_value('email', $prefillEmail)); ?>">
                </div>

                <div class="form-row">
                    <label for="code">Codigo de confirmacao</label>
                    <input id="code" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="000000">
                </div>

                <button class="btn btn--primary auth-form-card__submit" type="submit" name="action" value="verify">Confirmar email</button>
                <button class="btn btn--ghost auth-form-card__submit auth-form-card__secondary-form" type="submit" name="action" value="resend">Reenviar codigo</button>
            </form>

            <?php if ($mailLogPath !== '' && is_local_environment()): ?>
                <div class="debug-reset-box">
                    <strong>Email de teste salvo localmente</strong>
                    <p>Arquivo: <?= e($mailLogPath); ?></p>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
