<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

if (is_post() && posted_value('action') === 'delete') {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido para exclusao.');
        redirect('admin/produtos.php');
    }

    $productId = (int) posted_value('id');
    $product = find_product($productId);

    if (!$product) {
        set_flash('error', 'Produto nao encontrado.');
        redirect('admin/produtos.php');
    }

    delete_uploaded_file($product['imagem'] ?? null);
    delete_product_gallery_files($productId);

    $statement = db()->prepare('DELETE FROM produtos WHERE id = :id');
    $statement->execute(['id' => $productId]);

    set_flash('success', 'Produto removido com sucesso.');
    redirect('admin/produtos.php');
}

if (is_post() && posted_value('action') === 'toggle_featured') {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido para atualizar o destaque.');
        redirect('admin/produtos.php');
    }

    $productId = (int) posted_value('id');
    $product = find_product($productId);

    if (!$product) {
        set_flash('error', 'Produto nao encontrado.');
        redirect('admin/produtos.php');
    }

    $nextFeatured = (int) ($product['destaque'] ?? 0) === 1 ? 0 : 1;
    $statement = db()->prepare(
        'UPDATE produtos
         SET destaque = :destaque
         WHERE id = :id'
    );
    $statement->execute([
        'destaque' => $nextFeatured,
        'id' => $productId,
    ]);

    set_flash('success', $nextFeatured === 1
        ? 'Produto marcado como destaque.'
        : 'Produto removido dos destaques.');
    redirect('admin/produtos.php');
}

$currentAdminPage = 'produtos';
$pageTitle = 'Produtos';

$products = db()->query(
    'SELECT p.*, c.nome AS categoria_nome, COALESCE(m.nome, \'Sem marca\') AS marca_nome
     FROM produtos p
     INNER JOIN categorias c ON c.id = p.categoria_id
     LEFT JOIN marcas m ON m.id = p.marca_id
     ORDER BY c.ordem ASC, p.promocao DESC, p.destaque DESC, p.nome ASC'
)->fetchAll();

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">catalogo</p>
            <h2>Lista de produtos</h2>
        </div>
        <a class="button button--primary" href="<?= e(app_url('admin/produto_form.php')); ?>">Novo produto</a>
    </div>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Imagem</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Marca</th>
                    <th>Preco</th>
                    <th>Desconto</th>
                    <th>Estoque</th>
                    <th>Status</th>
                    <th>Destaque</th>
                    <th>Promo</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$products): ?>
                    <tr>
                        <td colspan="11" class="table-empty">Nenhum produto cadastrado.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($products as $product): ?>
                    <tr>
                        <td data-label="Imagem">
                            <?php if (!empty($product['imagem'])): ?>
                                <img class="table-thumb" src="<?= e(app_url($product['imagem'])); ?>" alt="<?= e($product['nome']); ?>">
                            <?php else: ?>
                                <div class="table-thumb table-thumb--placeholder">sem imagem</div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Produto">
                            <strong><?= e($product['nome']); ?></strong>
                            <?php if (trim((string) ($product['nome_curto'] ?? '')) !== ''): ?>
                                <small class="table-subtitle">Card: <?= e((string) $product['nome_curto']); ?></small>
                            <?php endif; ?>
                            <small class="table-subtitle"><?= e($product['slug']); ?></small>
                        </td>
                        <td data-label="Categoria"><?= e($product['categoria_nome']); ?></td>
                        <td data-label="Marca"><?= e($product['marca_nome']); ?></td>
                        <td data-label="Preco">
                            <?php if (product_has_discount($product)): ?>
                                <div class="admin-price-stack">
                                    <small><?= e(format_currency(product_original_price($product))); ?></small>
                                    <strong><?= e(format_currency(product_final_price($product))); ?></strong>
                                </div>
                            <?php else: ?>
                                <?= e(format_currency((float) $product['preco'])); ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Desconto">
                            <span class="status-pill <?= product_has_discount($product) ? 'status-pill--warning' : 'status-pill--neutral'; ?>">
                                <?= product_has_discount($product) ? e(product_discount_badge_label($product)) : 'Sem'; ?>
                            </span>
                        </td>
                        <td data-label="Estoque"><?= e((string) ($product['estoque'] ?? 0)); ?></td>
                        <td data-label="Status">
                            <span class="status-pill <?= (int) $product['ativo'] === 1 ? 'status-pill--success' : 'status-pill--neutral'; ?>">
                                <?= (int) $product['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td data-label="Destaque">
                            <span class="status-pill <?= (int) $product['destaque'] === 1 ? 'status-pill--accent' : 'status-pill--neutral'; ?>">
                                <?= (int) $product['destaque'] === 1 ? 'Sim' : 'Nao'; ?>
                            </span>
                        </td>
                        <td data-label="Promo">
                            <span class="status-pill <?= (int) ($product['promocao'] ?? 0) === 1 ? 'status-pill--warning' : 'status-pill--neutral'; ?>">
                                <?= (int) ($product['promocao'] ?? 0) === 1 ? 'Sim' : 'Nao'; ?>
                            </span>
                        </td>
                        <td data-label="Acoes">
                            <div class="table-actions">
                                <a class="button button--ghost button--small" href="<?= e(app_url('admin/produto_form.php?id=' . $product['id'])); ?>">Editar</a>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="toggle_featured">
                                    <input type="hidden" name="id" value="<?= e((string) $product['id']); ?>">
                                    <button class="button button--ghost button--small" type="submit">
                                        <?= (int) $product['destaque'] === 1 ? 'Remover destaque' : 'Destacar'; ?>
                                    </button>
                                </form>
                                <form method="post" onsubmit="return confirm('Excluir este produto?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $product['id']); ?>">
                                    <button class="button button--danger button--small" type="submit">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
