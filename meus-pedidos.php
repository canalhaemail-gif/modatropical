<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_auth();

$storeSettings = fetch_store_settings();
$customer = current_customer();
$orders = fetch_customer_orders((int) ($customer['id'] ?? 0));
$pageTitle = 'Meus Pedidos';
$bodyClass = 'storefront-body public-body--customer customer-orders-page';
$extraStylesheets = ['assets/css/public-auth.css'];

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <?php require BASE_PATH . '/includes/customer_area_topbar.php'; ?>

    <section class="account-layout account-layout--single">
        <article class="account-card">
            <div class="account-card__header">
                <span class="auth-form-card__badge">Pedidos</span>
                <h2>Meus pedidos</h2>
                <p>Veja todos os pedidos da sua conta, acompanhe o status atual e abra o andamento completo quando quiser.</p>
            </div>

            <?php if ($orders === []): ?>
                <div class="account-saved-empty">
                    <strong>Voce ainda nao fez pedidos.</strong>
                    <p>Assim que concluir uma compra, ela vai aparecer aqui com o status e o historico.</p>
                </div>

                <div class="account-card__actions">
                    <a class="btn btn--ghost" href="<?= e(app_url()); ?>">Voltar para a vitrine</a>
                </div>
            <?php else: ?>
                <p class="customer-orders-summary">
                    <?= e((string) count($orders)); ?> pedido(s) encontrado(s) na sua conta.
                </p>
            <?php endif; ?>
        </article>

        <?php foreach ($orders as $order): ?>
            <?php
            $orderId = (int) ($order['id'] ?? 0);
            $orderItems = fetch_order_items($orderId);
            $orderHistory = fetch_order_history($orderId);
            $lastHistory = $orderHistory !== []
                ? $orderHistory[count($orderHistory) - 1]
                : null;
            $trackingUrl = app_url('rastreio.php?codigo=' . rawurlencode((string) ($order['codigo_rastreio'] ?? '')));
            $itemCount = array_reduce(
                $orderItems,
                static fn(int $carry, array $item): int => $carry + max(0, (int) ($item['quantidade'] ?? 0)),
                0
            );
            $firstItemName = trim((string) ($orderItems[0]['produto_nome'] ?? 'Pedido sem itens'));
            $remainingItemsCount = max(0, count($orderItems) - 1);
            $orderPreview = $remainingItemsCount > 0
                ? $firstItemName . ' +' . $remainingItemsCount . ' item(ns)'
                : $firstItemName;
            ?>
            <details class="account-card customer-order-accordion">
                <summary class="customer-order-accordion__summary">
                    <div class="customer-order-accordion__main">
                        <span class="auth-form-card__badge">Pedido</span>
                        <strong class="customer-order-accordion__code"><?= e((string) ($order['codigo_rastreio'] ?? '')); ?></strong>
                        <p class="customer-order-accordion__preview"><?= e($orderPreview); ?></p>
                    </div>
                    <div class="customer-order-accordion__side">
                        <span class="status-pill <?= e(order_status_badge_class((string) ($order['status'] ?? 'pending'))); ?>">
                            <?= e(order_status_label((string) ($order['status'] ?? 'pending'))); ?>
                        </span>
                        <small><?= e(format_datetime_br((string) ($order['criado_em'] ?? ''))); ?></small>
                        <strong><?= e(format_currency((float) ($order['total'] ?? 0))); ?></strong>
                    </div>
                    <span class="customer-order-accordion__chevron" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </span>
                </summary>

                <div class="customer-order-accordion__content">
                    <div class="tracking-summary__rows customer-order-accordion__rows">
                        <div class="tracking-summary__row">
                            <span>Feito em</span>
                            <strong><?= e(format_datetime_br((string) ($order['criado_em'] ?? ''))); ?></strong>
                        </div>
                        <div class="tracking-summary__row">
                            <span>Recebimento</span>
                            <strong><?= e(order_fulfillment_label((string) ($order['fulfillment_method'] ?? 'delivery'))); ?></strong>
                        </div>
                        <div class="tracking-summary__row">
                            <span>Pagamento</span>
                            <strong><?= e(order_payment_label((string) ($order['payment_method'] ?? ''), (string) ($order['fulfillment_method'] ?? 'delivery'), (string) ($order['payment_provider'] ?? ''))); ?></strong>
                        </div>
                        <div class="tracking-summary__row">
                            <span>Status do pagamento</span>
                            <strong>
                                <span class="status-pill <?= e(order_payment_status_badge_class((string) ($order['payment_status'] ?? 'none'))); ?>">
                                    <?= e(order_payment_status_label((string) ($order['payment_status'] ?? 'none'))); ?>
                                </span>
                            </strong>
                        </div>
                        <div class="tracking-summary__row">
                            <span>Itens no pedido</span>
                            <strong><?= e((string) $itemCount); ?></strong>
                        </div>
                    </div>

                    <?php if ($lastHistory): ?>
                        <div class="customer-order-card__note">
                            <strong><?= e((string) ($lastHistory['titulo'] ?? 'Atualizacao do pedido')); ?></strong>
                            <p>
                                <?= e((string) ($lastHistory['descricao'] ?? 'Seu pedido recebeu uma nova atualizacao.')); ?>
                                <span><?= e(format_datetime_br((string) ($lastHistory['criado_em'] ?? ''))); ?></span>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="tracking-items">
                        <?php foreach ($orderItems as $item): ?>
                            <article class="tracking-item">
                                <div class="tracking-item__copy">
                                    <strong><?= e((string) ($item['produto_nome'] ?? 'Produto')); ?></strong>
                                    <p>
                                        <?= e((string) ($item['quantidade'] ?? 0)); ?>x
                                        <?php if (!empty($item['sabor'])): ?>
                                            | Tamanho: <?= e((string) $item['sabor']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span><?= e(format_currency((float) ($item['subtotal_item'] ?? 0))); ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="account-card__actions customer-order-card__actions">
                        <a class="btn btn--ghost" href="<?= e($trackingUrl); ?>">Ver andamento completo</a>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
