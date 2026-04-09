<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

extract(storefront_build_context(), EXTR_SKIP);

$trackingCode = order_normalize_tracking_code((string) ($_GET['codigo'] ?? ''));
$isNewOrder = (string) ($_GET['novo'] ?? '') === '1';
$order = $trackingCode !== '' ? find_order_by_tracking_code($trackingCode) : null;

$orderItems = $order ? fetch_order_items((int) $order['id']) : [];
$orderHistory = $order ? fetch_order_history((int) $order['id']) : [];
$trackingSteps = $order ? order_public_steps($order) : [];
$trackingRefreshUrl = $order
    ? app_url('rastreio.php?codigo=' . rawurlencode($trackingCode) . ($isNewOrder ? '&novo=1' : ''))
    : '';
$trackingLastHistory = $orderHistory !== []
    ? (string) ($orderHistory[count($orderHistory) - 1]['criado_em'] ?? '')
    : '';
$trackingPaymentSignature = $order
    ? implode('|', [
        (string) ($order['payment_status'] ?? ''),
        (string) ($order['payment_paid_at'] ?? ''),
        (string) ($order['payment_last_webhook_at'] ?? ''),
    ])
    : '';
$trackingSignature = $order
    ? implode('|', [
        (string) ($order['id'] ?? ''),
        (string) order_normalize_status((string) ($order['status'] ?? 'pending')),
        (string) ($order['status_atualizado_em'] ?? ''),
        (string) count($orderHistory),
        $trackingLastHistory,
        $trackingPaymentSignature,
    ])
    : '';

