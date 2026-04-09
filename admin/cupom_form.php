<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

$couponId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $couponId !== null && $couponId > 0;
$coupon = $editing ? find_coupon($couponId) : null;

if ($editing && !$coupon) {
    set_flash('error', 'Cupom nao encontrado.');
    redirect('admin/cupons.php');
}

$products = db()->query(
    'SELECT p.id, p.nome, COALESCE(m.nome, \'Sem marca\') AS marca_nome
     FROM produtos p
     LEFT JOIN marcas m ON m.id = p.marca_id
     ORDER BY p.nome ASC'
)->fetchAll();
$brands = db()->query(
    'SELECT id, nome
     FROM marcas
     ORDER BY nome ASC'
)->fetchAll();
$typeOptions = coupon_type_options();
$scopeOptions = coupon_scope_options();

$selectedProductIds = $editing ? fetch_coupon_product_ids($couponId) : [];
$selectedBrandIds = $editing ? fetch_coupon_brand_ids($couponId) : [];
$couponRedemptionCount = $editing ? coupon_count_redemptions($couponId) : 0;

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    $code = coupon_normalize_code((string) posted_value('codigo'));
    $description = trim((string) posted_value('descricao'));
    $typeRaw = trim((string) posted_value('tipo'));
    $scopeRaw = trim((string) posted_value('escopo'));
    $type = coupon_normalize_type($typeRaw);
    $scope = coupon_normalize_scope($scopeRaw);
    $discountValue = normalize_money_input((string) posted_value('valor_desconto'));
    $minimumSubtotal = normalize_money_input((string) posted_value('subtotal_minimo'));
    $startsAtDate = trim((string) posted_value('starts_at_date'));
    $startsAtTime = trim((string) posted_value('starts_at_time'));
    $endsAtDate = trim((string) posted_value('ends_at_date'));
    $endsAtTime = trim((string) posted_value('ends_at_time'));
    $startsAt = coupon_compose_datetime_input($startsAtDate, $startsAtTime, false);
    $endsAt = coupon_compose_datetime_input($endsAtDate, $endsAtTime, true);
    $redemptionLimitRaw = trim((string) posted_value('limite_resgates'));
    $redemptionLimit = $redemptionLimitRaw !== ''
        ? max(0, (int) $redemptionLimitRaw)
        : null;
    $active = posted_value('ativo') ? 1 : 0;
    $selectedProductIds = array_values(array_unique(array_filter(
        array_map(static fn(mixed $value): int => (int) $value, (array) posted_value('produtos', [])),
        static fn(int $value): bool => $value > 0
    )));
    $selectedBrandIds = array_values(array_unique(array_filter(
        array_map(static fn(mixed $value): int => (int) $value, (array) posted_value('marcas', [])),
        static fn(int $value): bool => $value > 0
    )));

    if ($code === '') {
        set_flash('error', 'Informe o codigo do cupom.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($typeRaw === '' || !array_key_exists($typeRaw, $typeOptions)) {
        set_flash('error', 'Selecione o tipo do cupom.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($scopeRaw === '' || !array_key_exists($scopeRaw, $scopeOptions)) {
        set_flash('error', 'Selecione o escopo do cupom.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    $name = 'Cupom ' . $code;

    if ($type !== 'free_shipping' && $discountValue <= 0) {
        set_flash('error', 'Informe um valor de desconto maior que zero.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($type === 'percent' && $discountValue > 100) {
        set_flash('error', 'O desconto percentual nao pode ultrapassar 100%.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($type === 'above_value' && $minimumSubtotal <= 0) {
        set_flash('error', 'Informe o subtotal minimo para o cupom "Acima de x valor".');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($scope !== 'products') {
        $selectedProductIds = [];
    }

    if ($scope !== 'brands') {
        $selectedBrandIds = [];
    }

    if ($scope === 'products' && $selectedProductIds === []) {
        set_flash('error', 'Selecione pelo menos um produto para este cupom.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($scope === 'brands' && $selectedBrandIds === []) {
        set_flash('error', 'Selecione pelo menos uma marca para este cupom.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($startsAtDate === '' && $startsAtTime !== '') {
        set_flash('error', 'Informe a data inicial para usar uma hora de inicio.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($endsAtDate === '' && $endsAtTime !== '') {
        set_flash('error', 'Informe a data final para usar uma hora de encerramento.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) > strtotime($endsAt)) {
        set_flash('error', 'A data final precisa ser posterior a data inicial.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($redemptionLimit !== null && $redemptionLimit > 0 && $couponRedemptionCount > $redemptionLimit) {
        set_flash('error', 'O limite de resgates nao pode ser menor que a quantidade ja resgatada.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    $existingCoupon = find_coupon_by_code($code);

    if ($existingCoupon && (!$editing || (int) ($existingCoupon['id'] ?? 0) !== $couponId)) {
        set_flash('error', 'Ja existe um cupom com esse codigo.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($type === 'free_shipping') {
        $discountValue = 0.0;
    }

    db()->beginTransaction();

    try {
        if ($editing) {
            $statement = db()->prepare(
                'UPDATE cupons
                 SET codigo = :codigo,
                     nome = :nome,
                     descricao = :descricao,
                     tipo = :tipo,
                     escopo = :escopo,
                     valor_desconto = :valor_desconto,
                     subtotal_minimo = :subtotal_minimo,
                     limite_resgates = :limite_resgates,
                     starts_at = :starts_at,
                     ends_at = :ends_at,
                     ativo = :ativo
                 WHERE id = :id'
            );

            $statement->execute([
                'codigo' => $code,
                'nome' => $name,
                'descricao' => $description !== '' ? $description : null,
                'tipo' => $type,
                'escopo' => $scope,
                'valor_desconto' => $discountValue,
                'subtotal_minimo' => $minimumSubtotal,
                'limite_resgates' => $redemptionLimit,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'ativo' => $active,
                'id' => $couponId,
            ]);

            coupon_sync_products($couponId, $selectedProductIds);
            coupon_sync_brands($couponId, $selectedBrandIds);
            db()->commit();
            $savedCoupon = find_coupon($couponId);
        } else {
            $statement = db()->prepare(
                'INSERT INTO cupons (
                    codigo, nome, descricao, tipo, escopo, valor_desconto, subtotal_minimo, limite_resgates,
                    starts_at, ends_at, ativo
                 ) VALUES (
                    :codigo, :nome, :descricao, :tipo, :escopo, :valor_desconto, :subtotal_minimo, :limite_resgates,
                    :starts_at, :ends_at, :ativo
                 )'
            );

            $statement->execute([
                'codigo' => $code,
                'nome' => $name,
                'descricao' => $description !== '' ? $description : null,
                'tipo' => $type,
                'escopo' => $scope,
                'valor_desconto' => $discountValue,
                'subtotal_minimo' => $minimumSubtotal,
                'limite_resgates' => $redemptionLimit,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'ativo' => $active,
            ]);

            $newCouponId = (int) db()->lastInsertId();
            coupon_sync_products($newCouponId, $selectedProductIds);
            coupon_sync_brands($newCouponId, $selectedBrandIds);
            db()->commit();
            $savedCoupon = find_coupon($newCouponId);
        }
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        set_flash('error', 'Nao foi possivel salvar o cupom.');
        redirect($editing ? 'admin/cupom_form.php?id=' . $couponId : 'admin/cupom_form.php');
    }

    if ($savedCoupon) {
        coupon_dispatch_launch_notifications($savedCoupon);
    }

    set_flash('success', $editing ? 'Cupom atualizado com sucesso.' : 'Cupom criado com sucesso.');
    redirect('admin/cupons.php');
}

$currentAdminPage = 'cupons';
$pageTitle = $editing ? 'Editar Cupom' : 'Novo Cupom';
$formData = [
    'codigo' => (string) posted_value('codigo', $coupon['codigo'] ?? ''),
    'descricao' => (string) posted_value('descricao', $coupon['descricao'] ?? ''),
    'tipo' => (string) posted_value('tipo', $coupon['tipo'] ?? ''),
    'escopo' => (string) posted_value('escopo', $coupon['escopo'] ?? ''),
    'valor_desconto' => (string) posted_value('valor_desconto', isset($coupon['valor_desconto']) ? number_format((float) $coupon['valor_desconto'], 2, ',', '.') : ''),
    'subtotal_minimo' => (string) posted_value('subtotal_minimo', isset($coupon['subtotal_minimo']) ? number_format((float) $coupon['subtotal_minimo'], 2, ',', '.') : '0,00'),
    'limite_resgates' => (string) posted_value('limite_resgates', isset($coupon['limite_resgates']) && (int) $coupon['limite_resgates'] > 0 ? (string) ((int) $coupon['limite_resgates']) : ''),
    'starts_at_date' => (string) posted_value('starts_at_date', !empty($coupon['starts_at']) ? date('Y-m-d', strtotime((string) $coupon['starts_at'])) : ''),
    'starts_at_time' => (string) posted_value('starts_at_time', !empty($coupon['starts_at']) ? date('H:i', strtotime((string) $coupon['starts_at'])) : ''),
    'ends_at_date' => (string) posted_value('ends_at_date', !empty($coupon['ends_at']) ? date('Y-m-d', strtotime((string) $coupon['ends_at'])) : ''),
    'ends_at_time' => (string) posted_value('ends_at_time', !empty($coupon['ends_at']) ? date('H:i', strtotime((string) $coupon['ends_at'])) : ''),
    'ativo' => (string) posted_value('ativo', (string) ($coupon['ativo'] ?? '1')),
];
$descriptionToggleLabel = trim($formData['descricao']) !== ''
    ? 'Editar descricao'
    : 'Adicionar descricao';
$hasSelectedType = array_key_exists($formData['tipo'], $typeOptions);
$hasSelectedScope = array_key_exists($formData['escopo'], $scopeOptions);
$showDiscountRow = $hasSelectedType && $formData['tipo'] !== 'free_shipping';
$showSubtotalRow = $hasSelectedType;
$showProductRow = $hasSelectedScope && $formData['escopo'] === 'products';
$showBrandRow = $hasSelectedScope && $formData['escopo'] === 'brands';

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--form">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">promocao</p>
            <h2><?= e($pageTitle); ?></h2>
        </div>
        <a class="button button--ghost" href="<?= e(app_url('admin/cupons.php')); ?>">Voltar</a>
    </div>

    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

        <div class="form-grid">
            <div class="form-row">
                <label for="codigo">Codigo</label>
                <input id="codigo" name="codigo" type="text" required value="<?= e($formData['codigo']); ?>" placeholder="Ex.: PROMO10">
            </div>

            <div class="form-row form-row--button-align">
                <label class="form-row__ghost-label" aria-hidden="true">Acao</label>
                <button
                    class="button button--ghost button--small button--fit"
                    type="button"
                    data-optional-field-toggle="#coupon-description-row"
                    data-optional-field-input="#descricao"
                    data-label-open="<?= e($descriptionToggleLabel); ?>"
                    data-label-close="Ocultar descricao"
                    data-clear-on-hide="true"
                >
                    <?= e($descriptionToggleLabel); ?>
                </button>
            </div>

            <div class="form-row form-row--wide" id="coupon-description-row" hidden>
                <label for="descricao">Descricao opcional</label>
                <textarea id="descricao" name="descricao" rows="3" placeholder="Texto que o cliente vai ver na notificacao ou no checkout."><?= e($formData['descricao']); ?></textarea>
                <small class="form-hint">Use apenas se quiser mostrar uma mensagem personalizada no cupom.</small>
            </div>

            <div class="form-row">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <option value="">Selecione o tipo</option>
                    <?php foreach ($typeOptions as $typeValue => $typeLabel): ?>
                        <option value="<?= e($typeValue); ?>" <?= selected($formData['tipo'], $typeValue); ?>><?= e($typeLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="escopo">Escopo</label>
                <select id="escopo" name="escopo" required>
                    <option value="">Selecione o escopo</option>
                    <?php foreach ($scopeOptions as $scopeValue => $scopeLabel): ?>
                        <option value="<?= e($scopeValue); ?>" <?= selected($formData['escopo'], $scopeValue); ?>><?= e($scopeLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row" id="coupon-discount-row" <?= $showDiscountRow ? '' : 'hidden'; ?>>
                <label for="valor_desconto">Valor do desconto</label>
                <input id="valor_desconto" name="valor_desconto" type="text" inputmode="decimal" value="<?= e($formData['valor_desconto']); ?>" placeholder="Ex.: 10,00">
                <small class="form-hint" id="coupon-discount-hint">Para percentual, use 10 para 10%.</small>
            </div>

            <div class="form-row" id="coupon-subtotal-row" <?= $showSubtotalRow ? '' : 'hidden'; ?>>
                <label for="subtotal_minimo">Subtotal minimo</label>
                <input id="subtotal_minimo" name="subtotal_minimo" type="text" inputmode="decimal" value="<?= e($formData['subtotal_minimo']); ?>" placeholder="0,00">
                <small class="form-hint" id="coupon-subtotal-hint">Use se quiser exigir um valor minimo para liberar o cupom.</small>
            </div>

            <div class="form-row">
                <label for="limite_resgates">Limite de resgates</label>
                <input id="limite_resgates" name="limite_resgates" type="number" min="1" step="1" value="<?= e($formData['limite_resgates']); ?>" placeholder="Ex.: 100">
                <small class="form-hint">Deixe em branco para cupom sem limite. Use 100 para liberar apenas aos 100 primeiros resgates.</small>
            </div>

            <div class="form-row form-row--wide">
                <label for="starts_at_date">Inicia em</label>
                <div class="coupon-datetime-grid">
                    <input id="starts_at_date" name="starts_at_date" type="date" value="<?= e($formData['starts_at_date']); ?>">
                    <input id="starts_at_time" name="starts_at_time" type="time" value="<?= e($formData['starts_at_time']); ?>">
                </div>
                <small class="form-hint">A hora e opcional. Sem hora, o cupom comeca a valer a partir de 00:00.</small>
            </div>

            <div class="form-row form-row--wide">
                <label for="ends_at_date">Encerra em</label>
                <div class="coupon-datetime-grid">
                    <input id="ends_at_date" name="ends_at_date" type="date" value="<?= e($formData['ends_at_date']); ?>">
                    <input id="ends_at_time" name="ends_at_time" type="time" value="<?= e($formData['ends_at_time']); ?>">
                </div>
                <small class="form-hint">A hora e opcional. Sem hora, o cupom encerra as 23:59 da data escolhida.</small>
            </div>

            <?php if ($editing): ?>
                <div class="form-row form-row--wide">
                    <div class="coupon-stats">
                        <span class="status-pill status-pill--accent">Resgatados: <?= e((string) $couponRedemptionCount); ?></span>
                        <?php if (coupon_redemption_limit($coupon ?? []) !== null): ?>
                            <span class="status-pill <?= !empty(coupon_enrich_redemption_stats($coupon)['redemption_limit_reached']) ? 'status-pill--warning' : 'status-pill--success'; ?>">
                                Limite: <?= e((string) ((int) ($coupon['limite_resgates'] ?? 0))); ?>
                            </span>
                            <span class="status-pill status-pill--neutral">
                                Restam: <?= e((string) max(0, ((int) ($coupon['limite_resgates'] ?? 0)) - $couponRedemptionCount)); ?>
                            </span>
                        <?php else: ?>
                            <span class="status-pill status-pill--neutral">Sem limite de resgates</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-row form-row--toggle form-row--wide">
                <label class="checkbox-row">
                    <input name="ativo" type="checkbox" value="1" <?= checked($formData['ativo'], 1); ?>>
                    <span>Cupom ativo</span>
                </label>
            </div>

            <div class="form-row form-row--wide" id="coupon-products-row" <?= $showProductRow ? '' : 'hidden'; ?>>
                <label>Produtos selecionados</label>
                <div class="coupon-product-picker">
                    <?php foreach ($products as $product): ?>
                        <label class="checkbox-row coupon-product-picker__item">
                            <input
                                type="checkbox"
                                name="produtos[]"
                                value="<?= e((string) $product['id']); ?>"
                                <?= in_array((int) $product['id'], $selectedProductIds, true) ? 'checked' : ''; ?>
                            >
                            <span><?= e((string) $product['nome']); ?> <small><?= e((string) $product['marca_nome']); ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small class="form-hint">Use esta lista quando o escopo for "Produtos selecionados".</small>
            </div>

            <div class="form-row form-row--wide" id="coupon-brands-row" <?= $showBrandRow ? '' : 'hidden'; ?>>
                <label>Marcas selecionadas</label>
                <div class="coupon-product-picker">
                    <?php foreach ($brands as $brand): ?>
                        <label class="checkbox-row coupon-product-picker__item">
                            <input
                                type="checkbox"
                                name="marcas[]"
                                value="<?= e((string) $brand['id']); ?>"
                                <?= in_array((int) $brand['id'], $selectedBrandIds, true) ? 'checked' : ''; ?>
                            >
                            <span><?= e((string) $brand['nome']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small class="form-hint">Use esta lista quando o escopo for "Marcas selecionadas".</small>
            </div>
        </div>

        <div class="form-actions">
            <button class="button button--primary" type="submit"><?= $editing ? 'Salvar alteracoes' : 'Criar cupom'; ?></button>
        </div>
    </form>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
