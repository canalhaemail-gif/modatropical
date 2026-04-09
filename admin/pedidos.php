<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function render_order_cash_change_section(array $order): void
{
    if (!order_has_cash_change_details($order)) {
        return;
    }

    $cashChangeFor = order_cash_change_for_amount($order);
    $cashChangeDue = order_cash_change_due_amount($order);
    $pickup = storefront_normalize_checkout_method((string) ($order['fulfillment_method'] ?? 'delivery')) === 'pickup';
    ?>
    <div class="order-admin-card__section">
        <strong>Pagamento em dinheiro</strong>
        <?php if ($cashChangeFor !== null): ?>
            <p>Troco para <?= e(format_currency($cashChangeFor)); ?></p>
            <?php if (($cashChangeDue ?? 0) > 0): ?>
                <p>Levar de troco: <?= e(format_currency((float) $cashChangeDue)); ?></p>
            <?php else: ?>
                <p>Cliente informou pagamento em dinheiro exato.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>Cliente vai pagar em dinheiro <?= $pickup ? 'na retirada' : 'na entrega'; ?>.</p>
        <?php endif; ?>
    </div>
    <?php
}

function render_order_coupon_section(array $order): void
{
    $couponCode = trim((string) ($order['cupom_codigo'] ?? ''));
    $couponDiscount = max(0, (float) ($order['cupom_desconto'] ?? 0));
    $shippingDiscount = max(0, (float) ($order['cupom_frete_desconto'] ?? 0));

    if ($couponCode === '' && $couponDiscount <= 0 && $shippingDiscount <= 0) {
        return;
    }
    ?>
    <div class="order-admin-card__section">
        <strong>Cupom aplicado</strong>
        <?php if ($couponCode !== ''): ?>
            <p>Codigo: <?= e($couponCode); ?></p>
        <?php endif; ?>
        <?php if ($couponDiscount > 0): ?>
            <p>Desconto: <?= e(format_currency($couponDiscount)); ?></p>
        <?php endif; ?>
        <?php if ($shippingDiscount > 0): ?>
            <p>Frete promocional: <?= e(format_currency($shippingDiscount)); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

require_admin_auth();

$actionMap = [
    'approve' => 'approved',
    'reject' => 'rejected',
    'cancel' => 'cancelled',
    'ready_pickup' => 'ready_pickup',
    'out_for_delivery' => 'out_for_delivery',
    'complete' => 'completed',
];

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido para atualizar o pedido.');
        redirect('admin/pedidos.php');
    }

    $orderId = (int) posted_value('id');
    $action = (string) posted_value('action');

    try {
        if ($action === 'delete') {
            order_delete($orderId);
            set_flash('success', 'Pedido excluido com sucesso.');
            redirect('admin/pedidos.php');
        }

        $nextStatus = $actionMap[$action] ?? null;

        if (!$nextStatus) {
            set_flash('error', 'Acao invalida para o pedido.');
            redirect('admin/pedidos.php');
        }

        order_transition($orderId, $nextStatus, (int) (current_admin()['id'] ?? 0));
        set_flash('success', 'Pedido atualizado para ' . order_status_admin_label($nextStatus) . '.');
    } catch (RuntimeException $exception) {
        set_flash('error', $exception->getMessage());
    }

    redirect('admin/pedidos.php');
}

$currentAdminPage = 'pedidos';
$pageTitle = 'Pedidos';

$orders = fetch_all_orders();
$pendingOrders = array_values(array_filter($orders, static fn(array $order): bool => order_normalize_status((string) ($order['status'] ?? 'pending')) === 'pending'));
$activeOrders = array_values(array_filter($orders, static fn(array $order): bool => in_array(order_normalize_status((string) ($order['status'] ?? 'pending')), ['approved', 'ready_pickup', 'out_for_delivery'], true)));
$completedOrders = array_values(array_filter($orders, static fn(array $order): bool => order_normalize_status((string) ($order['status'] ?? 'pending')) === 'completed'));
$cancelledOrders = array_values(array_filter($orders, static fn(array $order): bool => in_array(order_normalize_status((string) ($order['status'] ?? 'pending')), ['cancelled', 'rejected'], true)));

