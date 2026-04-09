<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido para atualizar o cupom.');
        redirect('admin/cupons.php');
    }

    $couponId = (int) posted_value('id');
    $action = (string) posted_value('action');
    $coupon = find_coupon($couponId);

    if (!$coupon) {
        set_flash('error', 'Cupom nao encontrado.');
        redirect('admin/cupons.php');
    }

    if ($action === 'delete') {
        $statement = db()->prepare('DELETE FROM cupons WHERE id = :id');
        $statement->execute(['id' => $couponId]);
        set_flash('success', 'Cupom removido com sucesso.');
        redirect('admin/cupons.php');
    }

    if ($action === 'toggle_active') {
        $nextActive = (int) ($coupon['ativo'] ?? 0) === 1 ? 0 : 1;
        $statement = db()->prepare(
            'UPDATE cupons
             SET ativo = :ativo
             WHERE id = :id'
        );
        $statement->execute([
            'ativo' => $nextActive,
            'id' => $couponId,
        ]);

        $updatedCoupon = find_coupon($couponId);

        if ($updatedCoupon && $nextActive === 1) {
            coupon_dispatch_launch_notifications($updatedCoupon);
        }

        set_flash('success', $nextActive === 1 ? 'Cupom ativado.' : 'Cupom desativado.');
        redirect('admin/cupons.php');
    }

    if ($action === 'renotify_unredeemed') {
        $coupon = coupon_enrich_redemption_stats($coupon);

        if (!coupon_is_active($coupon)) {
            set_flash('error', 'Ative o cupom antes de renotificar os clientes.');
            redirect('admin/cupons.php');
        }

        if (!empty($coupon['redemption_limit_reached'])) {
            set_flash('error', 'Esse cupom atingiu o limite de resgates e nao pode ser renotificado.');
            redirect('admin/cupons.php');
        }

        $notifiedCount = coupon_dispatch_reminder_notifications($coupon);

        if ($notifiedCount > 0) {
            set_flash('success', 'Lembrete enviado para ' . $notifiedCount . ' cliente(s) que ainda nao resgataram o cupom.');
        } else {
            set_flash('success', 'Nenhum cliente pendente para renotificar neste cupom.');
        }

        redirect('admin/cupons.php');
    }
}

$currentAdminPage = 'cupons';
$pageTitle = 'Cupons';
$coupons = fetch_all_coupons();

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">promocoes</p>
            <h2>Lista de cupons</h2>
        </div>
        <a class="button button--primary" href="<?= e(app_url('admin/cupom_form.php')); ?>">Novo cupom</a>
    </div>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Escopo</th>
                    <th>Valor</th>
                    <th>Resgates</th>
                    <th>Status</th>
                    <th>Notificacao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$coupons): ?>
                    <tr>
                        <td colspan="9" class="table-empty">Nenhum cupom cadastrado.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($coupons as $coupon): ?>
                    <?php
                    $scope = coupon_normalize_scope((string) ($coupon['escopo'] ?? 'order'));
                    $couponProducts = $scope === 'products'
                        ? fetch_coupon_products((int) ($coupon['id'] ?? 0))
                        : [];
                    $couponBrands = $scope === 'brands'
                        ? fetch_coupon_brands((int) ($coupon['id'] ?? 0))
                        : [];
                    $isActive = coupon_is_active($coupon);
                    ?>
                    <tr>
                        <td data-label="Codigo"><strong><?= e((string) ($coupon['codigo'] ?? '')); ?></strong></td>
                        <td data-label="Nome">
                            <strong><?= e((string) ($coupon['nome'] ?? '')); ?></strong>
                            <?php if (!empty($coupon['descricao'])): ?>
                                <small class="table-subtitle"><?= e((string) $coupon['descricao']); ?></small>
                            <?php elseif ($couponProducts !== []): ?>
                                <small class="table-subtitle"><?= e(implode(', ', array_map(static fn(array $product): string => (string) ($product['nome'] ?? ''), $couponProducts))); ?></small>
                            <?php elseif ($couponBrands !== []): ?>
                                <small class="table-subtitle"><?= e(implode(', ', array_map(static fn(array $brand): string => (string) ($brand['nome'] ?? ''), $couponBrands))); ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Tipo"><?= e(coupon_type_label((string) ($coupon['tipo'] ?? 'percent'))); ?></td>
                        <td data-label="Escopo"><?= e(coupon_scope_label((string) ($coupon['escopo'] ?? 'order'))); ?></td>
                        <td data-label="Valor"><?= e(coupon_display_value($coupon)); ?></td>
                        <td data-label="Resgates">
                            <?php if (($coupon['redemption_limit'] ?? null) !== null): ?>
                                <strong><?= e((string) ($coupon['redemption_count'] ?? 0)); ?> / <?= e((string) ($coupon['redemption_limit'] ?? 0)); ?></strong>
                                <small class="table-subtitle">
                                    <?= !empty($coupon['redemption_limit_reached']) ? 'Limite atingido' : ('Restam ' . e((string) ($coupon['redemptions_remaining'] ?? 0))); ?>
                                </small>
                            <?php else: ?>
                                <strong><?= e((string) ($coupon['redemption_count'] ?? 0)); ?></strong>
                                <small class="table-subtitle">Sem limite</small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <span class="status-pill <?= $isActive ? 'status-pill--success' : 'status-pill--neutral'; ?>">
                                <?= $isActive ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td data-label="Notificacao">
                            <span class="status-pill <?= !empty($coupon['notificado_em']) ? 'status-pill--accent' : 'status-pill--warning'; ?>">
                                <?= !empty($coupon['notificado_em']) ? 'Enviada' : 'Pendente'; ?>
                            </span>
                        </td>
                        <td data-label="Acoes">
                            <div class="table-actions">
                                <a class="button button--ghost button--small" href="<?= e(app_url('admin/cupom_form.php?id=' . $coupon['id'])); ?>">Editar</a>
                                <?php if ($isActive): ?>
                                    <form method="post" onsubmit="return confirm('Renotificar os clientes que ainda nao resgataram este cupom?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?= e((string) $coupon['id']); ?>">
                                        <input type="hidden" name="action" value="renotify_unredeemed">
                                        <button class="button button--primary button--small" type="submit">Renotificar</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $coupon['id']); ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <button class="button button--ghost button--small" type="submit">
                                        <?= (int) ($coupon['ativo'] ?? 0) === 1 ? 'Desativar' : 'Ativar'; ?>
                                    </button>
                                </form>
                                <form method="post" onsubmit="return confirm('Excluir este cupom?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $coupon['id']); ?>">
                                    <input type="hidden" name="action" value="delete">
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
