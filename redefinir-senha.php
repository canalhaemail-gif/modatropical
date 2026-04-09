<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$prefilledEmail = normalize_email((string) ($_GET['email'] ?? posted_value('email')));
$prefilledToken = trim((string) ($_GET['token'] ?? posted_value('token')));
$linkValidated = $prefilledEmail !== '' && $prefilledToken !== ''
    ? find_customer_password_reset_by_token($prefilledEmail, $prefilledToken)
    : null;

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('esqueci-senha.php');
    }

    $email = normalize_email((string) posted_value('email'));
    $code = digits_only((string) posted_value('code'));
    $token = trim((string) posted_value('token'));
    $password = (string) posted_value('password');
    $passwordConfirmation = (string) posted_value('password_confirmation');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Informe um email valido.');
        redirect('redefinir-senha.php?email=' . urlencode($prefilledEmail));
    }

    if ($token === '' && strlen($code) !== 6) {
        set_flash('error', 'Informe o codigo de 6 digitos enviado por email.');
        redirect('redefinir-senha.php?email=' . urlencode($email));
    }

    if (!is_strong_password($password)) {
        set_flash('error', password_rule_message());
        redirect('redefinir-senha.php?email=' . urlencode($email));
    }

    if ($password !== $passwordConfirmation) {
        set_flash('error', 'A confirmacao da senha nao confere.');
        redirect('redefinir-senha.php?email=' . urlencode($email));
    }

    $resetResult = $token !== ''
        ? reset_customer_password_with_token($email, $token, $password)
        : reset_customer_password_with_code($email, $code, $password);

    if (!$resetResult) {
        set_flash('error', 'Codigo invalido ou expirado. Solicite um novo envio.');
        redirect('redefinir-senha.php?email=' . urlencode($email));
    }

    logout_customer();
    $loginResult = attempt_customer_login_result($email, $password);

    if (!empty($loginResult['success'])) {
        set_flash('success', 'Senha redefinida com sucesso. Voce ja esta na sua conta.');
        redirect('minha-conta.php');
    }

    if (($loginResult['reason'] ?? '') === 'unverified') {
        $_SESSION['pending_verification_email'] = $email;
        set_flash('success', 'Senha redefinida com sucesso. Confirme seu email para continuar.');
        redirect('verificar-email.php?email=' . rawurlencode($email));
    }

    set_flash('success', 'Senha redefinida com sucesso. Entre com a nova senha.');
    redirect('entrar.php');
}

$storeSettings = fetch_store_settings();
$pageTitle = 'Redefinir Senha';
$bodyClass = 'public-body--auth';
$extraStylesheets = ['assets/css/public-auth.css'];

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <section class="auth-layout auth-layout--single">
        <article class="auth-form-card">
            <div class="auth-form-card__header">
                <span class="auth-form-card__badge">Codigo</span>
                <h2><?= $linkValidated ? 'Link confirmado, defina sua nova senha' : 'Digite o codigo recebido'; ?></h2>
            </div>

            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="token" value="<?= e($prefilledToken); ?>">

                <div class="form-row">
                    <label for="email">Email da conta</label>
                    <input id="email" name="email" type="email" required value="<?= e($prefilledEmail); ?>" autocomplete="email">
                </div>

                <?php if (!$linkValidated): ?>
                    <div class="form-row">
                        <label for="code">Codigo de recuperacao</label>
                        <input id="code" name="code" type="text" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000">
                    </div>
                <?php else: ?>
                    <div class="form-row">
                        <div class="form-row__hint">O link do email ja confirmou sua recuperacao. Agora basta definir a nova senha.</div>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <label for="password">Nova senha</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password">
                    <small class="form-row__hint">Use pelo menos 8 caracteres, uma letra maiuscula e um caractere especial.</small>
                </div>

                <div class="form-row">
                    <label for="password_confirmation">Confirmar nova senha</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>

                <button class="btn btn--primary auth-form-card__submit" type="submit">Salvar nova senha</button>
            </form>

            <p class="auth-form-card__footer">
                Nao recebeu o codigo?
                <a href="<?= e(app_url('esqueci-senha.php')); ?>">Solicitar novo envio</a>
            </p>
        </article>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