$pageTitle = 'Rastrear pedido | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body tracking-page';
$showSplash = false;

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <?php if ($trackingCode === ''): ?>
        <section class="empty-state empty-state--cart">
            <strong>Rastrear pedido</strong>
            <p>Digite o codigo de rastreio para acompanhar a aprovacao e o andamento do pedido.</p>

            <form class="tracking-search" method="get">
                <label class="tracking-search__label" for="tracking-code">Codigo de rastreio</label>
                <div class="tracking-search__row">
                    <input
                        id="tracking-code"
                        type="text"
                        name="codigo"
                        value="<?= e($trackingCode); ?>"
                        placeholder="Ex.: MT-270326-A8K4"
                        autocomplete="off"
                    >
                    <button class="btn btn--primary" type="submit">Rastrear</button>
                </div>
            </form>
        </section>
    <?php elseif (!$order): ?>
        <section class="empty-state empty-state--cart">
            <strong>Codigo nao encontrado.</strong>
            <p>Confira o codigo informado e tente novamente.</p>

            <form class="tracking-search" method="get">
                <label class="tracking-search__label" for="tracking-code">Codigo de rastreio</label>
                <div class="tracking-search__row">
                    <input
                        id="tracking-code"
                        type="text"
                        name="codigo"
                        value="<?= e($trackingCode); ?>"
                        placeholder="Ex.: MT-270326-A8K4"
                        autocomplete="off"
                    >
                    <button class="btn btn--primary" type="submit">Rastrear</button>
                </div>
            </form>
        </section>
    <?php else: ?>
        <?php
        $normalizedOrderStatus = order_normalize_status((string) ($order['status'] ?? 'pending'));
        $showCancelledHelp = in_array($normalizedOrderStatus, ['cancelled', 'rejected'], true);
        $cancelledTitle = $normalizedOrderStatus === 'rejected'
            ? 'Seu pedido foi recusado'
            : 'Seu pedido foi cancelado';
        $cancelledDescription = $normalizedOrderStatus === 'rejected'
            ? 'A loja recusou este pedido. Se quiser entender o motivo, fale com a loja informando o numero do pedido.'
            : 'A loja cancelou este pedido. Se quiser entender o motivo, fale com a loja informando o numero do pedido.';
        $fulfillmentMethod = (string) ($order['fulfillment_method'] ?? 'delivery');
        $orderStatusPrefix = match ($normalizedOrderStatus) {
            'completed' => '✅ ',
            'cancelled', 'rejected' => '❌ ',
            default => '',
        };
        $waitingCopy = static function (string $status, string $fulfillmentMethod): string {
            $pickup = storefront_normalize_checkout_method($fulfillmentMethod) === 'pickup';

            return match ($status) {
                'payment_confirmation' => 'Aguardando a confirmacao do pagamento online.',
                'approved' => 'Aguardando a loja aprovar o pedido para iniciar a separacao.',
                'ready_pickup' => 'Aguardando a loja finalizar a separacao para retirada.',
                'out_for_delivery' => 'Aguardando a loja liberar o pedido para entrega.',
                'completed' => $pickup
                    ? 'Aguardando a retirada ser concluida.'
                    : 'Aguardando a entrega ser concluida com sucesso.',
                'cancelled' => 'Aguardando uma definicao da loja sobre este pedido.',
                'rejected' => 'Aguardando a analise final da loja sobre este pedido.',
                default => 'Aguardando o andamento desta etapa.',
            };
        };
        $expectedTitles = [];
        foreach ($trackingSteps as $step) {
            $stepStatus = (string) ($step['status'] ?? 'pending');

            if ($stepStatus === 'payment_confirmation') {
                continue;
            }

            $expectedTitles[$stepStatus] = strtolower((string) (order_history_copy($stepStatus, $fulfillmentMethod)['titulo'] ?? ''));
        }
        $historyByStatus = [];
        foreach ($orderHistory as $history) {
            $historyStatus = order_normalize_status((string) ($history['status'] ?? 'pending'));
            if (!array_key_exists($historyStatus, $expectedTitles)) {
                continue;
            }

            $historyTitle = strtolower(trim((string) ($history['titulo'] ?? '')));
            $matchesExpected = $historyTitle !== '' && $historyTitle === $expectedTitles[$historyStatus];
            if (!isset($historyByStatus[$historyStatus]) || $matchesExpected) {
                $historyByStatus[$historyStatus] = $history;
            }
        }
        $timelineItems = [];
        foreach ($trackingSteps as $step) {
            $stepStatus = (string) ($step['status'] ?? 'pending');

            if ($stepStatus === 'payment_confirmation') {
                $paymentStatus = order_normalize_payment_status((string) ($order['payment_status'] ?? 'none'));
                $paymentConfirmed = in_array($paymentStatus, ['authorized', 'paid'], true);
                $paymentDate = trim((string) ($order['payment_paid_at'] ?? '')) !== ''
                    ? format_datetime_br((string) ($order['payment_paid_at'] ?? ''))
                    : 'Aguardando';

                $timelineItems[] = [
                    'title' => 'Confirmacao de pagamento',
                    'description' => $paymentConfirmed
                        ? 'O pagamento online foi confirmado. Agora a loja pode seguir com a aprovacao do pedido.'
                        : 'Aguardando a confirmacao do pagamento online para liberar a aprovacao do pedido.',
                    'date' => $paymentDate,
                    'icon' => $paymentConfirmed ? 'âœ…' : 'ðŸ¤”',
                    'class' => $paymentConfirmed ? 'is-complete' : 'is-pending',
                ];
                continue;
            }

            $normalizedStepStatus = order_normalize_status($stepStatus);
            $stepCopy = order_history_copy($normalizedStepStatus, $fulfillmentMethod);
            $history = $historyByStatus[$normalizedStepStatus] ?? null;
            $isFailedStep = in_array($normalizedStepStatus, ['cancelled', 'rejected'], true);
            $isCompletedStep = !$isFailedStep && (!empty($step['active']) || $history !== null);
            $timelineItems[] = [
                'title' => (string) ($history['titulo'] ?? $stepCopy['titulo'] ?? 'Atualizacao'),
                'description' => (string) ($history['descricao'] ?? ($isCompletedStep
                    ? ($stepCopy['descricao'] ?? '')
                    : $waitingCopy($normalizedStepStatus, $fulfillmentMethod))),
                'date' => $history !== null
                    ? format_datetime_br((string) ($history['criado_em'] ?? ''))
                    : 'Aguardando',
                'icon' => $isFailedStep ? '❌' : ($isCompletedStep ? '✅' : '🤔'),
                'class' => $isFailedStep ? 'is-failed' : ($isCompletedStep ? 'is-complete' : 'is-pending'),
            ];
        }
        $normalizedPaymentStatus = order_normalize_payment_status((string) ($order['payment_status'] ?? 'none'));
        $showTrackingPix = (string) ($order['payment_provider'] ?? '') === 'pagbank'
            && order_uses_online_payment(
                (string) ($order['fulfillment_method'] ?? 'delivery'),
                (string) ($order['payment_method'] ?? ''),
                (string) ($order['payment_provider'] ?? '')
            )
            && trim((string) ($order['payment_pix_text'] ?? '')) !== ''
            && in_array($normalizedPaymentStatus, ['waiting', 'authorized'], true);
        $trackingPixCode = trim((string) ($order['payment_pix_text'] ?? ''));
        $trackingPixImage = trim((string) ($order['payment_pix_image_base64'] ?? ''));
        ?>
        <div
            data-tracking-live-root
            data-tracking-live-url="<?= e($trackingRefreshUrl); ?>"
            data-tracking-live-signature="<?= e($trackingSignature); ?>"
            data-tracking-live-poll="5000"
        >
            <?php if ($isNewOrder && $order): ?>
                <section class="catalog-section tracking-success">
                    <span class="catalog-section__eyebrow">pedido recebido</span>
                    <h2>Seu pedido foi enviado com sucesso</h2>
                    <p>Guarde este codigo para consultar o andamento quando quiser.</p>
                    <strong><?= e((string) $order['codigo_rastreio']); ?></strong>
                </section>
            <?php endif; ?>

            <section class="tracking-layout tracking-layout--single">
                <div class="tracking-main">
                    <section class="catalog-section tracking-card">
                        <div class="tracking-card__top">
                            <div>
                                <h2><?= e((string) $order['codigo_rastreio']); ?></h2>
                            </div>
                            <span class="status-pill <?= e(order_status_badge_class((string) $order['status'])); ?>">
                                <?= e($orderStatusPrefix . order_status_label((string) $order['status'])); ?>
                            </span>
                        </div>
                    </section>

                    <?php if ($showCancelledHelp): ?>
                        <section class="catalog-section tracking-card tracking-card--cancelled-notice">
                            <span class="catalog-section__eyebrow">atencao</span>
                            <h2><?= e('❌ ' . $cancelledTitle); ?></h2>
                            <p><?= e($cancelledDescription); ?></p>

                            <div class="tracking-summary__rows">
                                <div class="tracking-summary__row">
                                    <span>Numero do pedido</span>
                                    <strong><?= e((string) $order['codigo_rastreio']); ?></strong>
                                </div>
                            </div>

                            <a
                                class="btn btn--primary"
                                href="<?= e(storefront_order_support_link($storeSettings, $order)); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Tirar duvidas sobre este pedido
                            </a>
                        </section>
                    <?php endif; ?>

                    <section class="catalog-section tracking-card">
                        <h2>Andamento do pedido</h2>

                        <div class="tracking-timeline">
                            <?php foreach ($timelineItems as $timelineItem): ?>
                                <article class="tracking-timeline__item <?= e((string) $timelineItem['class']); ?>">
                                    <div class="tracking-timeline__marker" aria-hidden="true">
                                        <span class="tracking-timeline__marker-icon"><?= e((string) $timelineItem['icon']); ?></span>
                                    </div>
                                    <div class="tracking-timeline__content">
                                        <div class="tracking-timeline__meta">
                                            <strong>
                                                <?= e((string) $timelineItem['title']); ?>
                                            </strong>
                                            <span><?= e((string) $timelineItem['date']); ?></span>
                                        </div>
                                        <p><?= e((string) $timelineItem['description']); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <?php if ($showTrackingPix): ?>
                        <section class="catalog-section tracking-card tracking-card--pix">
                            <span class="catalog-section__eyebrow">pix pagbank</span>
                            <h2>Finalize o pagamento pelo Pix</h2>
                            <p>Escaneie o QR Code abaixo ou copie o codigo Pix. Assim que o PagBank confirmar o pagamento, esta tela atualiza sozinha.</p>

                            <?php if ($trackingPixImage !== ''): ?>
                                <div class="tracking-pix__qr">
                                    <img src="<?= e($trackingPixImage); ?>" alt="QR Code Pix do pedido <?= e((string) ($order['codigo_rastreio'] ?? '')); ?>">
                                </div>
                            <?php endif; ?>

                            <div class="tracking-pix__copy">
                                <textarea readonly><?= e($trackingPixCode); ?></textarea>
                                <div class="tracking-pix__actions">
                                    <button
                                        class="btn btn--primary"
                                        type="button"
                                        data-copy-text="<?= e($trackingPixCode); ?>"
                                        data-copy-label-default="Copiar codigo Pix"
                                        data-copy-label-success="Codigo copiado"
                                        data-copy-label-error="Nao foi possivel copiar"
                                    >
                                        Copiar codigo Pix
                                    </button>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="catalog-section tracking-card">
                        <h2>Resumo do pedido</h2>

                        <div class="tracking-items">
                            <?php foreach ($orderItems as $item): ?>
                                <article class="tracking-item">
                                    <div class="tracking-item__media">
                                        <?php if (!empty($item['imagem'])): ?>
                                            <img src="<?= e(app_url((string) $item['imagem'])); ?>" alt="<?= e((string) ($item['produto_nome'] ?? 'Produto')); ?>" loading="lazy">
                                        <?php else: ?>
                                            <span>Sem imagem</span>
                                        <?php endif; ?>
                                    </div>
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

                        <div class="tracking-summary__rows tracking-summary__rows--order">
                            <div class="tracking-summary__row tracking-summary__row--total">
                                <span>Total do pedido</span>
                                <strong><?= e(format_currency((float) ($order['total'] ?? 0))); ?></strong>
                            </div>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