$orderItemsByOrderId = [];

if ($orders !== []) {
    $orderIds = array_values(array_map(static fn(array $order): int => (int) $order['id'], $orders));
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $statement = db()->prepare(
        "SELECT *
         FROM pedido_itens
         WHERE pedido_id IN ({$placeholders})
         ORDER BY pedido_id DESC, id ASC"
    );
    $statement->execute($orderIds);

    foreach ($statement->fetchAll() as $item) {
        $orderItemsByOrderId[(int) $item['pedido_id']][] = $item;
    }
}

$counts = [
    'pending' => count($pendingOrders),
    'active' => count($activeOrders),
    'completed' => count($completedOrders),
    'cancelled' => count($cancelledOrders),
];
$ordersLiveSignature = hash(
    'sha256',
    json_encode(
        [
            'counts' => $counts,
            'orders' => array_map(
                static fn(array $order): array => [
                    'id' => (int) ($order['id'] ?? 0),
                    'status' => (string) ($order['status'] ?? ''),
                    'created_at' => (string) ($order['criado_em'] ?? ''),
                    'updated_at' => (string) ($order['status_atualizado_em'] ?? ''),
                    'tracking_code' => (string) ($order['codigo_rastreio'] ?? ''),
                ],
                $orders
            ),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: ''
);
$ordersRefreshUrl = app_url('admin/pedidos.php');

require BASE_PATH . '/includes/admin_header.php';
?>

<div
    class="orders-dashboard"
    data-admin-orders-live-root
    data-admin-orders-live-url="<?= e($ordersRefreshUrl); ?>"
    data-admin-orders-live-signature="<?= e($ordersLiveSignature); ?>"
    data-admin-orders-live-poll="5000"
>
<section class="admin-grid admin-grid--metrics">
    <article class="metric-card">
        <span>Novos pedidos</span>
        <strong><?= e((string) $counts['pending']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Em andamento</span>
        <strong><?= e((string) $counts['active']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Concluidos</span>
        <strong><?= e((string) $counts['completed']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Cancelados / recusados</span>
        <strong><?= e((string) $counts['cancelled']); ?></strong>
    </article>
</section>

<section class="panel-card panel-card--orders">
    <div class="panel-card__header panel-card__header--orders">
        <div>
            <p class="panel-card__eyebrow">novos pedidos</p>
            <h2>Pedidos aguardando aprovacao</h2>
        </div>
        <span class="orders-live-badge">Atualiza automaticamente</span>
    </div>

    <?php if (!$pendingOrders): ?>
        <div class="empty-state empty-state--admin">Nenhum pedido novo aguardando aprovacao.</div>
    <?php else: ?>
        <div class="orders-board">
            <?php foreach ($pendingOrders as $order): ?>
                <?php
                $items = $orderItemsByOrderId[(int) $order['id']] ?? [];
                $trackingUrl = app_url('rastreio.php?codigo=' . rawurlencode((string) $order['codigo_rastreio']));
                ?>
                <article class="order-admin-card order-admin-card--pending">
                    <div class="order-admin-card__header">
                        <div class="order-admin-card__header-copy">
                            <span class="order-admin-card__kicker"><?= e(order_fulfillment_label((string) $order['fulfillment_method'])); ?></span>
                            <h3><?= e((string) $order['nome_cliente']); ?></h3>
                            <p><?= e(format_phone((string) ($order['telefone_cliente'] ?? ''))); ?></p>
                        </div>
                        <span class="status-pill <?= e(order_status_badge_class((string) $order['status'])); ?>">
                            <?= e(order_status_admin_label((string) $order['status'])); ?>
                        </span>
                    </div>

                    <div class="order-admin-card__badges">
                        <span class="status-pill status-pill--neutral">Rastreio: <?= e((string) $order['codigo_rastreio']); ?></span>
                        <span class="status-pill status-pill--neutral"><?= e(order_payment_label((string) ($order['payment_method'] ?? ''), (string) $order['fulfillment_method'], (string) ($order['payment_provider'] ?? ''))); ?></span>
                        <span class="status-pill status-pill--success"><?= e(format_currency((float) ($order['total'] ?? 0))); ?></span>
                    </div>

                    <?php render_order_cash_change_section($order); ?>
                    <?php render_order_coupon_section($order); ?>

                    <div class="order-admin-card__section">
                        <strong>Itens do pedido</strong>
                        <ul>
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <?= e((string) $item['quantidade']); ?>x <?= e((string) $item['produto_nome']); ?>
                                    <?php if (!empty($item['sabor'])): ?>
                                        | Tamanho: <?= e((string) $item['sabor']); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="order-admin-card__section">
                        <strong>Entrega</strong>
                        <p><?= e((string) $order['endereco_snapshot']); ?></p>
                    </div>

                    <div class="order-admin-card__footer">
                        <small>Criado em <?= e(format_datetime_br((string) ($order['criado_em'] ?? ''))); ?></small>
                        <div class="order-admin-card__actions">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="button button--primary" type="submit">Aceitar pedido</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Recusar este pedido? O estoque reservado sera devolvido.');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="button button--danger" type="submit">Recusar pedido</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Excluir este pedido permanentemente? O estoque reservado sera devolvido se ainda nao tiver sido entregue.');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="button button--ghost" type="submit">Excluir</button>
                            </form>
                            <a class="button button--ghost button--small" href="<?= e($trackingUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir rastreio</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel-card panel-card--orders">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">gestor de pedidos</p>
            <h2>Pedidos em andamento</h2>
        </div>
    </div>

    <?php if (!$activeOrders): ?>
        <div class="empty-state empty-state--admin">Nenhum pedido em andamento no momento.</div>
    <?php else: ?>
        <div class="orders-board">
            <?php foreach ($activeOrders as $order): ?>
                <?php
                $items = $orderItemsByOrderId[(int) $order['id']] ?? [];
                $normalizedStatus = order_normalize_status((string) ($order['status'] ?? 'pending'));
                $fulfillmentMethod = storefront_normalize_checkout_method((string) ($order['fulfillment_method'] ?? 'delivery'));
                $trackingUrl = app_url('rastreio.php?codigo=' . rawurlencode((string) $order['codigo_rastreio']));
                $canReady = $normalizedStatus === 'approved' && $fulfillmentMethod === 'pickup';
                $canDispatch = $normalizedStatus === 'approved' && $fulfillmentMethod === 'delivery';
                $canComplete = in_array($normalizedStatus, ['approved', 'ready_pickup', 'out_for_delivery'], true);
                $canCancel = in_array($normalizedStatus, ['approved', 'ready_pickup', 'out_for_delivery'], true);
                ?>
                <article class="order-admin-card">
                    <div class="order-admin-card__header">
                        <div>
                            <span class="order-admin-card__kicker"><?= e(order_fulfillment_label($fulfillmentMethod)); ?></span>
                            <h3><?= e((string) $order['nome_cliente']); ?></h3>
                            <p><?= e(format_phone((string) ($order['telefone_cliente'] ?? ''))); ?></p>
                        </div>
                        <span class="status-pill <?= e(order_status_badge_class($normalizedStatus)); ?>">
                            <?= e(order_status_admin_label($normalizedStatus)); ?>
                        </span>
                    </div>

                    <div class="order-admin-card__badges">
                        <span class="status-pill status-pill--neutral">Rastreio: <?= e((string) $order['codigo_rastreio']); ?></span>
                        <span class="status-pill status-pill--neutral"><?= e(order_payment_label((string) ($order['payment_method'] ?? ''), $fulfillmentMethod, (string) ($order['payment_provider'] ?? ''))); ?></span>
                        <span class="status-pill status-pill--success"><?= e(format_currency((float) ($order['total'] ?? 0))); ?></span>
                    </div>

                    <?php render_order_cash_change_section($order); ?>
                    <?php render_order_coupon_section($order); ?>

                    <div class="order-admin-card__section">
                        <strong>Itens do pedido</strong>
                        <ul>
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <?= e((string) $item['quantidade']); ?>x <?= e((string) $item['produto_nome']); ?>
                                    <?php if (!empty($item['sabor'])): ?>
                                        | Tamanho: <?= e((string) $item['sabor']); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="order-admin-card__section">
                        <strong>Endereco</strong>
                        <p><?= e((string) $order['endereco_snapshot']); ?></p>
                    </div>

                    <div class="order-admin-card__footer">
                        <small>Atualizado em <?= e(format_datetime_br((string) ($order['status_atualizado_em'] ?? $order['criado_em'] ?? ''))); ?></small>
                        <div class="order-admin-card__actions">
                            <?php if ($canReady): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                    <input type="hidden" name="action" value="ready_pickup">
                                    <button class="button button--primary" type="submit">Pronto para retirada</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($canDispatch): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                    <input type="hidden" name="action" value="out_for_delivery">
                                    <button class="button button--primary" type="submit">Saiu para entrega</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($canComplete): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button class="button button--ghost" type="submit">Concluir pedido</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($canCancel): ?>
                                <form method="post" onsubmit="return confirm('Cancelar este pedido? O estoque reservado sera devolvido.');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button class="button button--danger" type="submit">Cancelar pedido</button>
                                </form>
                            <?php endif; ?>

                            <form method="post" onsubmit="return confirm('Excluir este pedido permanentemente? O estoque reservado sera devolvido se ainda nao tiver sido entregue.');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="button button--ghost" type="submit">Excluir</button>
                            </form>

                            <a class="button button--ghost button--small" href="<?= e($trackingUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir rastreio</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel-card panel-card--orders">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">historico</p>
            <h2>Pedidos concluidos</h2>
        </div>
    </div>

    <?php if (!$completedOrders): ?>
        <div class="empty-state empty-state--admin">Nenhum pedido concluido ainda.</div>
    <?php else: ?>
        <div class="orders-board">
            <?php foreach ($completedOrders as $order): ?>
                <?php
                $items = $orderItemsByOrderId[(int) $order['id']] ?? [];
                $trackingUrl = app_url('rastreio.php?codigo=' . rawurlencode((string) $order['codigo_rastreio']));
                ?>
                <article class="order-admin-card order-admin-card--completed">
                    <div class="order-admin-card__header">
                        <div>
                            <span class="order-admin-card__kicker"><?= e(order_fulfillment_label((string) $order['fulfillment_method'])); ?></span>
                            <h3><?= e((string) $order['nome_cliente']); ?></h3>
                            <p><?= e(format_phone((string) ($order['telefone_cliente'] ?? ''))); ?></p>
                        </div>
                        <span class="status-pill <?= e(order_status_badge_class((string) $order['status'])); ?>">
                            <?= e(order_status_admin_label((string) $order['status'])); ?>
                        </span>
                    </div>

                    <div class="order-admin-card__badges">
                        <span class="status-pill status-pill--neutral">Rastreio: <?= e((string) $order['codigo_rastreio']); ?></span>
                        <span class="status-pill status-pill--neutral"><?= e(order_payment_label((string) ($order['payment_method'] ?? ''), (string) $order['fulfillment_method'], (string) ($order['payment_provider'] ?? ''))); ?></span>
                        <span class="status-pill status-pill--success"><?= e(format_currency((float) ($order['total'] ?? 0))); ?></span>
                    </div>

                    <?php render_order_cash_change_section($order); ?>
                    <?php render_order_coupon_section($order); ?>

                    <div class="order-admin-card__section">
                        <strong>Itens do pedido</strong>
                        <ul>
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <?= e((string) $item['quantidade']); ?>x <?= e((string) $item['produto_nome']); ?>
                                    <?php if (!empty($item['sabor'])): ?>
                                        | Tamanho: <?= e((string) $item['sabor']); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="order-admin-card__section">
                        <strong>Endereco</strong>
                        <p><?= e((string) $order['endereco_snapshot']); ?></p>
                    </div>

                    <div class="order-admin-card__footer">
                        <small>Atualizado em <?= e(format_datetime_br((string) ($order['status_atualizado_em'] ?? $order['criado_em'] ?? ''))); ?></small>
                        <div class="order-admin-card__actions">
                            <form method="post" onsubmit="return confirm('Excluir este pedido concluido permanentemente?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="button button--ghost" type="submit">Excluir</button>
                            </form>
                            <a class="button button--ghost button--small" href="<?= e($trackingUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir rastreio</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel-card panel-card--orders">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">historico</p>
            <h2>Pedidos cancelados e recusados</h2>
        </div>
    </div>

    <?php if (!$cancelledOrders): ?>
        <div class="empty-state empty-state--admin">Nenhum pedido cancelado ou recusado.</div>
    <?php else: ?>
        <div class="orders-board">
            <?php foreach ($cancelledOrders as $order): ?>
                <?php
                $items = $orderItemsByOrderId[(int) $order['id']] ?? [];
                $trackingUrl = app_url('rastreio.php?codigo=' . rawurlencode((string) $order['codigo_rastreio']));
                ?>
                <article class="order-admin-card order-admin-card--cancelled">
                    <div class="order-admin-card__header">
                        <div>
                            <span class="order-admin-card__kicker"><?= e(order_fulfillment_label((string) $order['fulfillment_method'])); ?></span>
                            <h3><?= e((string) $order['nome_cliente']); ?></h3>
                            <p><?= e(format_phone((string) ($order['telefone_cliente'] ?? ''))); ?></p>
                        </div>
                        <span class="status-pill <?= e(order_status_badge_class((string) $order['status'])); ?>">
                            <?= e(order_status_admin_label((string) $order['status'])); ?>
                        </span>
                    </div>

                    <div class="order-admin-card__badges">
                        <span class="status-pill status-pill--neutral">Rastreio: <?= e((string) $order['codigo_rastreio']); ?></span>
                        <span class="status-pill status-pill--neutral"><?= e(order_payment_label((string) ($order['payment_method'] ?? ''), (string) $order['fulfillment_method'], (string) ($order['payment_provider'] ?? ''))); ?></span>
                        <span class="status-pill status-pill--success"><?= e(format_currency((float) ($order['total'] ?? 0))); ?></span>
                    </div>

                    <?php render_order_cash_change_section($order); ?>
                    <?php render_order_coupon_section($order); ?>

                    <div class="order-admin-card__section">
                        <strong>Itens do pedido</strong>
                        <ul>
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <?= e((string) $item['quantidade']); ?>x <?= e((string) $item['produto_nome']); ?>
                                    <?php if (!empty($item['sabor'])): ?>
                                        | Tamanho: <?= e((string) $item['sabor']); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="order-admin-card__section">
                        <strong>Endereco</strong>
                        <p><?= e((string) $order['endereco_snapshot']); ?></p>
                    </div>

                    <div class="order-admin-card__footer">
                        <small>Atualizado em <?= e(format_datetime_br((string) ($order['status_atualizado_em'] ?? $order['criado_em'] ?? ''))); ?></small>
                        <div class="order-admin-card__actions">
                            <form method="post" onsubmit="return confirm('Excluir este pedido permanentemente?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= e((string) $order['id']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="button button--ghost" type="submit">Excluir</button>
                            </form>
                            <a class="button button--ghost button--small" href="<?= e($trackingUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir rastreio</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
</div>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
