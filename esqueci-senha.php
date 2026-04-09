<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_guest();

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('esqueci-senha.php');
    }

    $email = normalize_email((string) posted_value('email'));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Informe um email valido.');
        redirect('esqueci-senha.php');
    }

    $resetRequest = request_customer_password_reset_code($email);

    if (!$resetRequest['success']) {
        set_flash('error', 'Nao foi possivel enviar o email de recuperacao agora.');
        redirect('esqueci-senha.php');
    }

    if (!empty($resetRequest['logged']) && !empty($resetRequest['log_path']) && is_local_environment()) {
        $_SESSION['customer_reset_debug_notice'] = str_replace('\\', '/', (string) $resetRequest['log_path']);
    }

    set_flash('success', 'Se existir uma conta com esse email, enviamos um codigo de recuperacao.');
    redirect('redefinir-senha.php?email=' . urlencode($email));
}

$storeSettings = fetch_store_settings();
$pageTitle = 'Recuperar Senha';
$bodyClass = 'public-body--auth';
$extraStylesheets = ['assets/css/public-auth.css'];
$debugNotice = (string) pull_session_value('customer_reset_debug_notice', '');

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <section class="auth-layout">
        <article class="auth-panel">
            <span class="auth-panel__eyebrow">recuperacao</span>
            <h1>Recupere o acesso da sua conta.</h1>
            <p>Informe seu email. O sistema envia um codigo de 6 digitos para voce usar na redefinicao da senha.</p>
            <div class="auth-panel__actions">
                <a class="btn btn--ghost" href="<?= e(app_url('entrar.php')); ?>">Voltar ao login</a>
                <a class="btn btn--ghost" href="<?= e(app_url('index.php')); ?>">Ir para a vitrine</a>
            </div>
        </article>

        <article class="auth-form-card">
            <div class="auth-form-card__header">
                <span class="auth-form-card__badge">Senha</span>
                <h2>Enviar codigo de redefinicao</h2>
            </div>

            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                <div class="form-row">
                    <label for="email">Email da conta</label>
                    <input id="email" name="email" type="email" required autocomplete="email">
                </div>

                <button class="btn btn--primary auth-form-card__submit" type="submit">Enviar codigo</button>
            </form>

            <?php if ($debugNotice !== ''): ?>
                <div class="debug-reset-box">
                    <strong>Email salvo em arquivo no localhost</strong>
                    <input type="text" readonly value="<?= e($debugNotice); ?>">
                    <p>Se o XAMPP nao estiver configurado para enviar email real, abra esse arquivo e veja o codigo gerado.</p>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
