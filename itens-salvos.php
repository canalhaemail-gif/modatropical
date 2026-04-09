<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_auth();

$storeSettings = fetch_store_settings();
$customer = current_customer();
$savedProducts = customer_favorite_products((int) ($customer['id'] ?? 0));
$savedProductsCount = customer_favorite_count((int) ($customer['id'] ?? 0));
$pageTitle = 'Itens Salvos';
$bodyClass = 'storefront-body public-body--customer';
$extraStylesheets = ['assets/css/public-auth.css'];

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <?php require BASE_PATH . '/includes/customer_area_topbar.php'; ?>

    <section class="account-layout account-layout--single">
        <article class="account-card">
            <div class="account-card__header">
                <span class="auth-form-card__badge">Amei</span>
                <h2>Itens salvos</h2>
                <p>As pecas marcadas no coracao ficam guardadas aqui para voce rever depois com calma.</p>
            </div>

            <?php if ($savedProducts !== []): ?>
                <div class="account-saved-grid">
                    <?php foreach ($savedProducts as $savedProduct): ?>
                        <a class="account-saved-item" href="<?= e(storefront_product_url((string) ($savedProduct['slug'] ?? ''))); ?>">
                            <div class="account-saved-item__media">
                                <?php if (!empty($savedProduct['imagem'])): ?>
                                    <img
                                        src="<?= e(app_url((string) ($savedProduct['imagem'] ?? ''))); ?>"
                                        alt="<?= e((string) ($savedProduct['nome'] ?? 'Produto')); ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="account-saved-item__placeholder">Sem imagem</div>
                                <?php endif; ?>
                            </div>

                            <div class="account-saved-item__copy">
                                <strong><?= e((string) ($savedProduct['nome'] ?? 'Produto')); ?></strong>
                                <span><?= e((string) ($savedProduct['categoria_nome'] ?? 'Peca')); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <p class="account-saved-summary">
                    <?= e((string) $savedProductsCount); ?> peca(s) salva(s) na sua conta.
                </p>
            <?php else: ?>
                <div class="account-saved-empty">
                    <strong>Nenhum item salvo ainda.</strong>
                    <p>Quando voce tocar no coracao de um produto, ele vai aparecer nesta area exclusiva.</p>
                </div>
            <?php endif; ?>

            <div class="account-card__actions">
                <a class="btn btn--ghost" href="<?= e(app_url()); ?>">Voltar para a vitrine</a>
                <a class="btn btn--ghost" href="<?= e(app_url('minha-conta.php')); ?>">Minha conta</a>
                <a class="btn btn--ghost" href="<?= e(app_url('meus-pedidos.php')); ?>">Meus pedidos</a>
            </div>
        </article>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
