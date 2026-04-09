<?php
declare(strict_types=1);

$socialMode = social_auth_mode((string) ($socialMode ?? 'login'));
$socialClusterLabel = trim((string) ($socialClusterLabel ?? 'ou continue com'));
$googleButtonType = trim((string) ($googleButtonType ?? 'icon'));
$googleButtonShape = trim((string) ($googleButtonShape ?? 'square'));
$googleButtonSize = trim((string) ($googleButtonSize ?? 'large'));
$googleFormId = 'google-social-form-' . $socialMode;
$googleLoginUri = absolute_app_url('oauth/google.php');
$socialProviderLogoMap = [
    'google' => 'assets/img/logogoogle.png',
    'facebook' => 'assets/img/logofacebook.png',
    'tiktok' => 'assets/img/logotiktok.png',
];
$socialProviderLogoMarkup = static function (string $provider) use ($socialProviderLogoMap): string {
    $provider = trim(strtolower($provider));
    $logoPath = $socialProviderLogoMap[$provider] ?? '';

    if ($logoPath !== '') {
        return sprintf(
            '<img class="auth-social-link__logo auth-social-link__logo--%1$s" src="%2$s" alt="" loading="lazy" decoding="async">',
            e($provider),
            e(asset_url($logoPath))
        );
    }

    return social_provider_icon_markup($provider);
};
?>
<div class="auth-login-social">
    <div class="auth-login-social__divider">
        <span><?= e($socialClusterLabel); ?></span>
    </div>

    <div class="auth-social-card">
        <?php if (google_login_enabled()): ?>
            <div class="auth-social-link auth-social-link--google auth-social-link--google-host" data-google-auth-wrap aria-label="<?= e(($socialMode === 'link' ? 'Vincular com ' : 'Entrar com ') . 'Google'); ?>">
                <?= $socialProviderLogoMarkup('google'); ?>
                <div
                    class="auth-social-link__google-frame"
                    id="<?= e($googleFormId); ?>-button"
                    data-google-auth-button
                    data-google-client-id="<?= e((string) GOOGLE_CLIENT_ID); ?>"
                    data-google-auth-form="<?= e($googleFormId); ?>"
                    data-google-button-type="<?= e($googleButtonType); ?>"
                    data-google-button-shape="<?= e($googleButtonShape); ?>"
                    data-google-button-size="<?= e($googleButtonSize); ?>"
                    data-google-login-uri="<?= e($googleLoginUri); ?>"
                ></div>
            </div>

            <form method="post" action="<?= e(app_url('oauth/google.php')); ?>" id="<?= e($googleFormId); ?>" class="auth-login-social__hidden-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="credential" value="" data-google-credential-input>
                <input type="hidden" name="mode" value="<?= e($socialMode); ?>">
            </form>
        <?php else: ?>
            <span
                class="auth-social-link auth-social-link--google is-disabled"
                aria-label="Google em breve"
                title="Google sera ativado assim que configurarmos as credenciais."
            >
                <?= $socialProviderLogoMarkup('google'); ?>
            </span>
        <?php endif; ?>

        <?php foreach (['facebook', 'tiktok'] as $provider): ?>
            <?php $isEnabled = social_provider_enabled($provider); ?>

            <?php if ($isEnabled): ?>
                <a
                    class="auth-social-link auth-social-link--<?= e($provider); ?>"
                    href="<?= e(social_provider_start_url($provider, $socialMode)); ?>"
                    aria-label="<?= e(($socialMode === 'link' ? 'Vincular com ' : 'Entrar com ') . social_provider_label($provider)); ?>"
                >
                    <?= $socialProviderLogoMarkup($provider); ?>
                </a>
            <?php else: ?>
                <span
                    class="auth-social-link auth-social-link--<?= e($provider); ?> is-disabled"
                    aria-label="<?= e(social_provider_label($provider) . ' em breve'); ?>"
                    title="<?= e(social_provider_label($provider) . ' sera ativado assim que configurarmos as credenciais.'); ?>"
                >
                    <?= $socialProviderLogoMarkup($provider); ?>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>

    </div>
</div>

<?php if (google_login_enabled()): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
<?php endif; ?>
