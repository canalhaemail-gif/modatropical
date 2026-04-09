<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_guest();

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('entrar.php');
    }

    $email = normalize_email((string) posted_value('email'));
    $password = (string) posted_value('password');

    if ($email === '' || $password === '') {
        set_flash('error', 'Informe email e senha.');
        redirect('entrar.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Informe um email valido.');
        redirect('entrar.php');
    }

    $remember = posted_value('remember') === '1';
    $loginResult = attempt_customer_login_result($email, $password, $remember);

    if (!$loginResult['success']) {
        if (($loginResult['reason'] ?? '') === 'unverified') {
            $_SESSION['pending_verification_email'] = $email;
            set_flash('error', 'Seu email ainda nao foi confirmado. Digite o codigo recebido ou use o link do email.');
            redirect('verificar-email.php?email=' . rawurlencode($email));
        }

        if (($loginResult['reason'] ?? '') === 'inactive') {
            set_flash('error', 'Sua conta esta inativa no momento.');
            redirect('entrar.php');
        }

        set_flash('error', 'Credenciais invalidas.');
        redirect('entrar.php');
    }

    set_flash('success', 'Login realizado com sucesso.');
    redirect('');
}

$storeSettings = fetch_store_settings();
$pageTitle = 'Entrar na Conta';
$bodyClass = 'public-body--auth';
$extraStylesheets = ['assets/css/public-auth.css'];
$welcomeTitleImagePath = BASE_PATH . '/bemvindo.png';
$loginBrandPath = preferred_critical_image_render_url(
    is_file(BASE_PATH . '/logo.png') ? 'logo.png' : 'assets/img/default-logo.svg',
    ['assets/img/logo-fast.webp']
);
$welcomeTitleImageRenderUrl = is_file($welcomeTitleImagePath)
    ? preferred_critical_image_render_url('bemvindo.png')
    : null;

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell page-shell--login">
    <section class="auth-layout auth-layout--single auth-layout--login">
        <a class="auth-entry-brand" href="<?= e(app_url('index.php')); ?>" aria-label="Voltar para a loja">
            <img src="<?= e($loginBrandPath); ?>" alt="Moda Tropical" loading="eager" decoding="async" fetchpriority="high">
        </a>
        <article class="auth-form-card auth-form-card--login-modern">
            <div class="auth-form-card__header auth-form-card__header--login-modern">
                <?php if (is_file($welcomeTitleImagePath)): ?>
                    <img
                        class="auth-form-card__title-image"
                        src="<?= e((string) $welcomeTitleImageRenderUrl); ?>"
                        alt="Bem-vindo"
                        loading="eager"
                        decoding="async"
                        fetchpriority="high"
                    >
                <?php else: ?>
                    <h2>Bem-vindo</h2>
                <?php endif; ?>
            </div>

            <form method="post" class="auth-login-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                <div class="auth-login-form__field">
                    <label for="email">Email</label>
                    <div class="auth-login-form__input-shell">
                        <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                            <path d="M30.853 13.87a15 15 0 0 0-29.729 4.082 15.1 15.1 0 0 0 12.876 12.918 15.6 15.6 0 0 0 2.016.13 14.85 14.85 0 0 0 7.715-2.145 1 1 0 1 0-1.031-1.711 13.007 13.007 0 1 1 5.458-6.529 2.149 2.149 0 0 1-4.158-.759v-10.856a1 1 0 0 0-2 0v1.726a8 8 0 1 0 .2 10.325 4.135 4.135 0 0 0 7.83.274 15.2 15.2 0 0 0 .823-7.455Zm-14.853 8.13a6 6 0 1 1 6-6 6.006 6.006 0 0 1-6 6Z"></path>
                        </svg>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            placeholder="Digite seu email"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>

                <div class="auth-login-form__field">
                    <label for="password">Senha</label>
                    <div class="auth-login-form__input-shell">
                        <svg viewBox="-64 0 512 512" aria-hidden="true" focusable="false">
                            <path d="m336 512h-288c-26.453125 0-48-21.523438-48-48v-224c0-26.476562 21.546875-48 48-48h288c26.453125 0 48 21.523438 48 48v224c0 26.476562-21.546875 48-48 48zm-288-288c-8.8125 0-16 7.167969-16 16v224c0 8.832031 7.1875 16 16 16h288c8.8125 0 16-7.167969 16-16v-224c0-8.832031-7.1875-16-16-16zm0 0"></path>
                            <path d="m304 224c-8.832031 0-16-7.167969-16-16v-80c0-52.929688-43.070312-96-96-96s-96 43.070312-96 96v80c0 8.832031-7.167969 16-16 16s-16-7.167969-16-16v-80c0-70.59375 57.40625-128 128-128s128 57.40625 128 128v80c0 8.832031-7.167969 16-16 16zm0 0"></path>
                        </svg>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            placeholder="Digite sua senha"
                            required
                            autocomplete="current-password"
                        >
                        <button
                            class="auth-login-form__input-trailing"
                            type="button"
                            data-password-toggle
                            data-password-target="password"
                            aria-label="Mostrar senha"
                            aria-pressed="false"
                        >
                            <svg viewBox="0 0 576 512" aria-hidden="true" focusable="false">
                                <path d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zm0 368a144 144 0 1 1 0-288 144 144 0 1 1 0 288zm0-208a64 64 0 1 0 0 128 64 64 0 1 0 0-128z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="auth-login-form__meta">
                    <label class="auth-login-form__remember">
                        <span class="auth-login-form__remember-box">
                            <input type="checkbox" name="remember" value="1">
                            <span class="auth-login-form__remember-mark"></span>
                        </span>
                        <span>Lembrar de mim</span>
                    </label>
                    <a href="<?= e(app_url('esqueci-senha.php')); ?>">Esqueci minha senha</a>
                </div>

                <button class="auth-login-form__submit" type="submit">Entrar</button>

            </form>

            <?php
            $socialMode = 'login';
            $socialClusterLabel = 'ou continue com';
            $googleButtonType = 'icon';
            $googleButtonShape = 'square';
            $googleButtonSize = 'large';
            require BASE_PATH . '/includes/social_auth_cluster.php';
            ?>

            <p class="auth-form-card__footer auth-form-card__footer--login-modern">
                Ainda nao tem conta?
                <a href="<?= e(app_url('cadastro.php')); ?>">Cadastre-se aqui</a>
            </p>
        </article>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
