<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

extract(storefront_build_context(), EXTR_SKIP);

$query = trim((string) ($_GET['q'] ?? ''));
$searchResults = storefront_search_products($query);
$pageTitle = 'Buscar produtos | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body';
$catalogSearchValue = $query;

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <section class="storefront-page-heading storefront-page-heading--brand">
        <div class="storefront-page-heading__content">
            <span class="storefront-toolbar__eyebrow">busca da loja</span>
            <h1>Resultados da busca</h1>
            <?php if ($query !== ''): ?>
                <p>Encontramos <?= e((string) count($searchResults)); ?> item(ns) para "<?= e($query); ?>".</p>
            <?php else: ?>
                <p>Digite um nome, descricao, marca, categoria ou sabor para encontrar produtos mais rapido.</p>
            <?php endif; ?>
        </div>

        <div class="storefront-page-heading__actions">
            <a class="btn btn--light" href="<?= e(app_url()); ?>">Voltar para a home</a>
        </div>
    </section>

    <main class="storefront-catalog catalog">
        <?php if ($query === ''): ?>
            <section class="empty-state">
                <strong>Digite algo para buscar.</strong>
                <p>Use a barra acima para procurar produtos pelo nome, descricao, marca, categoria ou sabor.</p>
            </section>
        <?php elseif ($searchResults === []): ?>
            <section class="empty-state">
                <strong>Nenhum produto encontrado.</strong>
                <p>Tente outro termo relacionado ao produto que voce quer pedir.</p>
            </section>
        <?php else: ?>
            <section class="catalog-section storefront-category">
                <div class="catalog-section__header storefront-category__header">
                    <div>
                        <span class="catalog-section__eyebrow">resultado geral</span>
                        <h2><?= e((string) count($searchResults)); ?> produto(s) encontrado(s)</h2>
                        <p class="storefront-category__description">A busca considera nome, descricao, marca, categoria e sabores cadastrados.</p>
                    </div>
                </div>

                <div class="product-grid storefront-product-grid">
                    <?php foreach ($searchResults as $product): ?>
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
