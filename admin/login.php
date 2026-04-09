<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_guest();

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('admin/login.php');
    }

    $email = trim((string) posted_value('email'));
    $password = (string) posted_value('password');
    $remember = posted_value('remember') === '1';

    if ($email === '' || $password === '') {
        set_flash('error', 'Informe email e senha.');
        redirect('admin/login.php');
    }

    if (!attempt_admin_login($email, $password, $remember)) {
        set_flash('error', 'Credenciais invalidas.');
        redirect('admin/login.php');
    }

    set_flash('success', 'Login realizado com sucesso.');
    redirect('admin/index.php');
}

$storeSettings = fetch_store_settings();
$pageTitle = 'Login do Painel';
$flashes = pull_flashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/admin.css')); ?>">
</head>
<body class="admin-auth">
    <div class="admin-auth__backdrop"></div>

    <?php if ($flashes): ?>
        <div class="flash-stack flash-stack--auth">
            <?php foreach ($flashes as $flash): ?>
                <div class="flash flash--<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main class="auth-card">
        <div class="auth-card__brand">
            <span class="auth-card__badge">CD</span>
            <div>
                <strong><?= e($storeSettings['nome_estabelecimento'] ?? APP_NAME); ?></strong>
                <p>Entre para gerenciar categorias, produtos e configuracoes.</p>
            </div>
        </div>

        <form method="post" class="admin-form auth-card__form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

            <div class="form-row">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required autocomplete="email">
            </div>

            <div class="form-row">
                <label for="password">Senha</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
            </div>

            <div class="auth-card__meta">
                <label class="auth-card__remember">
                    <span class="auth-card__remember-box">
                        <input type="checkbox" name="remember" value="1">
                        <span class="auth-card__remember-mark"></span>
                    </span>
                    <span>Manter login neste dispositivo</span>
                </label>
            </div>

            <button class="button button--primary button--full" type="submit">Entrar no painel</button>
        </form>

        <div class="auth-card__footer">
            <span>Use as credenciais iniciais importadas no banco para o primeiro acesso.</span>
            <span>Depois do login, altere os dados conforme sua operacao.</span>
        </div>
    </main>

    <script src="<?= e(asset_url('assets/js/admin.js')); ?>"></script>
</body>
</html>
