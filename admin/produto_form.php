<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/customer_messages.php';

require_admin_auth();

function admin_product_total_stock(array $product, array $flavorEntries = []): int
{
    if ($flavorEntries !== []) {
        return calculate_product_flavor_stock($flavorEntries);
    }

    return max(0, (int) ($product['estoque'] ?? 0));
}

$productId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $productId !== null && $productId > 0;
$product = $editing ? find_product($productId) : null;

if ($editing && !$product) {
    set_flash('error', 'Produto nao encontrado.');
    redirect('admin/produtos.php');
}

$categories = db()->query(
    'SELECT id, nome, ativa
     FROM categorias
     ORDER BY ordem ASC, nome ASC'
)->fetchAll();

$brands = db()->query(
    'SELECT id, nome, ativa
     FROM marcas
     ORDER BY ordem ASC, nome ASC'
)->fetchAll();
$productGalleryImages = $editing ? fetch_product_gallery_images($productId) : [];
$existingFlavorEntries = [];

$productColumns = [
    'marca_id' => table_column_exists('produtos', 'marca_id'),
    'nome_curto' => table_column_exists('produtos', 'nome_curto'),
    'desconto_percentual' => table_column_exists('produtos', 'desconto_percentual'),
    'estoque' => table_column_exists('produtos', 'estoque'),
    'promocao' => table_column_exists('produtos', 'promocao'),
    'ordem' => table_column_exists('produtos', 'ordem'),
];

if ($editing) {
    $existingFlavorEntries = array_map(
        static fn(array $flavor): array => [
            'nome' => (string) ($flavor['nome'] ?? ''),
            'estoque' => max(0, (int) ($flavor['estoque'] ?? 0)),
        ],
        fetch_product_flavors($productId, false)
    );

    if ($existingFlavorEntries === []) {
        $existingFlavorEntries = parse_product_flavor_entries($product['sabores'] ?? null);

        if (count($existingFlavorEntries) === 1 && (int) ($product['estoque'] ?? 0) > 0) {
            $existingFlavorEntries[0]['estoque'] = (int) $product['estoque'];
        }
    }
}

