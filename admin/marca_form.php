<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

$brandId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $brandId !== null && $brandId > 0;
$brand = $editing ? find_brand($brandId) : null;

if ($editing && !$brand) {
    set_flash('error', 'Marca nao encontrada.');
    redirect('admin/marcas.php');
}

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido.');
        redirect($editing ? 'admin/marca_form.php?id=' . $brandId : 'admin/marca_form.php');
    }

    $name = trim((string) posted_value('nome'));
    $order = (int) posted_value('ordem', 0);
    $active = posted_value('ativa') ? 1 : 0;
    $removeImage = posted_value('remover_imagem') ? 1 : 0;

    if ($name === '') {
        set_flash('error', 'Informe o nome da marca.');
        redirect($editing ? 'admin/marca_form.php?id=' . $brandId : 'admin/marca_form.php');
    }

    $slug = generate_unique_slug('marcas', $name, $editing ? $brandId : null);

    $imagePath = $brand['imagem'] ?? null;

    if ($removeImage && $editing) {
        delete_uploaded_file($imagePath);
        $imagePath = null;
    }

    try {
        $imagePath = handle_image_upload('imagem', 'brands', $imagePath);
    } catch (RuntimeException $exception) {
        set_flash('error', $exception->getMessage());
        redirect($editing ? 'admin/marca_form.php?id=' . $brandId : 'admin/marca_form.php');
    }

    if ($editing) {
        $statement = db()->prepare(
            'UPDATE marcas
             SET nome = :nome, slug = :slug, imagem = :imagem, ordem = :ordem, ativa = :ativa
             WHERE id = :id'
        );

        $statement->execute([
            'nome' => $name,
            'slug' => $slug,
            'imagem' => $imagePath,
            'ordem' => $order,
            'ativa' => $active,
            'id' => $brandId,
        ]);

        set_flash('success', 'Marca atualizada com sucesso.');
        redirect('admin/marcas.php');
    }

    $statement = db()->prepare(
        'INSERT INTO marcas (nome, slug, imagem, ordem, ativa)
         VALUES (:nome, :slug, :imagem, :ordem, :ativa)'
    );

    $statement->execute([
        'nome' => $name,
        'slug' => $slug,
        'imagem' => $imagePath,
        'ordem' => $order,
        'ativa' => $active,
    ]);

    set_flash('success', 'Marca criada com sucesso.');
    redirect('admin/marcas.php');
}

$currentAdminPage = 'marcas';
$pageTitle = $editing ? 'Editar Marca' : 'Nova Marca';

$formData = [
    'nome' => (string) posted_value('nome', $brand['nome'] ?? ''),
    'ordem' => (string) posted_value('ordem', $brand['ordem'] ?? '0'),
    'ativa' => (string) posted_value('ativa', (string) ($brand['ativa'] ?? '1')),
];

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--form">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">cadastro</p>
            <h2><?= e($pageTitle); ?></h2>
        </div>
        <a class="button button--ghost" href="<?= e(app_url('admin/marcas.php')); ?>">Voltar</a>
    </div>

    <form method="post" class="admin-form" enctype="multipart/form-data">
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

            <div class="form-row">
                <label for="imagem">Imagem da marca</label>
                <input id="imagem" name="imagem" type="file" accept=".jpg,.jpeg,.png,.webp" data-image-input="#marca-preview">
            </div>

            <div class="form-row form-row--preview">
                <label>Preview</label>
                <div class="image-preview" id="marca-preview">
                    <?php if (!empty($brand['imagem'])): ?>
                        <img src="<?= e(app_url($brand['imagem'])); ?>" alt="<?= e($brand['nome'] ?? 'Preview'); ?>">
                    <?php else: ?>
                        <span>Nenhuma imagem selecionada.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row form-row--toggle">
                <label class="checkbox-row">
                    <input name="ativa" type="checkbox" value="1" <?= checked($formData['ativa'], 1); ?>>
                    <span>Marca ativa</span>
                </label>
            </div>

            <?php if ($editing && !empty($brand['imagem'])): ?>
                <div class="form-row form-row--toggle">
                    <label class="checkbox-row">
                        <input name="remover_imagem" type="checkbox" value="1">
                        <span>Remover imagem atual</span>
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button class="button button--primary" type="submit"><?= $editing ? 'Salvar alteracoes' : 'Criar marca'; ?></button>
        </div>
    </form>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
