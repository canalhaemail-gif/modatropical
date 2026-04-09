<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

extract(storefront_build_context(), EXTR_SKIP);

$slug = trim((string) ($_GET['slug'] ?? ''));
$product = storefront_find_product_by_slug($slug);

if ($product === null) {
    set_flash('error', 'Produto nao encontrado.');
    redirect('');
}

$pageTitle = ($product['nome'] ?? 'Produto') . ' | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body';
$currentCategorySlug = $product['categoria_slug'] ?? null;
$category = [
    'nome' => $product['categoria_nome'] ?? 'Categoria',
    'slug' => $product['categoria_slug'] ?? '',
];
$brandName = storefront_brand_display_name($product);
$hasBrandMeta = trim((string) ($product['marca_nome'] ?? '')) !== '';
$showCategoryMeta = trim($category['nome']) !== '' && strcasecmp(trim($brandName), trim($category['nome'])) !== 0;
$productGalleryImages = fetch_product_gallery_images((int) $product['id']);
$productGallery = [];

if (!empty($product['imagem'])) {
    $productGallery[] = [
        'src' => $product['imagem'],
        'label' => 'Imagem principal',
    ];
}

foreach ($productGalleryImages as $galleryImage) {
    $productGallery[] = [
        'src' => $galleryImage['imagem'],
        'label' => 'Imagem adicional',
    ];
}

if ($productGallery === []) {
    $productGallery[] = [
        'src' => 'assets/img/default-logo.svg',
        'label' => 'Sem imagem',
    ];
}
$hasGalleryThumbs = count($productGallery) > 1;

$productFlavors = fetch_product_flavors((int) $product['id']);
$flavorStockMap = [];

foreach ($productFlavors as $productFlavor) {
    $flavorStockMap[storefront_normalize_flavor((string) ($productFlavor['nome'] ?? ''))] = max(0, (int) ($productFlavor['estoque'] ?? 0));
}

$flavors = $productFlavors !== []
    ? array_map(static fn(array $flavor): string => (string) $flavor['nome'], $productFlavors)
    : parse_product_flavors($product['sabores'] ?? null);
$flavorSelectId = 'product-view-flavor-' . (int) $product['id'];
$cartFlavorId = 'product-view-cart-flavor-' . (int) $product['id'];
$detailsId = 'product-view-details-' . (int) $product['id'];
$description = trim((string) ($product['descricao'] ?? ''));
$stock = (int) ($product['estoque'] ?? 0);
$cartRedirect = current_app_path_with_query();
$hasDiscount = product_has_discount($product);
$finalPrice = product_final_price($product);
$originalPrice = product_original_price($product);

$productReference = str_pad((string) $product['id'], 6, '0', STR_PAD_LEFT);
$selectionRequired = $flavors !== [];
$resolveFlavorStock = static function (string $flavor) use ($flavorStockMap, $productFlavors, $stock): int {
    $normalizedFlavor = storefront_normalize_flavor($flavor);

    if (array_key_exists($normalizedFlavor, $flavorStockMap)) {
        return max(0, (int) $flavorStockMap[$normalizedFlavor]);
    }

    if ($productFlavors === []) {
        return max(0, $stock);
    }

    return 0;
};
$isAvailable = $selectionRequired
    ? count(array_filter($flavors, static fn(string $flavor): bool => $resolveFlavorStock($flavor) > 0)) > 0
    : $stock > 0;
$currentCustomer = current_customer();
$canUseCart = storefront_customer_can_use_cart($currentCustomer);
$showSubmitButton = !$selectionRequired;
$relatedUrl = '';
$relatedLabel = '';
$canUseFavorites = $currentCustomer !== null
    && (int) ($currentCustomer['ativo'] ?? 0) === 1
    && !empty($currentCustomer['email_verificado_em']);
$isFavorite = $canUseFavorites
    ? customer_has_favorite_product((int) ($currentCustomer['id'] ?? 0), (int) ($product['id'] ?? 0))
    : false;
$favoriteRedirectUrl = app_url('entrar.php');
$shareUrl = storefront_product_url((string) ($product['slug'] ?? ''));
$shareImageUrl = !empty($product['imagem'])
    ? app_url((string) $product['imagem'])
    : app_url('logo.png');
