<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

if (is_post() && posted_value('action') === 'delete') {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido para exclusao.');
        redirect('admin/tamanhos.php');
    }

    $flavorId = (int) posted_value('id');
    $flavor = find_flavor($flavorId);

    if (!$flavor) {
        set_flash('error', 'Tamanho nao encontrado.');
        redirect('admin/tamanhos.php');
    }

    $productIdsStatement = db()->prepare(
        'SELECT DISTINCT produto_id
         FROM produto_sabores
         WHERE sabor_id = :sabor_id'
    );
    $productIdsStatement->execute(['sabor_id' => $flavorId]);
    $productIds = array_map(
        static fn(mixed $value): int => (int) $value,
        $productIdsStatement->fetchAll(PDO::FETCH_COLUMN) ?: []
    );

    $statement = db()->prepare('DELETE FROM sabores WHERE id = :id');
    $statement->execute(['id' => $flavorId]);

    foreach ($productIds as $productId) {
        sync_product_flavor_cache($productId);
    }

    set_flash('success', 'Tamanho removido com sucesso.');
    redirect('admin/tamanhos.php');
}

$currentAdminPage = 'sabores';
$pageTitle = 'Tamanhos';

$flavors = db()->query(
    'SELECT s.*,
            (SELECT COUNT(*) FROM produto_sabores ps WHERE ps.sabor_id = s.id) AS total_produtos
     FROM sabores s
     ORDER BY s.ordem ASC, s.nome ASC'
)->fetchAll();

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">organizacao</p>
            <h2>Lista de tamanhos</h2>
        </div>
        <a class="button button--primary" href="<?= e(app_url('admin/tamanho_form.php')); ?>">Novo tamanho</a>
    </div>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Slug</th>
                    <th>Ordem</th>
                    <th>Status</th>
                    <th>Produtos</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$flavors): ?>
                    <tr>
                        <td colspan="6" class="table-empty">Nenhum tamanho cadastrado.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($flavors as $flavor): ?>
                    <tr>
                        <td><?= e($flavor['nome']); ?></td>
                        <td><?= e($flavor['slug']); ?></td>
                        <td><?= e((string) $flavor['ordem']); ?></td>
                        <td>
                            <span class="status-pill <?= (int) $flavor['ativo'] === 1 ? 'status-pill--success' : 'status-pill--neutral'; ?>">
                                <?= (int) $flavor['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td><?= e((string) $flavor['total_produtos']); ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="button button--ghost button--small" href="<?= e(app_url('admin/tamanho_form.php?id=' . $flavor['id'])); ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('Excluir este tamanho? Os produtos continuam cadastrados e apenas perdem esse vinculo.');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $flavor['id']); ?>">
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