$previousTotalStock = $editing && $product ? admin_product_total_stock($product, $existingFlavorEntries) : 0;

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido.');
        redirect($editing ? 'admin/produto_form.php?id=' . $productId : 'admin/produto_form.php');
    }

    $categoryId = (int) posted_value('categoria_id');
    $brandIdRaw = posted_value('marca_id');
    $brandId = $brandIdRaw !== '' ? (int) $brandIdRaw : null;
    $removeGalleryRaw = posted_value('remover_galeria', []);
    $removeGalleryIds = is_array($removeGalleryRaw)
        ? array_values(array_unique(array_filter(array_map(static fn(mixed $value): int => (int) $value, $removeGalleryRaw), static fn(int $value): bool => $value > 0)))
        : [];
    $name = trim((string) posted_value('nome'));
    $shortName = trim((string) posted_value('nome_curto'));
    $description = trim((string) posted_value('descricao'));
    $price = normalize_money_input((string) posted_value('preco'));
    $discountPercent = normalize_percentage_input(posted_value('desconto_percentual'));
    $stock = max(0, (int) posted_value('estoque', 0));
    $flavorEntries = parse_product_flavor_entries(posted_value('sabores'));
    if ($flavorEntries !== []) {
        $stock = calculate_product_flavor_stock($flavorEntries);
    }
    $active = posted_value('ativo') ? 1 : 0;
    $featured = posted_value('destaque') ? 1 : 0;
    $promotion = posted_value('promocao') ? 1 : 0;
    $removeImage = posted_value('remover_imagem') ? 1 : 0;

    // Produtos em destaque ou promocao precisam continuar visiveis na vitrine.
    if ($featured === 1 || $promotion === 1) {
        $active = 1;
    }

    if (!$categoryId || !find_category($categoryId)) {
        set_flash('error', 'Selecione uma categoria valida.');
        redirect($editing ? 'admin/produto_form.php?id=' . $productId : 'admin/produto_form.php');
    }

    if ($brandId !== null && !find_brand($brandId)) {
        set_flash('error', 'Selecione uma marca valida.');
        redirect($editing ? 'admin/produto_form.php?id=' . $productId : 'admin/produto_form.php');
    }

    if ($name === '') {
        set_flash('error', 'Informe o nome do produto.');
        redirect($editing ? 'admin/produto_form.php?id=' . $productId : 'admin/produto_form.php');
    }

    $shortNameLength = function_exists('mb_strlen')
        ? mb_strlen($shortName, 'UTF-8')
        : strlen($shortName);

    if ($shortName !== '' && $shortNameLength > 80) {
        set_flash('error', 'O nome curto deve ter no maximo 80 caracteres.');
        redirect($editing ? 'admin/produto_form.php?id=' . $productId : 'admin/produto_form.php');
    }

    if ($price <= 0) {
        set_flash('error', 'Informe um preco maior que zero.');
        redirect($editing ? 'admin/produto_form.php?id=' . $productId : 'admin/produto_form.php');
    }

    if ($discountPercent < 0 || $discountPercent >= 100) {
        set_flash('error', 'Informe um desconto entre 0% e 99,99%.');
        redirect($editing ? 'admin/produto_form.php?id=' . $productId : 'admin/produto_form.php');
    }

    $slug = generate_unique_slug('produtos', $name, $editing ? $productId : null);

    $imagePath = $product['imagem'] ?? null;

    if ($removeImage && $editing) {
        delete_uploaded_file($imagePath);
        $imagePath = null;
    }

    try {
        $imagePath = handle_image_upload('imagem', 'products', $imagePath);
    } catch (RuntimeException $exception) {
        set_flash('error', $exception->getMessage());
        redirect($editing ? 'admin/produto_form.php?id=' . $productId : 'admin/produto_form.php');
    }

    if ($editing) {
        try {
            db()->beginTransaction();

            $updateData = [
                'categoria_id' => $categoryId,
                'nome' => $name,
                'slug' => $slug,
                'descricao' => $description,
                'preco' => $price,
                'imagem' => $imagePath,
                'ativo' => $active,
                'destaque' => $featured,
            ];

            if ($productColumns['marca_id']) {
                $updateData['marca_id'] = $brandId;
            }

            if ($productColumns['nome_curto']) {
                $updateData['nome_curto'] = $shortName !== '' ? $shortName : null;
            }

            if ($productColumns['desconto_percentual']) {
                $updateData['desconto_percentual'] = $discountPercent;
            }

            if ($productColumns['estoque']) {
                $updateData['estoque'] = $stock;
            }

            if ($productColumns['promocao']) {
                $updateData['promocao'] = $promotion;
            }

            $setClause = implode(
                ",\n                     ",
                array_map(
                    static fn(string $column): string => $column . ' = :' . $column,
                    array_keys($updateData)
                )
            );

            $statement = db()->prepare(
                "UPDATE produtos
                 SET {$setClause}
                 WHERE id = :id"
            );

            $statement->execute($updateData + [
                'id' => $productId,
            ]);

            sync_product_flavor_entries($productId, $flavorEntries);
            remove_product_gallery_images($productId, $removeGalleryIds);
            save_product_gallery_uploads($productId, 'galeria');

            db()->commit();
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log('[produto_form] Falha ao salvar produto #' . $productId . ': ' . $exception->getMessage());
            set_flash('error', 'Nao foi possivel salvar o produto. Tente novamente.');
            redirect('admin/produto_form.php?id=' . $productId);
        }

        $restockNotifications = customer_send_back_in_stock_alerts(
            (int) $productId,
            $name,
            $slug,
            $previousTotalStock,
            $stock
        );

        $successMessage = 'Produto atualizado com sucesso.';

        if ($restockNotifications > 0) {
            $successMessage .= ' Alertas de estoque enviados: ' . $restockNotifications . '.';
        }

        set_flash('success', $successMessage);
        redirect('admin/produtos.php');
    }

    $order = $productColumns['ordem']
        ? (int) db()->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM produtos')->fetchColumn()
        : 0;

    try {
        db()->beginTransaction();

        $insertData = [
            'categoria_id' => $categoryId,
            'nome' => $name,
            'slug' => $slug,
            'descricao' => $description,
            'preco' => $price,
            'imagem' => $imagePath,
            'ativo' => $active,
            'destaque' => $featured,
        ];

        if ($productColumns['marca_id']) {
            $insertData['marca_id'] = $brandId;
        }

        if ($productColumns['nome_curto']) {
            $insertData['nome_curto'] = $shortName !== '' ? $shortName : null;
        }

        if ($productColumns['desconto_percentual']) {
            $insertData['desconto_percentual'] = $discountPercent;
        }

        if ($productColumns['estoque']) {
            $insertData['estoque'] = $stock;
        }

        if ($productColumns['promocao']) {
            $insertData['promocao'] = $promotion;
        }

        if ($productColumns['ordem']) {
            $insertData['ordem'] = $order;
        }

        $insertColumns = implode(', ', array_keys($insertData));
        $insertValues = implode(', ', array_map(
            static fn(string $column): string => ':' . $column,
            array_keys($insertData)
        ));

        $statement = db()->prepare(
            "INSERT INTO produtos ({$insertColumns})
             VALUES ({$insertValues})"
        );

        $statement->execute($insertData);

        $newProductId = (int) db()->lastInsertId();
        sync_product_flavor_entries($newProductId, $flavorEntries);
        save_product_gallery_uploads($newProductId, 'galeria');

        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        error_log('[produto_form] Falha ao criar produto: ' . $exception->getMessage());
        set_flash('error', 'Nao foi possivel criar o produto. Tente novamente.');
        redirect('admin/produto_form.php');
    }

    set_flash('success', 'Produto criado com sucesso.');
    redirect('admin/produtos.php');
}