$shareTitle = trim((string) ($product['nome'] ?? 'Produto'));
$shareText = 'Olha essa peca da ' . trim((string) ($storeSettings['nome_estabelecimento'] ?? 'loja')) . ': ' . $shareTitle;
$shareMessage = $shareText . ' ' . $shareUrl;
$shareWhatsAppUrl = 'https://wa.me/?text=' . rawurlencode($shareMessage);
$shareTelegramUrl = 'https://t.me/share/url?url=' . rawurlencode($shareUrl) . '&text=' . rawurlencode($shareText);
$shareFacebookUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl);
$sharePinterestUrl = 'https://pinterest.com/pin/create/button/?url=' . rawurlencode($shareUrl) . '&media=' . rawurlencode($shareImageUrl) . '&description=' . rawurlencode($shareText);

if (!empty($product['marca_slug'])) {
    $relatedUrl = storefront_brand_url((string) $product['marca_slug']);
    $relatedLabel = 'Ver mais da marca';
} elseif (!empty($product['categoria_slug'])) {
    $relatedUrl = storefront_category_url((string) $product['categoria_slug']);
    $relatedLabel = 'Ver mais da categoria';
}

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <nav class="storefront-product-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(app_url()); ?>" data-instant-nav>Inicio</a>
        <?php if (!empty($category['slug'])): ?>
            <span>/</span>
            <a href="<?= e(storefront_category_url((string) $category['slug'])); ?>" data-instant-nav><?= e($category['nome']); ?></a>
        <?php endif; ?>
        <span>/</span>
        <span><?= e($product['nome']); ?></span>
    </nav>

    <section class="storefront-product-detail storefront-product-detail--editorial" data-product-order-scope>
        <div class="storefront-product-detail__gallery-shell<?= $hasGalleryThumbs ? ' has-thumbs' : ''; ?>" data-product-gallery-scope>
            <?php if ($hasGalleryThumbs): ?>
                <div class="storefront-product-detail__thumbs">
                    <?php foreach ($productGallery as $index => $galleryItem): ?>
                        <button
                            class="storefront-product-detail__thumb<?= $index === 0 ? ' is-active' : ''; ?>"
                            type="button"
                            data-product-gallery-thumb
                            data-image-src="<?= e(app_url($galleryItem['src'])); ?>"
                            data-image-alt="<?= e($product['nome']); ?>"
                            aria-label="Ver <?= e($galleryItem['label']); ?>"
                        >
                            <img src="<?= e(app_url($galleryItem['src'])); ?>" alt="<?= e($galleryItem['label']); ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="storefront-product-detail__gallery">
                <div class="storefront-product-detail__main">
                    <img
                        src="<?= e(app_url($productGallery[0]['src'])); ?>"
                        alt="<?= e($product['nome']); ?>"
                        data-product-gallery-main
                    >
                </div>
            </div>
        </div>

        <div class="storefront-product-detail__info">
            <div class="storefront-product-detail__header">
                <a class="storefront-product-detail__backlink" href="<?= e(app_url()); ?>" data-instant-nav>Voltar para a vitrine</a>
                <?php if ($hasBrandMeta): ?>
                    <span class="storefront-product-detail__eyebrow"><?= e($brandName); ?></span>
                <?php endif; ?>
                <h1><?= e($product['nome']); ?></h1>
                <div class="storefront-product-detail__subheader">
                    <?php if ($showCategoryMeta): ?>
                        <span><?= e($category['nome']); ?></span>
                    <?php endif; ?>
                    <span>Ref. MT<?= e($productReference); ?></span>
                </div>
            </div>

            <div class="storefront-product-detail__price">
                <?php if ($hasDiscount): ?>
                    <span class="storefront-product-detail__discount"><?= e(product_discount_badge_label($product)); ?></span>
                    <small class="storefront-product-detail__price-old"><?= e(format_currency($originalPrice)); ?></small>
                <?php endif; ?>
                <strong><?= e(format_currency($finalPrice)); ?></strong>
                <small><?= e($canUseCart ? 'Selecione o tamanho para liberar a compra.' : 'Entre ou crie sua conta para comprar.'); ?></small>
            </div>

            <?php if ($flavors !== []): ?>
                <div class="storefront-product-card__flavors storefront-product-detail__flavors">
                    <div class="storefront-product-detail__size-heading">
                        <?php if ($canUseCart): ?>
                            <label for="<?= e($flavorSelectId); ?>">Tamanho</label>
                        <?php else: ?>
                            <span class="storefront-product-detail__size-label">Tamanho</span>
                        <?php endif; ?>
                        <span><?= e($canUseCart ? 'Escolha sua medida ideal' : 'Veja as medidas disponiveis'); ?></span>
                    </div>
                    <?php if ($canUseCart): ?>
                        <select
                            id="<?= e($flavorSelectId); ?>"
                            class="storefront-product-detail__native-select"
                            data-product-flavor-select
                            data-product-flavor-required="true"
                            aria-hidden="true"
                            tabindex="-1"
                        >
                            <option value="" data-stock="0">Selecione um tamanho</option>

                            <?php foreach ($flavors as $flavor): ?>
                                <?php
                                    $flavorStock = $resolveFlavorStock($flavor);
                                    $flavorUnavailable = $flavorStock <= 0;
                                ?>
                                <option
                                    value="<?= e($flavor); ?>"
                                    data-stock="<?= e((string) $flavorStock); ?>"
                                    <?= $flavorUnavailable ? 'disabled' : ''; ?>
                                >
                                    <?= e($flavor); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <div class="storefront-product-detail__size-list" data-product-size-list>
                        <?php foreach ($flavors as $flavor): ?>
                            <?php
                                $flavorStock = $resolveFlavorStock($flavor);
                                $flavorUnavailable = $flavorStock <= 0;
                            ?>
                            <?php if ($canUseCart): ?>
                                <button
                                    class="storefront-product-detail__size-option<?= $flavorUnavailable ? ' is-unavailable' : ''; ?>"
                                    type="button"
                                    data-product-size-option
                                    data-product-size-value="<?= e($flavor); ?>"
                                    aria-pressed="false"
                                    <?= $flavorUnavailable ? 'disabled aria-disabled="true"' : ''; ?>
                                >
                                    <?= e($flavor); ?>
                                </button>
                            <?php else: ?>
                                <span class="storefront-product-detail__size-option storefront-product-detail__size-option--static<?= $flavorUnavailable ? ' is-unavailable' : ''; ?>">
                                    <?= e($flavor); ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($canUseCart): ?>
                        <small class="storefront-product-card__flavor-hint" data-product-flavor-hint hidden>
                            Escolha um tamanho para continuar.
                        </small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($canUseCart): ?>
                <form
                    method="post"
                    action="<?= e(app_url('carrinho.php')); ?>"
                    class="storefront-product-detail__cart-form"
                    data-product-cart-form
                >
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= e((string) $product['id']); ?>">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="flavor" value="" data-product-cart-flavor-input id="<?= e($cartFlavorId); ?>">
                    <input type="hidden" name="redirect_to" value="<?= e($cartRedirect); ?>">

                    <div class="storefront-product-detail__purchase-actions">
                        <?php if ($canUseFavorites): ?>
                            <button
                                class="storefront-product-detail__favorite<?= $isFavorite ? ' is-active' : ''; ?>"
                                type="button"
                                aria-label="<?= e($isFavorite ? 'Remover dos salvos' : 'Salvar nos seus salvos'); ?>"
                                aria-pressed="<?= $isFavorite ? 'true' : 'false'; ?>"
                                data-favorite-toggle
                                data-favorite-url="<?= e(app_url('favoritos.php')); ?>"
                                data-favorite-product-id="<?= e((string) ($product['id'] ?? 0)); ?>"
                                data-favorite-csrf="<?= e(csrf_token()); ?>"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 21.35 10.55 20C5.4 15.24 2 12.09 2 8.23 2 5.08 4.42 3 7.44 3c1.71 0 3.35.8 4.56 2.09C13.21 3.8 14.85 3 16.56 3 19.58 3 22 5.08 22 8.23c0 3.86-3.4 7.01-8.55 11.78L12 21.35Z"></path>
                                </svg>
                                <span class="sr-only" data-favorite-label><?= e($isFavorite ? 'Remover dos salvos' : 'Salvar nos seus salvos'); ?></span>
                            </button>
                        <?php endif; ?>

                        <button
                            class="btn btn--primary storefront-product-detail__submit"
                            type="submit"
                            data-product-submit
                            <?= $showSubmitButton ? '' : 'hidden'; ?>
                            <?= !$isAvailable && !$selectionRequired ? 'disabled' : ''; ?>
                        >
                            Adicionar ao carrinho
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="storefront-product-detail__guest-cta">
                    <a class="btn btn--primary storefront-product-detail__submit storefront-product-detail__submit--guest" href="<?= e(app_url('entrar.php')); ?>">
                        Entrar ou criar conta
                    </a>
                </div>
            <?php endif; ?>

            <div class="storefront-product-detail__secondary-actions">
                <div
                    class="storefront-product-share"
                    data-share-inline-root
                    data-share-title="<?= e($shareTitle); ?>"
                    data-share-text="<?= e($shareText); ?>"
                    data-share-url="<?= e($shareUrl); ?>"
                >
                    <span class="storefront-product-share__label">Curtiu? Compartilhe esta peca!</span>

                    <div class="storefront-product-share__actions">
                        <button
                            class="storefront-product-share__icon"
                            type="button"
                            aria-label="Instagram e mais apps"
                            title="Instagram e mais apps"
                            data-share-native
                            hidden
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 3v18M3 12h18M5.64 5.64l12.72 12.72M18.36 5.64 5.64 18.36"></path>
                            </svg>
                        </button>

                        <a class="storefront-product-share__icon" href="<?= e($shareWhatsAppUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="Compartilhar no WhatsApp" title="Compartilhar no WhatsApp">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M16.75 13.96c-.25-.13-1.47-.72-1.7-.8-.23-.08-.4-.12-.57.13-.17.25-.65.8-.8.96-.15.17-.29.19-.54.06-.25-.13-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.38-.44.12-.15.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.57-1.37-.78-1.87-.21-.5-.42-.43-.57-.44h-.49c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.44 1.02 2.61c.13.17 1.77 2.69 4.29 3.77.6.26 1.07.41 1.43.52.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.17.21-.57.21-1.06.15-1.17-.06-.11-.23-.17-.48-.29ZM12.04 2C6.52 2 2.04 6.37 2.04 11.77c0 1.75.47 3.39 1.29 4.82L2 22l5.57-1.46a10.15 10.15 0 0 0 4.47 1.03c5.52 0 10-4.37 10-9.77C22.04 6.37 17.56 2 12.04 2Z"></path>
                            </svg>
                        </a>
                        <a class="storefront-product-share__icon" href="<?= e($shareTelegramUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="Compartilhar no Telegram" title="Compartilhar no Telegram">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M21.5 4.5 18.3 19a1 1 0 0 1-1.5.63l-4.37-3.02-2.24 2.15a.8.8 0 0 1-1.36-.45l-.32-4.14L18.2 6.2 6.27 13.6 2.9 12.52a.94.94 0 0 1-.06-1.78L20.2 4.1a1 1 0 0 1 1.3.4Z"></path>
                            </svg>
                        </a>
                        <a class="storefront-product-share__icon" href="<?= e($shareFacebookUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="Compartilhar no Facebook" title="Compartilhar no Facebook">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M13.5 21v-7h2.33l.35-2.73H13.5V9.53c0-.79.22-1.33 1.35-1.33h1.44V5.77c-.25-.03-1.11-.1-2.11-.1-2.09 0-3.52 1.28-3.52 3.63v1.97H8.3V14h2.36v7h2.84Z"></path>
                            </svg>
                        </a>
                        <a class="storefront-product-share__icon" href="<?= e($sharePinterestUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="Compartilhar no Pinterest" title="Compartilhar no Pinterest">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12.04 2C6.9 2 4 5.67 4 9.67c0 2.3 1.3 5.17 3.38 6.08.32.14.49.08.56-.22.05-.22.34-1.36.45-1.77.04-.14.02-.27-.1-.4-.63-.76-1.15-2.15-1.15-3.45 0-3.33 2.48-6.3 6.72-6.3 3.67 0 5.7 2.24 5.7 5.23 0 3.93-1.74 7.25-4.33 7.25-1.43 0-2.5-1.18-2.16-2.63.41-1.72 1.22-3.58 1.22-4.82 0-1.11-.6-2.03-1.82-2.03-1.45 0-2.61 1.5-2.61 3.5 0 1.28.43 2.15.43 2.15l-1.74 7.38c-.51 2.18-.08 4.85-.04 5.12.02.16.23.2.32.08.13-.16 1.74-2.15 2.28-4.14.15-.56.88-3.42.88-3.42.43.82 1.7 1.54 3.04 1.54 4 0 6.71-3.65 6.71-8.54C20.99 5.44 17.18 2 12.04 2Z"></path>
                            </svg>
                        </a>
                        <button
                            class="storefront-product-share__copy"
                            type="button"
                            data-copy-text="<?= e($shareUrl); ?>"
                            data-copy-label-default="Copiar link"
                            data-copy-label-success="Link copiado"
                            data-copy-label-error="Nao foi possivel copiar"
                        >
                            Copiar link
                        </button>
                    </div>
                </div>

                <?php if ($relatedUrl !== ''): ?>
                    <a class="btn btn--outline" href="<?= e($relatedUrl); ?>"<?= str_contains($relatedUrl, 'categoria.php') ? ' data-instant-nav' : ''; ?>>
                        <?= e($relatedLabel); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($description !== ''): ?>
                <section class="storefront-product-detail__about" aria-labelledby="<?= e($detailsId); ?>">
                    <h2 id="<?= e($detailsId); ?>">Sobre o produto</h2>
                    <div class="storefront-product-detail__description">
                        <div><?= nl2br(e($description)); ?></div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
