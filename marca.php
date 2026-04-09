<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

extract(storefront_build_context(), EXTR_SKIP);

$slug = trim((string) ($_GET['slug'] ?? ''));
$currentBrand = storefront_find_brand_by_slug($brands, $slug);

if ($currentBrand === null) {
    set_flash('error', 'Marca nao encontrada.');
    redirect('');
}

$pageTitle = ($currentBrand['nome'] ?? 'Marca') . ' | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body';
$brandProducts = $productsByBrand[(int) $currentBrand['id']] ?? [];
$brandMedia = storefront_brand_media($currentBrand, $brandProducts);

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <section class="storefront-page-heading storefront-page-heading--brand">
        <div class="storefront-page-heading__content">
            <span class="storefront-toolbar__eyebrow">marca</span>
            <h1><?= e($currentBrand['nome']); ?></h1>
            <p>Todos os produtos ativos desta marca ficam reunidos aqui para a navegacao ficar mais limpa e visual.</p>
        </div>

        <div class="storefront-page-heading__actions">
            <span class="storefront-toolbar__status"><?= count($brandProducts); ?> itens encontrados</span>
            <a class="btn btn--light" href="<?= e(app_url()); ?>">Voltar para a home</a>
        </div>
    </section>

    <section class="storefront-brand-hero">
        <div class="storefront-brand-hero__media">
            <img src="<?= e(app_url($brandMedia)); ?>" alt="<?= e($currentBrand['nome']); ?>">
        </div>
        <div class="storefront-brand-hero__body">
            <span class="storefront-toolbar__eyebrow">linha da loja</span>
            <h2><?= e($currentBrand['nome']); ?></h2>
            <p>Explore os itens desta marca e use a busca acima para localizar um produto especifico mais rapido.</p>
        </div>
    </section>

    <main class="storefront-catalog catalog">
        <section class="empty-state storefront-search-empty" data-search-empty hidden>
            <strong>Nenhum item encontrado.</strong>
            <p>Tente outro nome ou termo de busca.</p>
        </section>

        <section class="catalog-section storefront-category" data-search-section>
            <div class="catalog-section__header storefront-category__header">
                <div>
                    <span class="catalog-section__eyebrow">marca atual</span>
                    <h2><?= e($currentBrand['nome']); ?></h2>
                    <p class="storefront-category__description">Todos os produtos ativos desta marca aparecem nesta pagina.</p>
                </div>
                <span class="catalog-section__count"><?= count($brandProducts); ?> itens</span>
            </div>

            <?php if ($brandProducts === []): ?>
                <div class="empty-state">
                    <strong>Nenhum produto ativo encontrado nesta marca.</strong>
                    <p>Vincule produtos a esta marca no painel administrativo para preencher esta pagina.</p>
                </div>
            <?php else: ?>
                <div class="product-grid storefront-product-grid">
                    <?php foreach ($brandProducts as $product): ?>
                        <?php
                        $category = [
                            'nome' => $product['categoria_nome'] ?? 'Categoria',
                            'slug' => $product['categoria_slug'] ?? '',
                        ];
                        require BASE_PATH . '/includes/storefront_product_card.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
