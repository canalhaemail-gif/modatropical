<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

extract(storefront_build_context(), EXTR_SKIP);

$slug = trim((string) ($_GET['slug'] ?? ''));
$currentCategory = storefront_find_category_by_slug($visibleCategories, $slug);

if ($currentCategory === null) {
    set_flash('error', 'Categoria nao encontrada.');
    redirect('');
}

$pageTitle = ($currentCategory['nome'] ?? 'Categoria') . ' | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body';
$currentCategorySlug = $currentCategory['slug'] ?? null;
$categoryProducts = $productsByCategory[(int) $currentCategory['id']] ?? [];
$categoryHeadingImageMap = [
    'blusinhas' => 'blusinhas.png',
    'macac-oes' => 'macacoes.png',
    'macacoes' => 'macacoes.png',
    'inverno' => 'inverno.png',
    'calcas' => 'calças.png',
    'calças' => 'calças.png',
];
$categoryHeadingImageFile = $categoryHeadingImageMap[$currentCategorySlug] ?? null;
$categoryHeadingImagePath = $categoryHeadingImageFile !== null ? BASE_PATH . '/' . $categoryHeadingImageFile : null;
$categoryHeadingImageRenderUrl = ($categoryHeadingImagePath !== null && is_file($categoryHeadingImagePath))
    ? preferred_critical_image_render_url((string) $categoryHeadingImageFile)
    : null;

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <section class="storefront-page-heading storefront-page-heading--centered storefront-page-heading--with-back storefront-page-heading--category-feature">
        <a class="btn btn--light storefront-page-heading__back" href="<?= e(app_url()); ?>" data-instant-nav>Voltar para a home</a>

        <div class="storefront-page-heading__content">
            <?php if ($categoryHeadingImageRenderUrl !== null): ?>
                <img
                    class="storefront-page-heading__title-image storefront-page-heading__title-image--category"
                    src="<?= e($categoryHeadingImageRenderUrl); ?>"
                    alt="<?= e($currentCategory['nome']); ?>"
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                >
            <?php else: ?>
                <h1><?= e($currentCategory['nome']); ?></h1>
            <?php endif; ?>
        </div>
    </section>

    <main class="storefront-catalog catalog">
        <section class="empty-state storefront-search-empty" data-search-empty hidden>
            <strong>Nenhum item encontrado.</strong>
            <p>Tente outro nome ou termo de busca.</p>
        </section>

        <section class="catalog-section storefront-category" data-search-section>
            <?php if ($categoryProducts === []): ?>
                <div class="empty-state">
                    <strong>Nenhum produto ativo encontrado nesta categoria.</strong>
                    <p>Publique produtos no painel administrativo para preencher esta pagina.</p>
                </div>
            <?php else: ?>
                <div class="product-grid storefront-product-grid">
                    <?php foreach ($categoryProducts as $product): ?>
                        <?php $category = $currentCategory; ?>
                        <?php require BASE_PATH . '/includes/storefront_product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
