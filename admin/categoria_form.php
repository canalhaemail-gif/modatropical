<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

$categoryId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $categoryId !== null && $categoryId > 0;
$category = $editing ? find_category($categoryId) : null;

if ($editing && !$category) {
    set_flash('error', 'Categoria nao encontrada.');
    redirect('admin/categorias.php');
}

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido.');
        redirect($editing ? 'admin/categoria_form.php?id=' . $categoryId : 'admin/categoria_form.php');
    }

    $name = trim((string) posted_value('nome'));
    $order = (int) posted_value('ordem', 0);
    $active = posted_value('ativa') ? 1 : 0;

    if ($name === '') {
        set_flash('error', 'Informe o nome da categoria.');
        redirect($editing ? 'admin/categoria_form.php?id=' . $categoryId : 'admin/categoria_form.php');
    }

    $slug = generate_unique_slug('categorias', $name, $editing ? $categoryId : null);

    if ($editing) {
        $statement = db()->prepare(
            'UPDATE categorias
             SET nome = :nome, slug = :slug, ordem = :ordem, ativa = :ativa
             WHERE id = :id'
        );

        $statement->execute([
            'nome' => $name,
            'slug' => $slug,
            'ordem' => $order,
            'ativa' => $active,
            'id' => $categoryId,
        ]);

        set_flash('success', 'Categoria atualizada com sucesso.');
        redirect('admin/categorias.php');
    }

    $statement = db()->prepare(
        'INSERT INTO categorias (nome, slug, ordem, ativa)
         VALUES (:nome, :slug, :ordem, :ativa)'
    );

    $statement->execute([
        'nome' => $name,
        'slug' => $slug,
        'ordem' => $order,
        'ativa' => $active,
    ]);

    set_flash('success', 'Categoria criada com sucesso.');
    redirect('admin/categorias.php');
}

$currentAdminPage = 'categorias';
$pageTitle = $editing ? 'Editar Categoria' : 'Nova Categoria';

$formData = [
    'nome' => (string) posted_value('nome', $category['nome'] ?? ''),
    'ordem' => (string) posted_value('ordem', $category['ordem'] ?? '0'),
    'ativa' => (string) posted_value('ativa', (string) ($category['ativa'] ?? '1')),
];

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--form">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">cadastro</p>
            <h2><?= e($pageTitle); ?></h2>
        </div>
        <a class="button button--ghost" href="<?= e(app_url('admin/categorias.php')); ?>">Voltar</a>
    </div>

    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

        <div class="form-grid">
            <div class="form-row">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" type="text" required value="<?= e($formData['nome']); ?>">
            </div>

            <div class="form-row">
                <label for="ordem">Ordem</label>
                <input id="ordem" name="ordem" type="number" min="0" value="<?= e($formData['ordem']); ?>">
            </div>

            <div class="form-row form-row--toggle">
                <label class="checkbox-row">
                    <input name="ativa" type="checkbox" value="1" <?= checked($formData['ativa'], 1); ?>>
                    <span>Categoria ativa</span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button class="button button--primary" type="submit"><?= $editing ? 'Salvar alteracoes' : 'Criar categoria'; ?></button>
        </div>
    </form>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
