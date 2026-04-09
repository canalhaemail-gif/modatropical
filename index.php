<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

extract(storefront_build_context(), EXTR_SKIP);

$pageTitle = ($storeSettings['nome_estabelecimento'] ?? APP_NAME) . ' | Vitrine Digital';
$bodyClass = 'storefront-body';
$showcaseProducts = array_slice($promotionProducts, 0, 8);
$homeFeaturedProducts = $featuredProducts;
$homeFeaturedHeadingImageRenderUrl = is_file(BASE_PATH . '/destaques.png')
    ? preferred_critical_image_render_url('destaques.png')
    : null;
$criticalImageCandidates = ($homeFeaturedHeadingImageRenderUrl !== null && str_starts_with($homeFeaturedHeadingImageRenderUrl, 'data:image/'))
    ? []
    : ['destaques.png'];

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <?php if ($showcaseProducts): ?>
        <section class="storefront-showcase">
            <div class="storefront-showcase__feature">
                <div class="storefront-showcase__marquee-header">
                    <a class="storefront-showcase__badge" href="<?= e(app_url('promocoes.php')); ?>" data-instant-nav>
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M13.2 2.4c.4 2.2-.4 3.8-1.8 5.3-1 1-1.9 2-2.2 3.7-.2 1.1 0 2.1.6 3 .2-1.6 1.1-2.6 2.1-3.6 1.5-1.5 3.2-3.2 3.4-6 .8.8 1.8 2.1 2.4 3.7.8 2.1.7 4.3-.1 6.2-1.1 2.5-3.5 4.3-6.4 4.3-3.8 0-6.8-3-6.8-6.8 0-2.7 1.3-4.7 2.9-6.4 2-2.1 4.1-3.8 5.9-7.4z"></path>
                        </svg>
                        <span>QUEIMA DE ESTOQUE</span>
                    </a>
                </div>

                <div class="storefront-showcase__marquee-shell">
                    <div class="storefront-showcase__viewport" data-showcase-carousel>
                        <div class="storefront-showcase__carousel-track" data-showcase-track>
                            <?php foreach ($showcaseProducts as $product): ?>
                                <?php
                                $productCategory = [
                                    'nome' => $product['categoria_nome'] ?? 'Categoria',
                                    'slug' => $product['categoria_slug'] ?? '',
                                ];
                                $brandName = storefront_brand_display_name($product);
                                ?>
                                <a
                                    class="storefront-showcase__mini-card"
                                    data-showcase-slide
                                    draggable="false"
                                    href="<?= e(storefront_product_url((string) $product['slug'])); ?>"
                                >
                                    <span class="storefront-showcase__promo-ribbon">EM PROMO</span>
                                    <div class="storefront-showcase__mini-media">
                                        <?php if (!empty($product['imagem'])): ?>
                                            <img
                                                src="<?= e(app_url($product['imagem'])); ?>"
                                                alt="<?= e($product['nome']); ?>"
                                                loading="lazy"
                                                draggable="false"
                                            >
                                        <?php else: ?>
                                            <div class="storefront-showcase__placeholder">sem imagem</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="storefront-showcase__mini-copy">
                                        <span><?= e($brandName); ?></span>
                                        <strong><?= e($product['nome']); ?></strong>
                                        <?php if (product_has_discount($product)): ?>
                                            <small class="storefront-showcase__mini-price-old"><?= e(format_currency(product_original_price($product))); ?></small>
                                        <?php endif; ?>
                                        <small><?= e(format_currency(product_final_price($product))); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($homeFeaturedProducts): ?>
        <section class="catalog-section storefront-category storefront-category--featured">
            <div class="catalog-section__header storefront-category__header storefront-category__header--featured">
                <div>
                    <img
                        class="storefront-category__header-image"
                        src="<?= e((string) $homeFeaturedHeadingImageRenderUrl); ?>"
                        alt="Destaques da loja"
                        loading="eager"
                        decoding="async"
                        fetchpriority="high"
                    >
                </div>
            </div>

            <div class="product-grid storefront-product-grid">
                <?php foreach ($homeFeaturedProducts as $product): ?>
                    <?php
                    $category = [
                        'nome' => $product['categoria_nome'] ?? 'Categoria',
                        'slug' => $product['categoria_slug'] ?? '',
                    ];
                    ?>
                    <?php require BASE_PATH . '/includes/storefront_product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
