<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

$settings = fetch_store_settings();
$settingsId = isset($settings['id']) ? (int) $settings['id'] : 0;

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido.');
        redirect('admin/configuracoes.php');
    }

    $name = trim((string) posted_value('nome_estabelecimento'));
    $description = trim((string) posted_value('descricao_loja'));
    $phone = trim((string) posted_value('telefone_whatsapp'));
    $address = trim((string) posted_value('endereco'));
    $hours = trim((string) posted_value('horario_funcionamento'));
    $primary = trim((string) posted_value('cor_primaria'));
    $secondary = trim((string) posted_value('cor_secundaria'));
    $removeLogo = posted_value('remover_logo') ? 1 : 0;

    if ($name === '') {
        set_flash('error', 'Informe o nome do estabelecimento.');
        redirect('admin/configuracoes.php');
    }

    if (!preg_match('/^#[a-f0-9]{6}$/i', $primary)) {
        $primary = '#4d137a';
    }

    if (!preg_match('/^#[a-f0-9]{6}$/i', $secondary)) {
        $secondary = '#8f5cff';
    }

    $logoPath = $settings['logo'] ?? null;

    if ($removeLogo && $logoPath) {
        delete_uploaded_file($logoPath);
        $logoPath = null;
    }

    try {
        $logoPath = handle_image_upload('logo', 'store', $logoPath);
    } catch (RuntimeException $exception) {
        set_flash('error', $exception->getMessage());
        redirect('admin/configuracoes.php');
    }

    if ($settingsId > 0) {
        $statement = db()->prepare(
            'UPDATE configuracoes
             SET nome_estabelecimento = :nome_estabelecimento,
                 descricao_loja = :descricao_loja,
                 logo = :logo,
                 telefone_whatsapp = :telefone_whatsapp,
                 endereco = :endereco,
                 horario_funcionamento = :horario_funcionamento,
                 cor_primaria = :cor_primaria,
                 cor_secundaria = :cor_secundaria
             WHERE id = :id'
        );

        $statement->execute([
            'nome_estabelecimento' => $name,
            'descricao_loja' => $description,
            'logo' => $logoPath,
            'telefone_whatsapp' => $phone,
            'endereco' => $address,
            'horario_funcionamento' => $hours,
            'cor_primaria' => $primary,
            'cor_secundaria' => $secondary,
            'id' => $settingsId,
        ]);
    } else {
        $statement = db()->prepare(
            'INSERT INTO configuracoes (
                nome_estabelecimento,
                descricao_loja,
                logo,
                telefone_whatsapp,
                endereco,
                horario_funcionamento,
                cor_primaria,
                cor_secundaria
             ) VALUES (
                :nome_estabelecimento,
                :descricao_loja,
                :logo,
                :telefone_whatsapp,
                :endereco,
                :horario_funcionamento,
                :cor_primaria,
                :cor_secundaria
             )'
        );

        $statement->execute([
            'nome_estabelecimento' => $name,
            'descricao_loja' => $description,
            'logo' => $logoPath,
            'telefone_whatsapp' => $phone,
            'endereco' => $address,
            'horario_funcionamento' => $hours,
            'cor_primaria' => $primary,
            'cor_secundaria' => $secondary,
        ]);
    }

    set_flash('success', 'Configuracoes atualizadas com sucesso.');
    redirect('admin/configuracoes.php');
}

$currentAdminPage = 'configuracoes';
$pageTitle = 'Configuracoes da Loja';

$formData = [
    'nome_estabelecimento' => (string) posted_value('nome_estabelecimento', $settings['nome_estabelecimento'] ?? ''),
    'descricao_loja' => (string) posted_value('descricao_loja', $settings['descricao_loja'] ?? ''),
    'logo' => (string) ($settings['logo'] ?? ''),
    'telefone_whatsapp' => (string) posted_value('telefone_whatsapp', $settings['telefone_whatsapp'] ?? ''),
    'endereco' => (string) posted_value('endereco', $settings['endereco'] ?? ''),
    'horario_funcionamento' => (string) posted_value('horario_funcionamento', $settings['horario_funcionamento'] ?? ''),
    'cor_primaria' => (string) posted_value('cor_primaria', $settings['cor_primaria'] ?? '#D97A6C'),
    'cor_secundaria' => (string) posted_value('cor_secundaria', $settings['cor_secundaria'] ?? '#97B39B'),
];

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--form">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">marca e operacao</p>
            <h2>Configuracoes gerais</h2>
        </div>
    </div>

    <form method="post" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

        <div class="form-grid">
            <div class="form-row">
                <label for="nome_estabelecimento">Nome do estabelecimento</label>
                <input id="nome_estabelecimento" name="nome_estabelecimento" type="text" required value="<?= e($formData['nome_estabelecimento']); ?>">
            </div>

            <div class="form-row">
                <label for="telefone_whatsapp">Telefone / WhatsApp</label>
                <input id="telefone_whatsapp" name="telefone_whatsapp" type="text" value="<?= e($formData['telefone_whatsapp']); ?>" placeholder="5511999999999">
            </div>

            <div class="form-row form-row--wide">
                <label for="descricao_loja">Descricao da loja</label>
                <textarea id="descricao_loja" name="descricao_loja" rows="5"><?= e($formData['descricao_loja']); ?></textarea>
            </div>

            <div class="form-row">
                <label for="endereco">Endereco</label>
                <input id="endereco" name="endereco" type="text" value="<?= e($formData['endereco']); ?>">
            </div>

            <div class="form-row">
                <label for="horario_funcionamento">Horario de funcionamento</label>
                <input id="horario_funcionamento" name="horario_funcionamento" type="text" value="<?= e($formData['horario_funcionamento']); ?>">
            </div>

            <div class="form-row">
                <label for="cor_primaria">Cor primaria</label>
                <div class="color-field">
                    <input id="cor_primaria" name="cor_primaria" type="color" value="<?= e($formData['cor_primaria']); ?>">
                    <input type="text" value="<?= e($formData['cor_primaria']); ?>" readonly>
                </div>
            </div>

            <div class="form-row">
                <label for="cor_secundaria">Cor secundaria</label>
                <div class="color-field">
                    <input id="cor_secundaria" name="cor_secundaria" type="color" value="<?= e($formData['cor_secundaria']); ?>">
                    <input type="text" value="<?= e($formData['cor_secundaria']); ?>" readonly>
                </div>
            </div>

            <div class="form-row">
                <label for="logo">Logo da loja</label>
                <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp" data-image-input="#logo-preview">
            </div>

            <div class="form-row form-row--preview">
                <label>Preview do logo</label>
                <div class="image-preview image-preview--logo" id="logo-preview">
                    <?php if (!empty($formData['logo'])): ?>
                        <img src="<?= e(app_url($formData['logo'])); ?>" alt="Logo atual">
                    <?php else: ?>
                        <span>Nenhum logo enviado.</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($formData['logo'])): ?>
                <div class="form-row form-row--toggle">
                    <label class="checkbox-row">
                        <input name="remover_logo" type="checkbox" value="1">
                        <span>Remover logo atual</span>
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button class="button button--primary" type="submit">Salvar configuracoes</button>
        </div>
    </form>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
