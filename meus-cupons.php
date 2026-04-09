<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_auth();

$customer = current_customer();
$customerId = (int) ($customer['id'] ?? 0);

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('meus-cupons.php');
    }

    $couponId = (int) posted_value('coupon_id');

    if ((string) posted_value('action') === 'redeem_coupon') {
        if (redeem_customer_coupon($customerId, $couponId)) {
            set_flash('success', 'Cupom resgatado com sucesso.');
        } else {
            set_flash('error', 'Nao foi possivel resgatar esse cupom agora.');
        }

        redirect('meus-cupons.php' . ($couponId > 0 ? '?cupom=' . $couponId : ''));
    }
}

$wallet = fetch_customer_coupon_wallet($customerId);
$redeemedCoupons = array_values(array_filter(
    $wallet,
    static fn(array $coupon): bool => !empty($coupon['is_redeemed'])
));
$availableCoupons = array_values(array_filter(
    $wallet,
    static fn(array $coupon): bool => !empty($coupon['can_redeem_now'])
));

$storeSettings = fetch_store_settings();
$pageTitle = 'Meus cupons | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body';
$showSplash = false;
$couponsHeadingImage = BASE_PATH . '/cupons.png';
$couponsHeadingImageRenderUrl = is_file($couponsHeadingImage)
    ? preferred_critical_image_render_url('cupons.png')
    : null;

extract(storefront_build_context(), EXTR_SKIP);

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';

function render_customer_coupon_card(array $coupon, string $actionType = 'redeem'): void
{
    $couponId = (int) ($coupon['id'] ?? 0);
    $isRedeemed = !empty($coupon['is_redeemed']);
    $isActiveNow = !empty($coupon['is_active_now']);
    ?>
    <article class="customer-coupon-card<?= $isRedeemed ? ' is-redeemed' : ''; ?>">
        <div class="customer-coupon-card__top">
            <div class="customer-coupon-card__title-row">
                <strong class="customer-coupon-card__label">Cupom</strong>
                <span class="status-pill status-pill--accent"><?= e((string) ($coupon['codigo'] ?? 'CUPOM')); ?></span>
            </div>
        </div>

        <p><?= e(trim((string) ($coupon['descricao'] ?? '')) !== '' ? (string) $coupon['descricao'] : coupon_build_description($coupon)); ?></p>

        <div class="customer-coupon-card__meta">
            <span><?= e(coupon_scope_label((string) ($coupon['escopo'] ?? 'order'))); ?></span>
            <?php if ((float) ($coupon['subtotal_minimo'] ?? 0) > 0): ?>
                <span>Minimo de <?= e(format_currency((float) ($coupon['subtotal_minimo'] ?? 0))); ?></span>
            <?php endif; ?>
            <?php if (!empty($coupon['ends_at'])): ?>
                <span>Valido ate <?= e(format_datetime_br((string) $coupon['ends_at'])); ?></span>
            <?php endif; ?>
        </div>

        <div class="customer-coupon-card__actions">
            <?php if (!$isActiveNow): ?>
                <span class="status-pill status-pill--neutral">Indisponivel agora</span>
            <?php elseif (empty($coupon['is_redeemed']) && !empty($coupon['redemption_limit_reached'])): ?>
                <span class="status-pill status-pill--warning">Limite de resgates atingido</span>
            <?php elseif ($actionType === 'saved'): ?>
                <span class="status-pill status-pill--success">Resgatado</span>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="redeem_coupon">
                    <input type="hidden" name="coupon_id" value="<?= e((string) $couponId); ?>">
                    <button class="btn btn--primary" type="submit">Resgatar cupom</button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php
}
?>

<div class="storefront">
    <section class="storefront-page-heading storefront-page-heading--promotions storefront-page-heading--coupons">
        <a class="btn btn--light storefront-page-heading__back" href="<?= e(app_url()); ?>" data-instant-nav>Voltar para a home</a>

        <div class="storefront-page-heading__content">
            <?php if ($couponsHeadingImageRenderUrl !== null): ?>
                <img
                    class="storefront-page-heading__title-image storefront-page-heading__title-image--promotions"
                    src="<?= e($couponsHeadingImageRenderUrl); ?>"
                    alt="Cupons da loja"
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                >
            <?php else: ?>
                <h1>Meus cupons</h1>
            <?php endif; ?>
        </div>
    </section>

    <section class="catalog-section customer-coupon-section customer-coupon-section--saved">
        <div class="catalog-section__header">
            <div>
                <h2>Cupons guardados para depois</h2>
            </div>
        </div>

        <?php if (!$redeemedCoupons): ?>
            <div class="empty-state">
                <strong>Voce ainda nao resgatou nenhum cupom.</strong>
                <p>Quando a loja liberar um cupom, voce pode guardar ele aqui para usar no fechamento depois.</p>
            </div>
        <?php else: ?>
            <div class="customer-coupon-grid">
                <?php foreach ($redeemedCoupons as $coupon): ?>
                    <?php render_customer_coupon_card($coupon, 'saved'); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($availableCoupons): ?>
        <section class="catalog-section customer-coupon-section customer-coupon-section--available">
            <div class="catalog-section__header">
                <div>
                    <h2>Cupons para resgatar</h2>
                </div>
            </div>

            <div class="customer-coupon-grid">
                <?php foreach ($availableCoupons as $coupon): ?>
                    <?php render_customer_coupon_card($coupon, 'redeem'); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
