<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

extract(storefront_build_context(), EXTR_SKIP);

$pageTitle = 'Promocoes | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body';
$promotionList = $promotionProducts ?? [];
$promotionsHeadingImage = BASE_PATH . '/mega.png';
$promotionsHeadingImageRenderUrl = is_file($promotionsHeadingImage)
    ? preferred_critical_image_render_url('mega.png')
    : null;

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <section class="storefront-page-heading storefront-page-heading--brand storefront-page-heading--promotions">
        <a class="btn btn--light storefront-page-heading__back" href="<?= e(app_url()); ?>" data-instant-nav>Voltar para a home</a>

        <div class="storefront-page-heading__content">
            <?php if ($promotionsHeadingImageRenderUrl !== null): ?>
                <img
                    class="storefront-page-heading__title-image storefront-page-heading__title-image--promotions"
                    src="<?= e($promotionsHeadingImageRenderUrl); ?>"
                    alt="Promocoes da loja"
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                >
            <?php else: ?>
                <h1>Promocoes da loja</h1>
            <?php endif; ?>
        </div>

        <?php require BASE_PATH . '/includes/promo_fire_banner.php'; ?>
    </section>

    <main class="storefront-catalog catalog">
        <?php if ($promotionList === []): ?>
            <section class="empty-state">
                <strong>Nenhum produto em promocao no momento.</strong>
                <p>Em breve teremos novidades por aqui.</p>
            </section>
        <?php else: ?>
            <section class="catalog-section storefront-category">
                <div class="product-grid storefront-product-grid">
                    <?php foreach ($promotionList as $product): ?>
                        <?php
                        $category = [
                            'nome' => $product['categoria_nome'] ?? 'Categoria',
                            'slug' => $product['categoria_slug'] ?? '',
                        ];
                        require BASE_PATH . '/includes/storefront_product_card.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