$currentAdminPage = 'produtos';
$pageTitle = $editing ? 'Editar Produto' : 'Novo Produto';

$formData = [
    'categoria_id' => (string) posted_value('categoria_id', $product['categoria_id'] ?? ''),
    'marca_id' => (string) posted_value('marca_id', $product['marca_id'] ?? ''),
    'nome' => (string) posted_value('nome', $product['nome'] ?? ''),
    'nome_curto' => (string) posted_value('nome_curto', $product['nome_curto'] ?? ''),
    'descricao' => (string) posted_value('descricao', $product['descricao'] ?? ''),
    'preco' => (string) posted_value('preco', isset($product['preco']) ? number_format((float) $product['preco'], 2, ',', '.') : ''),
    'desconto_percentual' => (string) posted_value('desconto_percentual', isset($product['desconto_percentual']) ? number_format((float) $product['desconto_percentual'], 2, ',', '.') : '0,00'),
    'estoque' => (string) posted_value('estoque', $product['estoque'] ?? '0'),
    'sabores' => (string) posted_value('sabores', serialize_product_flavor_entries($existingFlavorEntries)),
    'ativo' => (string) posted_value('ativo', (string) ($product['ativo'] ?? '1')),
    'destaque' => (string) posted_value('destaque', (string) ($product['destaque'] ?? '0')),
    'promocao' => (string) posted_value('promocao', (string) ($product['promocao'] ?? '0')),
];

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--form">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">cadastro</p>
            <h2><?= e($pageTitle); ?></h2>
        </div>
        <a class="button button--ghost" href="<?= e(app_url('admin/produtos.php')); ?>">Voltar</a>
    </div>

    <form method="post" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

        <div class="form-grid">
            <div class="form-row">
                <label for="categoria_id">Categoria</label>
                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']); ?>" <?= selected($formData['categoria_id'], $category['id']); ?>>
                            <?= e($category['nome']); ?><?= (int) $category['ativa'] === 0 ? ' (inativa)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="marca_id">Marca</label>
                <select id="marca_id" name="marca_id">
                    <option value="">Sem marca</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= e((string) $brand['id']); ?>" <?= selected($formData['marca_id'], $brand['id']); ?>>
                            <?= e($brand['nome']); ?><?= (int) $brand['ativa'] === 0 ? ' (inativa)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" type="text" required value="<?= e($formData['nome']); ?>">
            </div>

            <div class="form-row">
                <label for="nome_curto">Nome curto para card</label>
                <input id="nome_curto" name="nome_curto" type="text" maxlength="80" value="<?= e($formData['nome_curto']); ?>" placeholder="Opcional, usado so nos cards">
                <small class="form-hint">Se preencher, esse nome aparece nos cards da vitrine. A pagina do produto continua com o nome completo.</small>
            </div>

            <div class="form-row">
                <label for="preco">Preco</label>
                <input id="preco" name="preco" type="text" required value="<?= e($formData['preco']); ?>" placeholder="39,90">
            </div>

            <div class="form-row">
                <label for="desconto_percentual">Desconto do produto (%)</label>
                <input id="desconto_percentual" name="desconto_percentual" type="text" inputmode="decimal" value="<?= e($formData['desconto_percentual']); ?>" placeholder="Ex.: 10,00">
                <small class="form-hint">Use 0 para nao aplicar desconto. A vitrine mostra o preco original riscado e o valor final calculado.</small>
            </div>

            <div class="form-row">
                <label for="estoque">Quantidade no estoque</label>
                <input id="estoque" name="estoque" type="number" min="0" value="<?= e($formData['estoque']); ?>" placeholder="0">
                <small class="form-hint">Se voce cadastrar tamanhos com quantidade abaixo, esse total sera calculado automaticamente.</small>
            </div>

            <div class="form-row form-row--wide">
                <label for="descricao">Descricao</label>
                <textarea id="descricao" name="descricao" rows="5"><?= e($formData['descricao']); ?></textarea>
            </div>

            <div class="form-row form-row--wide">
                <label for="flavor-input">Tamanhos</label>
                <div class="product-flavor-builder" data-flavor-builder>
                    <div class="product-flavor-builder__input-row product-flavor-builder__input-row--sizes">
                        <div class="product-flavor-builder__size-grid" aria-label="Escolha os tamanhos">
                            <?php for ($size = 32; $size <= 46; $size++): ?>
                                <label class="product-flavor-builder__size-option">
                                    <input type="checkbox" value="<?= e((string) $size); ?>" data-flavor-input>
                                    <span><?= e((string) $size); ?></span>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <button class="button button--ghost button--small" type="button" data-flavor-add>Adicionar tamanhos selecionados</button>
                    </div>
                    <div class="product-flavor-builder__list" data-flavor-list></div>
                    <textarea id="sabores" name="sabores" hidden data-flavor-storage><?= e($formData['sabores']); ?></textarea>
                </div>
                <small class="form-hint">Marque varios tamanhos de uma vez, confirme e depois ajuste a quantidade de cada um na lista abaixo.</small>
            </div>

            <div class="form-row product-form__upload-row product-form__upload-row--primary">
                <label for="imagem">Imagem principal</label>
                <div class="admin-file-field">
                    <label class="admin-file-field__button" for="imagem">Selecionar imagem</label>
                    <span class="admin-file-field__name" data-file-name>
                        <?= !empty($product['imagem']) ? 'Imagem atual mantida ate voce trocar.' : 'Nenhuma imagem selecionada'; ?>
                    </span>
                    <input
                        id="imagem"
                        name="imagem"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp"
                        data-image-input="#produto-preview"
                        data-file-name-target="[data-file-name]"
                    >
                </div>
                <small class="form-hint">Formatos aceitos: JPG, PNG e WEBP.</small>
            </div>

            <div class="form-row product-form__upload-row product-form__upload-row--gallery">
                <label for="galeria">Mais imagens do produto</label>
                <div class="admin-file-field">
                    <label class="admin-file-field__button" for="galeria">Adicionar imagens</label>
                    <span class="admin-file-field__name" data-gallery-file-name>
                        Selecione uma ou varias imagens extras.
                    </span>
                    <input
                        id="galeria"
                        name="galeria[]"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp"
                        multiple
                        data-image-input="#produto-galeria-preview"
                        data-file-name-target="[data-gallery-file-name]"
                    >
                </div>
                <small class="form-hint">Essas imagens aparecem na pagina completa do produto.</small>
            </div>

            <div class="form-row form-row--preview product-form__upload-preview product-form__upload-preview--primary">
                <label>Imagem principal</label>
                <div class="image-preview" id="produto-preview">
                    <?php if (!empty($product['imagem'])): ?>
                        <img src="<?= e(app_url($product['imagem'])); ?>" alt="<?= e($product['nome'] ?? 'Preview'); ?>">
                    <?php else: ?>
                        <span>Nenhuma imagem selecionada.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row form-row--preview product-form__upload-preview product-form__upload-preview--gallery">
                <label>Previa da galeria</label>
                <div class="image-preview image-preview--gallery" id="produto-galeria-preview">
                    <span>Nenhuma imagem extra selecionada.</span>
                </div>
            </div>

            <?php if ($editing): ?>
                <div class="form-row form-row--wide">
                    <label>Galeria atual</label>
                    <?php if ($productGalleryImages): ?>
                        <div class="gallery-grid-admin">
                            <?php foreach ($productGalleryImages as $galleryImage): ?>
                                <label class="gallery-card-admin">
                                    <img src="<?= e(app_url($galleryImage['imagem'])); ?>" alt="Imagem adicional do produto">
                                    <span class="gallery-card-admin__remove">
                                        <input type="checkbox" name="remover_galeria[]" value="<?= e((string) $galleryImage['id']); ?>">
                                        <span>Remover</span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="inline-empty-state">
                            <span>Esse produto ainda nao tem imagens extras.</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="form-row form-row--toggle form-row--wide product-form__toggle-row">
                <label class="checkbox-row checkbox-row--tile">
                    <input name="destaque" type="checkbox" value="1" <?= checked($formData['destaque'], 1); ?> data-product-feature-toggle>
                    <span class="checkbox-row__surface">Marcar como destaque</span>
                </label>
            </div>

            <div class="form-row form-row--toggle form-row--wide product-form__toggle-row">
                <label class="checkbox-row checkbox-row--tile">
                    <input name="ativo" type="checkbox" value="1" <?= checked($formData['ativo'], 1); ?> data-product-active-toggle>
                    <span class="checkbox-row__surface">Produto ativo</span>
                </label>
            </div>

            <div class="form-row form-row--toggle form-row--wide product-form__toggle-row">
                <label class="checkbox-row checkbox-row--tile">
                    <input name="promocao" type="checkbox" value="1" <?= checked($formData['promocao'], 1); ?> data-product-promo-toggle>
                    <span class="checkbox-row__surface">Adicionar a promo</span>
                </label>
            </div>

            <div class="form-row form-row--wide">
                <small class="form-hint">Produtos em destaque ou promocao ficam ativos automaticamente para aparecerem na vitrine.</small>
            </div>

            <?php if ($editing && !empty($product['imagem'])): ?>
                <div class="form-row form-row--toggle">
                    <label class="checkbox-row">
                        <input name="remover_imagem" type="checkbox" value="1">
                        <span>Remover imagem atual</span>
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button class="button button--primary" type="submit"><?= $editing ? 'Salvar alteracoes' : 'Criar produto'; ?></button>
        </div>
    </form>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
