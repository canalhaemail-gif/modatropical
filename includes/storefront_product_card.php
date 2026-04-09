<?php
declare(strict_types=1);

$category = $category ?? [
    'nome' => $product['categoria_nome'] ?? 'Categoria',
    'slug' => $product['categoria_slug'] ?? '',
];
$brandName = storefront_brand_display_name($product);
$productUrl = storefront_product_url((string) ($product['slug'] ?? ''));
$cardName = storefront_product_card_name($product);
$flavors = storefront_product_available_flavors($product);
$searchIndexSource = trim(
    $product['nome']
    . ' ' . ($product['nome_curto'] ?? '')
    . ' ' . ($product['descricao'] ?? '')
    . ' ' . $category['nome']
    . ' ' . $brandName
    . ' ' . implode(' ', $flavors)
);
$searchIndex = function_exists('mb_strtolower')
    ? mb_strtolower($searchIndexSource, 'UTF-8')
    : strtolower($searchIndexSource);
$hasDiscount = product_has_discount($product);
$cardBadges = [];

if ((int) ($product['destaque'] ?? 0) === 1) {
    $cardBadges[] = [
        'label' => 'Destaque',
        'class' => 'is-featured',
    ];
}

if ((int) ($product['promocao'] ?? 0) === 1) {
    $cardBadges[] = [
        'label' => 'Promo',
        'class' => 'is-promo',
    ];
}

if ($hasDiscount) {
    $cardBadges[] = [
        'label' => (string) product_discount_badge_label($product),
        'class' => 'is-discount',
    ];
}

if ($cardBadges === []) {
    $cardBadges[] = [
        'label' => 'Tendencia',
        'class' => 'is-trending',
    ];
}
?>
<article
    class="product-card storefront-product-card"
    data-product-card
    data-product-view-url="<?= e($productUrl); ?>"
    data-search-index="<?= e($searchIndex); ?>"
>
    <a class="product-card__image-wrap storefront-product-card__image-wrap storefront-product-card__media-link" href="<?= e($productUrl); ?>">
        <span class="storefront-product-card__image-frame">
            <?php if (!empty($product['imagem'])): ?>
                <img
                    class="product-card__image"
                    src="<?= e(app_url($product['imagem'])); ?>"
                    alt="<?= e($product['nome']); ?>"
                    loading="lazy"
                >
            <?php else: ?>
                <div class="product-card__image product-card__image--placeholder">
                    <span>sem imagem</span>
                </div>
            <?php endif; ?>
        </span>
    </a>

    <div class="product-card__body storefront-product-card__body">
        <div class="product-card__title-row">
            <h3><a href="<?= e($productUrl); ?>"><?= e($cardName); ?></a></h3>
        </div>

        <div class="storefront-product-card__badge-row" aria-label="Marcadores do produto">
            <?php foreach ($cardBadges as $badge): ?>
                <span class="storefront-product-card__badge <?= e($badge['class']); ?>"><?= e($badge['label']); ?></span>
            <?php endforeach; ?>
        </div>

        <a class="storefront-product-card__details-toggle storefront-product-card__cta" href="<?= e($productUrl); ?>">
            Ver produto
        </a>
    </div>
</article>
