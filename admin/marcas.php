<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

if (is_post() && posted_value('action') === 'delete') {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido para exclusao.');
        redirect('admin/marcas.php');
    }

    $brandId = (int) posted_value('id');
    $brand = find_brand($brandId);

    if (!$brand) {
        set_flash('error', 'Marca nao encontrada.');
        redirect('admin/marcas.php');
    }

    delete_uploaded_file($brand['imagem'] ?? null);

    $statement = db()->prepare('DELETE FROM marcas WHERE id = :id');
    $statement->execute(['id' => $brandId]);

    set_flash('success', 'Marca removida com sucesso. Produtos vinculados continuam cadastrados sem marca.');
    redirect('admin/marcas.php');
}

$currentAdminPage = 'marcas';
$pageTitle = 'Marcas';

$brands = db()->query(
    'SELECT m.*,
            (SELECT COUNT(*) FROM produtos p WHERE p.marca_id = m.id) AS total_produtos
     FROM marcas m
     ORDER BY m.ordem ASC, m.nome ASC'
)->fetchAll();

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">organizacao</p>
            <h2>Lista de marcas</h2>
        </div>
        <a class="button button--primary" href="<?= e(app_url('admin/marca_form.php')); ?>">Nova marca</a>
    </div>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Imagem</th>
                    <th>Nome</th>
                    <th>Slug</th>
                    <th>Ordem</th>
                    <th>Status</th>
                    <th>Produtos</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$brands): ?>
                    <tr>
                        <td colspan="7" class="table-empty">Nenhuma marca cadastrada.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($brands as $brand): ?>
                    <tr>
                        <td>
                            <?php if (!empty($brand['imagem'])): ?>
                                <img class="table-thumb" src="<?= e(app_url($brand['imagem'])); ?>" alt="<?= e($brand['nome']); ?>">
                            <?php else: ?>
                                <div class="table-thumb table-thumb--placeholder">sem imagem</div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($brand['nome']); ?></td>
                        <td><?= e($brand['slug']); ?></td>
                        <td><?= e((string) $brand['ordem']); ?></td>
                        <td>
                            <span class="status-pill <?= (int) $brand['ativa'] === 1 ? 'status-pill--success' : 'status-pill--neutral'; ?>">
                                <?= (int) $brand['ativa'] === 1 ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                        <td><?= e((string) $brand['total_produtos']); ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="button button--ghost button--small" href="<?= e(app_url('admin/marca_form.php?id=' . $brand['id'])); ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('Excluir esta marca? Os produtos vinculados permanecem cadastrados sem marca.');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $brand['id']); ?>">
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
