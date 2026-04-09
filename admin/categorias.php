<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

if (is_post() && posted_value('action') === 'delete') {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido para exclusao.');
        redirect('admin/categorias.php');
    }

    $categoryId = (int) posted_value('id');
    $category = find_category($categoryId);

    if (!$category) {
        set_flash('error', 'Categoria nao encontrada.');
        redirect('admin/categorias.php');
    }

    $statement = db()->prepare('DELETE FROM categorias WHERE id = :id');
    $statement->execute(['id' => $categoryId]);

    set_flash('success', 'Categoria removida com sucesso.');
    redirect('admin/categorias.php');
}

$currentAdminPage = 'categorias';
$pageTitle = 'Categorias';

$categories = db()->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM produtos p WHERE p.categoria_id = c.id) AS total_produtos
     FROM categorias c
     ORDER BY c.ordem ASC, c.nome ASC'
)->fetchAll();

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">organizacao</p>
            <h2>Lista de categorias</h2>
        </div>
        <a class="button button--primary" href="<?= e(app_url('admin/categoria_form.php')); ?>">Nova categoria</a>
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
                <?php if (!$categories): ?>
                    <tr>
                        <td colspan="6" class="table-empty">Nenhuma categoria cadastrada.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= e($category['nome']); ?></td>
                        <td><?= e($category['slug']); ?></td>
                        <td><?= e((string) $category['ordem']); ?></td>
                        <td>
                            <span class="status-pill <?= (int) $category['ativa'] === 1 ? 'status-pill--success' : 'status-pill--neutral'; ?>">
                                <?= (int) $category['ativa'] === 1 ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                        <td><?= e((string) $category['total_produtos']); ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="button button--ghost button--small" href="<?= e(app_url('admin/categoria_form.php?id=' . $category['id'])); ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('Excluir esta categoria e os produtos relacionados?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $category['id']); ?>">
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
