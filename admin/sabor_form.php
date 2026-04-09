<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

$flavorId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $flavorId !== null && $flavorId > 0;
$flavor = $editing ? find_flavor($flavorId) : null;

if ($editing && !$flavor) {
    set_flash('error', 'Tamanho nao encontrado.');
    redirect('admin/tamanhos.php');
}

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido.');
        redirect($editing ? 'admin/tamanho_form.php?id=' . $flavorId : 'admin/tamanho_form.php');
    }

    $name = trim((string) posted_value('nome'));
    $order = (int) posted_value('ordem', 0);
    $active = posted_value('ativo') ? 1 : 0;

    if ($name === '') {
        set_flash('error', 'Informe o nome do tamanho.');
        redirect($editing ? 'admin/tamanho_form.php?id=' . $flavorId : 'admin/tamanho_form.php');
    }

    $slug = generate_unique_slug('sabores', $name, $editing ? $flavorId : null);

    if ($editing) {
        $statement = db()->prepare(
            'UPDATE sabores
             SET nome = :nome, slug = :slug, ordem = :ordem, ativo = :ativo
             WHERE id = :id'
        );

        $statement->execute([
            'nome' => $name,
            'slug' => $slug,
            'ordem' => $order,
            'ativo' => $active,
            'id' => $flavorId,
        ]);

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

        foreach ($productIds as $productId) {
            sync_product_flavor_cache($productId);
        }

        set_flash('success', 'Tamanho atualizado com sucesso.');
        redirect('admin/tamanhos.php');
    }

    $statement = db()->prepare(
        'INSERT INTO sabores (nome, slug, ordem, ativo)
         VALUES (:nome, :slug, :ordem, :ativo)'
    );

    $statement->execute([
        'nome' => $name,
        'slug' => $slug,
        'ordem' => $order,
        'ativo' => $active,
    ]);

    set_flash('success', 'Tamanho criado com sucesso.');
    redirect('admin/tamanhos.php');
}

$currentAdminPage = 'sabores';
$pageTitle = $editing ? 'Editar Tamanho' : 'Novo Tamanho';

$formData = [
    'nome' => (string) posted_value('nome', $flavor['nome'] ?? ''),
    'ordem' => (string) posted_value('ordem', $flavor['ordem'] ?? '0'),
    'ativo' => (string) posted_value('ativo', (string) ($flavor['ativo'] ?? '1')),
];

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--form">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">cadastro</p>
            <h2><?= e($pageTitle); ?></h2>
        </div>
        <a class="button button--ghost" href="<?= e(app_url('admin/tamanhos.php')); ?>">Voltar</a>
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
                    <input name="ativo" type="checkbox" value="1" <?= checked($formData['ativo'], 1); ?>>
                    <span>Tamanho ativo</span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button class="button button--primary" type="submit"><?= $editing ? 'Salvar alteracoes' : 'Criar tamanho'; ?></button>
        </div>
    </form>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
