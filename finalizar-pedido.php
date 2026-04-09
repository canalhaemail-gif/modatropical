<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

require_customer_auth();

$cart = storefront_selected_cart(storefront_build_cart());

if ($cart['items'] === []) {
    set_flash('error', 'Selecione pelo menos um item do carrinho para finalizar.');
    redirect('carrinho.php');
}

extract(storefront_build_context(), EXTR_SKIP);
$checkoutWallet = $currentCustomer ? fetch_customer_coupon_wallet((int) ($currentCustomer['id'] ?? 0)) : [];
$checkoutRedeemedCoupons = array_values(array_filter(
    $checkoutWallet,
    static fn(array $coupon): bool => !empty($coupon['is_redeemed']) && !empty($coupon['is_active_now'])
));

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('finalizar-pedido.php');
    }

    $postedPaymentMethod = storefront_resolve_checkout_payment_input(
        (string) posted_value('payment_scope', ''),
        (string) posted_value('payment_method', ''),
        (string) posted_value('delivery_payment_method', ''),
        (string) posted_value('online_payment_method', '')
    );

    if ((string) posted_value('action') === 'save_checkout_options') {
        storefront_save_checkout_selection(
            'delivery',
            $postedPaymentMethod,
            posted_value('cash_change_for'),
            posted_value('cash_change_choice')
        );

        redirect('finalizar-pedido.php');
    }

    if ((string) posted_value('action') === 'apply_coupon') {
        $selection = storefront_current_checkout_selection($currentCustomer);
        $couponCode = (string) posted_value('coupon_code');
        $couponPreview = storefront_build_cart_checkout(
            $storeSettings,
            $currentCustomer,
            $cart,
            'delivery',
            $selection['payment_method'] ?? null,
            $selection['cash_change_for'] ?? null,
            $couponCode
        );

        if (!empty($couponPreview['coupon_valid'])) {
            storefront_save_coupon_code((string) ($couponPreview['coupon_code'] ?? $couponCode));
            set_flash('success', 'Cupom aplicado com sucesso.');
        } else {
            storefront_clear_coupon_code();
            set_flash('error', (string) ($couponPreview['coupon_error'] ?? 'Nao foi possivel aplicar o cupom.'));
        }

        redirect('finalizar-pedido.php');
    }

    if ((string) posted_value('action') === 'apply_saved_coupon') {
        $couponId = (int) posted_value('coupon_id');
        $coupon = $currentCustomer ? find_customer_coupon_wallet_entry((int) ($currentCustomer['id'] ?? 0), $couponId) : null;

        if (!$coupon || empty($coupon['is_redeemed'])) {
            storefront_clear_coupon_code();
            set_flash('error', 'Resgate esse cupom antes de usar.');
            redirect('finalizar-pedido.php');
        }

        if (empty($coupon['is_active_now'])) {
            storefront_clear_coupon_code();
            set_flash('error', 'Esse cupom nao esta disponivel no momento.');
            redirect('finalizar-pedido.php');
        }

        $selection = storefront_current_checkout_selection($currentCustomer);
        $couponPreview = storefront_build_cart_checkout(
            $storeSettings,
            $currentCustomer,
            $cart,
            'delivery',
            $selection['payment_method'] ?? null,
            $selection['cash_change_for'] ?? null,
            (string) ($coupon['codigo'] ?? '')
        );

        if (!empty($couponPreview['coupon_valid'])) {
            storefront_save_coupon_code((string) ($coupon['codigo'] ?? ''));
            set_flash('success', 'Cupom aplicado com sucesso.');
        } else {
            storefront_clear_coupon_code();
            set_flash('error', (string) ($couponPreview['coupon_error'] ?? 'Nao foi possivel aplicar esse cupom.'));
        }

        redirect('finalizar-pedido.php');
    }

    if ((string) posted_value('action') === 'remove_coupon') {
        storefront_clear_coupon_code();
        set_flash('success', 'Cupom removido.');
        redirect('finalizar-pedido.php');
    }

    if ((string) posted_value('action') === 'place_order') {
        $selectedFulfillment = 'delivery';
        $selectedPayment = $postedPaymentMethod;
        $cashChangeFor = posted_value('cash_change_for');
        $cashChangeChoice = posted_value('cash_change_choice');

        storefront_save_checkout_selection($selectedFulfillment, $selectedPayment, $cashChangeFor, $cashChangeChoice);
        $checkout = storefront_build_cart_checkout(
            $storeSettings,
            $currentCustomer,
            $cart,
            $selectedFulfillment,
            $selectedPayment,
            $cashChangeFor,
            storefront_coupon_session_code()
        );

        try {
            $order = create_storefront_order($storeSettings, $currentCustomer, $cart, $checkout);
            storefront_cart_remove_items(array_map(
                static fn(array $item): string => (string) ($item['key'] ?? ''),
                $cart['items']
            ));
            unset($_SESSION['storefront_checkout']);
            storefront_clear_coupon_code();

            if (!empty($order['payment_checkout_url'])) {
                redirect((string) $order['payment_checkout_url']);
            }

            redirect('rastreio.php?codigo=' . rawurlencode((string) $order['codigo_rastreio']) . '&novo=1');
        } catch (RuntimeException $exception) {
            error_log('[finalizar-pedido] Falha ao concluir pedido: ' . $exception->getMessage());
            set_flash('error', $exception->getMessage());
            redirect('finalizar-pedido.php');
        } catch (Throwable $exception) {
            $rawMessage = trim($exception->getMessage());
            $debugMessage = $rawMessage !== ''
                ? $rawMessage
                : ('Erro interno do tipo ' . get_class($exception) . '.');

            error_log(
                '[finalizar-pedido] Erro interno ao concluir pedido: '
                . $debugMessage
                . "\n"
                . $exception->getTraceAsString()
            );
            set_flash('error', 'Nao foi possivel concluir o pedido agora. Motivo: ' . $debugMessage);
            redirect('finalizar-pedido.php');
        }
    }
}

$forcedCheckoutSelection = storefront_current_checkout_selection($currentCustomer, 'delivery', null, null);
storefront_save_checkout_selection(
    'delivery',
    (string) ($forcedCheckoutSelection['payment_method'] ?? ''),
    $forcedCheckoutSelection['cash_change_for'] ?? null,
    $forcedCheckoutSelection['cash_change_choice'] ?? null
);
$checkout = storefront_build_cart_checkout(
    $storeSettings,
    $currentCustomer,
    $cart,
    'delivery',
    (string) ($forcedCheckoutSelection['payment_method'] ?? ''),
    $forcedCheckoutSelection['cash_change_for'] ?? null,
    storefront_coupon_session_code()
);
$onlinePaymentProviderName = online_payment_provider_public_name();
$onlinePaymentDescription = online_payment_redirect_description();
$checkoutOnlineMethod = (string) ($checkout['online_payment_method'] ?? '');
$checkoutOnlineCardReady = !empty($checkout['online_card_ready']);
$checkoutCashChoiceNeedsChange = (string) ($checkout['payment_method'] ?? '') === 'cash'
    && (string) ($checkout['cash_change_choice'] ?? '') === 'change';
$checkoutCashChoiceNoChange = (string) ($checkout['payment_method'] ?? '') === 'cash'
    && (string) ($checkout['cash_change_choice'] ?? '') === 'exact';
$checkoutTotalRawValue = number_format((float) ($checkout['total'] ?? 0), 2, '.', '');
$checkoutTotalInputValue = number_format((float) ($checkout['total'] ?? 0), 2, ',', '.');
$isPickup = (string) ($checkout['fulfillment_method'] ?? '') === 'pickup';
$offlinePaymentLabel = $isPickup ? 'Pagar na retirada' : 'Pagar na entrega';
$offlinePaymentDescription = $isPickup
    ? 'Escolha se vai pagar no cartao na retirada ou em dinheiro com troco.'
    : 'Escolha se vai pagar no cartao da entrega ou em dinheiro com troco.';
$cardOfflineLabel = $isPickup ? 'Cartao na retirada' : 'Cartao na entrega';
$cardOfflineDescription = $isPickup
    ? 'Pague no debito ou no credito quando retirar o pedido.'
    : 'Pague no debito ou no credito quando o pedido chegar.';
$cashOfflineLabel = $isPickup ? 'Dinheiro na retirada' : 'Dinheiro na entrega';
$deliveryAddressText = !empty($checkout['delivery_address'])
    ? build_customer_address_string(
        (string) ($checkout['delivery_address']['rua'] ?? ''),
        (string) ($checkout['delivery_address']['bairro'] ?? ''),
        (string) ($checkout['delivery_address']['numero'] ?? ''),
        (string) ($checkout['delivery_address']['complemento'] ?? ''),
        (string) ($checkout['delivery_address']['cidade'] ?? ''),
        (string) ($checkout['delivery_address']['uf'] ?? '')
    )
    : '';
$deliveryAddressData = is_array($checkout['delivery_address'] ?? null) ? (array) $checkout['delivery_address'] : [];
$checkoutCustomerName = trim((string) ($currentCustomer['nome'] ?? ''));
$checkoutCustomerPhone = format_phone((string) ($currentCustomer['telefone'] ?? ''));
$checkoutCustomerPhoneDigits = preg_replace('/\D+/', '', (string) ($currentCustomer['telefone'] ?? ''));
$checkoutCustomerPhoneDisplay = $checkoutCustomerPhone;
if ($checkoutCustomerPhoneDigits !== null && strlen($checkoutCustomerPhoneDigits) >= 10) {
    $checkoutPhoneAreaCode = substr($checkoutCustomerPhoneDigits, 0, 2);
    $checkoutPhoneLocal = substr($checkoutCustomerPhoneDigits, 2);
    if (strlen($checkoutPhoneLocal) === 9) {
        $checkoutPhoneLocal = substr($checkoutPhoneLocal, 0, 5) . '-' . substr($checkoutPhoneLocal, 5);
    } elseif (strlen($checkoutPhoneLocal) === 8) {
        $checkoutPhoneLocal = substr($checkoutPhoneLocal, 0, 4) . '-' . substr($checkoutPhoneLocal, 4);
    }
    $checkoutCustomerPhoneDisplay = '(+55) ' . $checkoutPhoneAreaCode . ' ' . $checkoutPhoneLocal;
}
$checkoutCustomerCpf = format_cpf((string) ($currentCustomer['cpf'] ?? ''));
$checkoutAddressStreetLine = trim(implode(', ', array_filter([
    trim((string) ($deliveryAddressData['rua'] ?? '')),
    trim((string) ($deliveryAddressData['numero'] ?? '')),
    trim((string) ($deliveryAddressData['complemento'] ?? '')),
])));
$checkoutUf = strtoupper(trim((string) ($deliveryAddressData['uf'] ?? '')));
$checkoutUfLabel = [
    'RJ' => 'Rio de Janeiro',
][$checkoutUf] ?? $checkoutUf;
$checkoutAddressLocationLine = trim(implode(', ', array_filter([
    trim((string) ($deliveryAddressData['cidade'] ?? '')),
    $checkoutUfLabel,
    format_cep((string) ($deliveryAddressData['cep'] ?? '')),
])));
$pickupAddressText = trim((string) ($storeSettings['endereco'] ?? ''));
$checkoutStateTone = 'success';
$checkoutStateEyebrow = $isPickup ? 'retirada' : 'entrega';
$checkoutStateTitle = $isPickup ? 'Retirada sem taxa' : 'Entrega disponivel';
$checkoutStateMessage = $isPickup
    ? 'Seu pedido sera retirado na loja, sem custo adicional de entrega.'
    : storefront_delivery_notice_text();
$checkoutStateActionHref = '';
$checkoutStateActionLabel = '';

if (!$isPickup && !empty($checkout['requires_address'])) {
    $checkoutStateTone = 'warning';
    $checkoutStateTitle = 'Endereco necessario';
    $checkoutStateMessage = 'Cadastre um endereco em Volta Redonda-RJ ou Barra Mansa-RJ para liberar a entrega.';
    $checkoutStateActionHref = app_url('editar-enderecos.php');
    $checkoutStateActionLabel = 'Cadastrar endereco';
} elseif (!$isPickup && !empty($checkout['delivery_unavailable'])) {
    $checkoutStateTone = 'warning';
    $checkoutStateTitle = 'Endereco fora da area';
    $checkoutStateMessage = 'Seu endereco atual nao atende a area de entrega. Ajuste o endereco ou escolha retirada na loja.';
    $checkoutStateActionHref = app_url('editar-enderecos.php');
    $checkoutStateActionLabel = 'Alterar endereco';
} elseif (!$isPickup && $deliveryAddressText !== '') {
    $checkoutStateActionHref = app_url('editar-enderecos.php');
    $checkoutStateActionLabel = 'Alterar endereco';
}
$checkoutAddressStripClass = !$isPickup && (!empty($checkout['requires_address']) || !empty($checkout['delivery_unavailable']))
    ? 'checkout-address-strip checkout-address-strip--warning'
    : 'checkout-address-strip';
$checkoutAddressActionLabel = '';

if (!$isPickup) {
    $checkoutAddressActionLabel = $deliveryAddressText !== '' ? 'Alterar endereco' : 'Cadastrar endereco';
}

$checkoutPaymentTitle = 'Pagamento online';
$checkoutPaymentMessage = 'Ao confirmar, voce sera redirecionado para a tela segura do ' . $onlinePaymentProviderName . '.';

if ((string) ($checkout['payment_method'] ?? '') === 'cash') {
    $checkoutPaymentTitle = $isPickup ? 'Dinheiro na retirada' : 'Dinheiro na entrega';
    $checkoutPaymentMessage = !empty($checkout['cash_change_for'])
        ? 'Troco para ' . format_currency((float) ($checkout['cash_change_for'] ?? 0)) . '.'
        : 'Informe para quanto precisa de troco antes de confirmar o pedido.';
} elseif ((string) ($checkout['payment_method'] ?? '') === 'card') {
    $checkoutPaymentTitle = $isPickup ? 'Cartao na retirada' : 'Cartao na entrega';
    $checkoutPaymentMessage = $isPickup
        ? 'O pagamento no cartao sera feito quando voce retirar o pedido.'
        : 'O pagamento no cartao sera feito quando o pedido chegar ao endereco.';
}

$checkoutPrimaryButtonLabel = match ((string) ($checkout['payment_method'] ?? '')) {
    'pix' => 'Gerar Pix',
    'online_card' => 'Seguir com cartao',
    default => 'Confirmar pedido',
};
$checkoutFinalizeTitle = 'Falta um ajuste para finalizar';
$checkoutFinalizeMessage = $checkoutStateMessage;
$checkoutFinalizeActionHref = $checkoutStateActionHref;
$checkoutFinalizeActionLabel = $checkoutStateActionLabel;

if ((string) ($checkout['payment_method'] ?? '') === '') {
    $checkoutFinalizeMessage = 'Escolha a forma de pagamento para seguir com o pedido.';
    $checkoutFinalizeActionHref = '';
    $checkoutFinalizeActionLabel = '';
} elseif ((string) ($checkout['payment_method'] ?? '') === 'online_card' && !$checkoutOnlineCardReady) {
    $checkoutFinalizeMessage = 'Cartao PagBank sera liberado na proxima etapa. Por enquanto, finalize por Pix ou escolha pagamento na entrega.';
    $checkoutFinalizeActionHref = '';
    $checkoutFinalizeActionLabel = '';
} elseif ((string) ($checkout['payment_method'] ?? '') === 'cash' && !empty($checkout['cash_requires_input']) && empty($checkout['cash_change_valid'])) {
    $checkoutFinalizeMessage = 'Informe um valor valido para o troco ou escolha outra forma de pagamento.';
    $checkoutFinalizeActionHref = '';
    $checkoutFinalizeActionLabel = '';
}

$pageTitle = 'Finalizar pedido | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body checkout-page';
$showSplash = false;
$paymentHeadingImagePath = is_file(BASE_PATH . '/pagamento.png') ? 'pagamento.png' : null;
$paymentHeadingImageRenderUrl = $paymentHeadingImagePath !== null
    ? preferred_critical_image_render_url($paymentHeadingImagePath)
    : app_url('pagamento.png');
$criticalImageCandidates = $criticalImageCandidates ?? [];

if ($paymentHeadingImagePath !== null && !str_starts_with($paymentHeadingImageRenderUrl, 'data:image/')) {
    $criticalImageCandidates[] = $paymentHeadingImagePath;
}

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <section class="storefront-page-heading storefront-page-heading--checkout">
        <div class="storefront-page-heading__actions">
            <a class="btn btn--primary checkout-nav-button checkout-nav-button--store" href="<?= e(app_url()); ?>">
                <span>Voltar para a loja</span>
            </a>
            <a class="btn btn--light checkout-nav-button checkout-nav-button--cart" href="<?= e(app_url('carrinho.php')); ?>">
                <span class="checkout-nav-button__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                        <path d="M7 7V6a5 5 0 0 1 10 0v1h2.08a1 1 0 0 1 1 .9l1.2 12A3 3 0 0 1 18.3 23H5.7a3 3 0 0 1-2.98-3.1l1.2-12a1 1 0 0 1 1-.9H7Zm2 0h6V6a3 3 0 0 0-6 0v1Zm-3.18 2-.95 9.5A1 1 0 0 0 5.86 20h12.28a1 1 0 0 0 .99-1.5L18.18 9H17v2a1 1 0 1 1-2 0V9H9v2a1 1 0 1 1-2 0V9H5.82Z"></path>
                    </svg>
                </span>
                <span>Voltar ao carrinho</span>
            </a>
        </div>
    </section>

    <section class="<?= e($checkoutAddressStripClass); ?>">
        <?php if (!$isPickup && $deliveryAddressText !== ''): ?>
            <div class="checkout-address-card">
                <div class="checkout-address-card__main">
                    <span class="checkout-address-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Zm0-9a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"></path>
                        </svg>
                    </span>

                    <div class="checkout-address-card__copy">
                        <div class="checkout-address-card__identity">
                            <?php if ($checkoutCustomerName !== ''): ?>
                                <strong><?= e($checkoutCustomerName); ?></strong>
                            <?php endif; ?>
                            <?php if ($checkoutCustomerPhoneDisplay !== ''): ?>
                                <span><?= e($checkoutCustomerPhoneDisplay); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="checkout-address-card__lines">
                            <?php if ($checkoutAddressStreetLine !== ''): ?>
                                <p><?= e($checkoutAddressStreetLine); ?></p>
                            <?php endif; ?>
                            <?php if ($checkoutAddressLocationLine !== ''): ?>
                                <p><?= e($checkoutAddressLocationLine); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($checkoutStateActionHref !== '' && $checkoutAddressActionLabel !== ''): ?>
                        <a class="checkout-address-card__action" href="<?= e($checkoutStateActionHref); ?>" aria-label="<?= e($checkoutAddressActionLabel); ?>">
                            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                <path d="M9.3 5.3a1 1 0 0 0 0 1.4L14.59 12 9.3 17.3a1 1 0 1 0 1.4 1.4l6-6a1 1 0 0 0 0-1.4l-6-6a1 1 0 0 0-1.4 0Z"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($checkoutCustomerCpf !== ''): ?>
                    <div class="checkout-address-card__cpf">
                        <div class="checkout-address-card__cpf-label">
                            <span class="checkout-address-card__cpf-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5v-13Zm2.5-.5a.5.5 0 0 0-.5.5v13a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5v-13a.5.5 0 0 0-.5-.5h-11ZM8 8a1 1 0 0 1 1-1h4.5a1 1 0 1 1 0 2H9a1 1 0 0 1-1-1Zm0 4a1 1 0 0 1 1-1h3a1 1 0 1 1 0 2H9a1 1 0 0 1-1-1Zm7-1.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Zm0 1.8a.7.7 0 1 0 0 1.4.7.7 0 0 0 0-1.4Z"></path>
                                </svg>
                            </span>
                            <strong>CPF</strong>
                        </div>
                        <span class="checkout-address-card__cpf-value"><?= e($checkoutCustomerCpf); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="checkout-address-strip__content">
                <p><?= e($checkoutStateMessage); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$isPickup && $deliveryAddressText === '' && $checkoutStateActionHref !== '' && $checkoutAddressActionLabel !== ''): ?>
            <div class="checkout-address-strip__actions">
                <a class="btn btn--ghost" href="<?= e($checkoutStateActionHref); ?>"><?= e($checkoutAddressActionLabel); ?></a>
            </div>
        <?php endif; ?>
    </section>

    <section class="checkout-order-sheet">
        <div class="checkout-order-sheet__table-head">
            <span>Produtos do pedido</span>
            <span>Preco unitario</span>
            <span>Qtd</span>
            <span>Subtotal</span>
        </div>

        <div class="checkout-order-sheet__list">
            <?php foreach (($cart['items'] ?? []) as $item): ?>
                <?php $product = $item['product'] ?? []; ?>
                <article class="checkout-order-item">
                    <div class="checkout-order-item__product">
                        <a class="checkout-order-item__media" href="<?= e(storefront_product_url((string) ($product['slug'] ?? ''))); ?>">
                            <?php if (!empty($product['imagem'])): ?>
                                <img src="<?= e(app_url((string) $product['imagem'])); ?>" alt="<?= e((string) ($product['nome'] ?? 'Produto')); ?>" loading="lazy">
                            <?php else: ?>
                                <span>Sem imagem</span>
                            <?php endif; ?>
                        </a>

                        <div class="checkout-order-item__copy">
                            <strong><?= e((string) ($product['nome'] ?? 'Produto')); ?></strong>
                            <?php if (!empty($item['flavor'])): ?>
                                <small>Tamanho: <?= e((string) $item['flavor']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="checkout-order-item__metric" data-label="Preco unitario">
                        <strong><?= e(format_currency((float) ($item['unit_price'] ?? 0))); ?></strong>
                    </div>

                    <div class="checkout-order-item__metric" data-label="Qtd">
                        <strong><?= e((string) ((int) ($item['quantity'] ?? 0))); ?></strong>
                    </div>

                    <div class="checkout-order-item__metric checkout-order-item__metric--subtotal" data-label="Subtotal">
                        <strong><?= e(format_currency((float) ($item['line_total'] ?? 0))); ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cart-layout checkout-layout">
        <div class="cart-main checkout-main">
            <section class="catalog-section cart-summary checkout-card checkout-card--choices">
                <div class="checkout-flow-grid">
                        <?php if (!empty($checkout['show_payment_options'])): ?>
                        <section class="checkout-step-card">
                            <div class="checkout-step-card__media">
                                <img src="<?= e($paymentHeadingImageRenderUrl); ?>" alt="Pagamento" loading="lazy">
                            </div>

                            <form
                                method="post"
                                class="cart-summary__preferences checkout-form"
                                data-checkout-options-form
                                data-checkout-base-can-finalize="<?= !empty($checkout['base_can_finalize']) ? '1' : '0'; ?>"
                                data-checkout-online-card-ready="<?= $checkoutOnlineCardReady ? '1' : '0'; ?>"
                                data-checkout-base-pending-title="<?= e($checkoutStateTitle); ?>"
                                data-checkout-base-pending-message="<?= e($checkoutStateMessage); ?>"
                                data-checkout-base-pending-action-href="<?= e($checkoutStateActionHref); ?>"
                                data-checkout-base-pending-action-label="<?= e($checkoutStateActionLabel); ?>"
                            >
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="save_checkout_options">
                                <input type="hidden" name="fulfillment_method" value="delivery">

                                <div class="checkout-form__section">
                                    <div class="checkout-payment-scope-group checkout-payment-scope-group--online<?= ((string) ($checkout['payment_scope'] ?? '') === 'online') ? ' is-active' : ''; ?>">
                                        <label class="cart-choice checkout-payment-card checkout-payment-card--scope checkout-payment-card--online">
                                            <input
                                                type="radio"
                                                name="payment_scope"
                                                value="online"
                                                <?= checked((string) ($checkout['payment_scope'] ?? ''), 'online'); ?>
                                            >
                                            <span class="checkout-payment-card__content">
                                                <span class="checkout-payment-card__copy">
                                                    <strong>Pagar online</strong>
                                                </span>
                                                <span class="checkout-payment-card__icon-stack" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" class="checkout-payment-card__icon checkout-payment-card__icon--wallet checkout-payment-card__icon--default" focusable="false" aria-hidden="true">
                                                        <path d="M21,18V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5A2,2 0 0,1 5,3H19A2,2 0 0,1 21,5V6H12C10.89,6 10,6.9 10,8V16A2,2 0 0,0 12,18M12,16H22V8H12M16,13.5A1.5,1.5 0 0,1 14.5,12A1.5,1.5 0 0,1 16,10.5A1.5,1.5 0 0,1 17.5,12A1.5,1.5 0 0,1 16,13.5Z"></path>
                                                    </svg>
                                                    <svg viewBox="0 0 576 512" class="checkout-payment-card__icon checkout-payment-card__icon--card" focusable="false" aria-hidden="true">
                                                        <path d="M512 80c8.8 0 16 7.2 16 16v32H48V96c0-8.8 7.2-16 16-16H512zm16 144V416c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V224H528zM64 32C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64zm56 304c-13.3 0-24 10.7-24 24s10.7 24 24 24h48c13.3 0 24-10.7 24-24s-10.7-24-24-24H120zm128 0c-13.3 0-24 10.7-24 24s10.7 24 24 24H360c13.3 0 24-10.7 24-24s-10.7-24-24-24H248z"></path>
                                                    </svg>
                                                    <svg viewBox="0 0 24 24" class="checkout-payment-card__icon checkout-payment-card__icon--payment" focusable="false" aria-hidden="true">
                                                        <path d="M2,17H22V21H2V17M6.25,7H9V6H6V3H18V6H15V7H17.75L19,17H5L6.25,7M9,10H15V8H9V10M9,13H15V11H9V13Z"></path>
                                                    </svg>
                                                    <svg viewBox="0 0 24 24" class="checkout-payment-card__icon checkout-payment-card__icon--check" focusable="false" aria-hidden="true">
                                                        <path d="M9,16.17L4.83,12L3.41,13.41L9,19L21,7L19.59,5.59L9,16.17Z"></path>
                                                    </svg>
                                                </span>
                                            </span>
                                        </label>

                                    <?php if (!empty($checkout['show_online_payment_options'])): ?>
                                        <div class="cart-choice-group__options cart-choice-group__options--stacked checkout-payment-grid checkout-payment-grid--suboptions checkout-payment-grid--online-methods">
                                            <label class="cart-choice checkout-payment-card checkout-payment-card--suboption checkout-payment-card--suboption-action checkout-payment-card--suboption-online-action">
                                                <input
                                                    type="radio"
                                                    name="online_payment_method"
                                                    value="pix"
                                                    <?= checked($checkoutOnlineMethod, 'pix'); ?>
                                                >
                                                <span class="checkout-payment-card__content">
                                                    <span class="checkout-payment-card__delivery-copy">
                                                        <strong>Pix no site</strong>
                                                    </span>
                                                    <span class="checkout-payment-card__delivery-arrow" aria-hidden="true">
                                                        <svg
                                                            width="25"
                                                            height="25"
                                                            viewBox="0 0 45 38"
                                                            fill="none"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                        >
                                                            <path
                                                                d="M43.7678 20.7678C44.7441 19.7915 44.7441 18.2085 43.7678 17.2322L27.8579 1.32233C26.8816 0.34602 25.2986 0.34602 24.3223 1.32233C23.346 2.29864 23.346 3.88155 24.3223 4.85786L38.4645 19L24.3223 33.1421C23.346 34.1184 23.346 35.7014 24.3223 36.6777C25.2986 37.654 26.8816 37.654 27.8579 36.6777L43.7678 20.7678ZM0 21.5L42 21.5V16.5L0 16.5L0 21.5Z"
                                                                fill="currentColor"
                                                            ></path>
                                                        </svg>
                                                    </span>
                                                </span>
                                            </label>

                                            <label class="cart-choice checkout-payment-card checkout-payment-card--suboption checkout-payment-card--suboption-action checkout-payment-card--suboption-online-action">
                                                <input
                                                    type="radio"
                                                    name="online_payment_method"
                                                    value="online_card"
                                                    <?= checked($checkoutOnlineMethod, 'online_card'); ?>
                                                >
                                                <span class="checkout-payment-card__content">
                                                    <span class="checkout-payment-card__delivery-copy">
                                                        <strong>Cartao no site</strong>
                                                    </span>
                                                    <span class="checkout-payment-card__delivery-arrow" aria-hidden="true">
                                                        <svg
                                                            width="25"
                                                            height="25"
                                                            viewBox="0 0 45 38"
                                                            fill="none"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                        >
                                                            <path
                                                                d="M43.7678 20.7678C44.7441 19.7915 44.7441 18.2085 43.7678 17.2322L27.8579 1.32233C26.8816 0.34602 25.2986 0.34602 24.3223 1.32233C23.346 2.29864 23.346 3.88155 24.3223 4.85786L38.4645 19L24.3223 33.1421C23.346 34.1184 23.346 35.7014 24.3223 36.6777C25.2986 37.654 26.8816 37.654 27.8579 36.6777L43.7678 20.7678ZM0 21.5L42 21.5V16.5L0 16.5L0 21.5Z"
                                                                fill="currentColor"
                                                            ></path>
                                                        </svg>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>

                                            <div
                                                class="checkout-online-card-fields<?= $checkoutOnlineMethod === 'online_card' ? '' : ' checkout-online-card-fields--hidden'; ?>"
                                                data-checkout-online-card-fields
                                                <?= $checkoutOnlineMethod === 'online_card' ? '' : 'hidden'; ?>
                                            >
                                                <div class="checkout-online-card-fields__grid">
                                                    <label class="checkout-online-card-field checkout-online-card-field--full">
                                                        <span>Nome no cartao</span>
                                                        <input
                                                            type="text"
                                                            name="card_holder_name"
                                                            value="<?= e($checkoutCustomerName); ?>"
                                                            autocomplete="cc-name"
                                                            placeholder="Nome como aparece no cartao"
                                                            data-checkout-sync="card_holder_name"
                                                        >
                                                    </label>

                                                    <label class="checkout-online-card-field">
                                                        <span>CPF do titular</span>
                                                        <input
                                                            type="text"
                                                            name="card_holder_cpf"
                                                            value="<?= e($checkoutCustomerCpf); ?>"
                                                            inputmode="numeric"
                                                            autocomplete="cc-additional-name"
                                                            placeholder="000.000.000-00"
                                                            data-checkout-sync="card_holder_cpf"
                                                        >
                                                    </label>

                                                    <label class="checkout-online-card-field checkout-online-card-field--full">
                                                        <span>Numero do cartao</span>
                                                        <input
                                                            type="text"
                                                            name="card_number"
                                                            value=""
                                                            inputmode="numeric"
                                                            autocomplete="cc-number"
                                                            maxlength="23"
                                                            placeholder="0000 0000 0000 0000"
                                                            data-checkout-card-number
                                                            data-checkout-sync="card_number"
                                                        >
                                                    </label>

                                                    <label class="checkout-online-card-field">
                                                        <span>Validade</span>
                                                        <input
                                                            type="text"
                                                            name="card_expiry"
                                                            value=""
                                                            inputmode="numeric"
                                                            autocomplete="cc-exp"
                                                            maxlength="5"
                                                            placeholder="MM/AA"
                                                            data-checkout-card-expiry
                                                            data-checkout-sync="card_expiry"
                                                        >
                                                    </label>

                                                    <label class="checkout-online-card-field checkout-online-card-field--cvv">
                                                        <span>CVV</span>
                                                        <input
                                                            type="text"
                                                            name="card_cvv"
                                                            value=""
                                                            inputmode="numeric"
                                                            autocomplete="cc-csc"
                                                            maxlength="4"
                                                            placeholder="123"
                                                            data-checkout-card-cvv
                                                            data-checkout-sync="card_cvv"
                                                        >
                                                    </label>
                                                </div>

                                                <label class="checkout-billing-toggle">
                                                    <span class="checkout-billing-toggle__box">
                                                        <input
                                                            type="checkbox"
                                                            name="billing_same_as_delivery"
                                                            value="1"
                                                            checked
                                                            data-checkout-billing-same
                                                            data-checkout-sync="billing_same_as_delivery"
                                                        >
                                                        <span class="checkout-billing-toggle__mark"></span>
                                                    </span>
                                                    <span>Usar o mesmo endereco da entrega no faturamento</span>
                                                </label>

                                                <div class="checkout-billing-fields" data-checkout-billing-fields hidden>
                                                    <div class="checkout-billing-fields__grid">
                                                        <label class="checkout-online-card-field">
                                                            <span>CEP</span>
                                                            <input
                                                                type="text"
                                                                name="billing_postal_code"
                                                                value=""
                                                                inputmode="numeric"
                                                                autocomplete="postal-code"
                                                                maxlength="9"
                                                                placeholder="00000-000"
                                                                data-checkout-billing-postal-code
                                                                data-checkout-sync="billing_postal_code"
                                                            >
                                                        </label>

                                                        <label class="checkout-online-card-field checkout-online-card-field--full">
                                                            <span>Rua</span>
                                                            <input
                                                                type="text"
                                                                name="billing_street"
                                                                value=""
                                                                autocomplete="address-line1"
                                                                placeholder="Rua do faturamento"
                                                                data-checkout-sync="billing_street"
                                                            >
                                                        </label>

                                                        <label class="checkout-online-card-field">
                                                            <span>Numero</span>
                                                            <input
                                                                type="text"
                                                                name="billing_number"
                                                                value=""
                                                                autocomplete="address-line2"
                                                                placeholder="Numero"
                                                                data-checkout-sync="billing_number"
                                                            >
                                                        </label>

                                                        <label class="checkout-online-card-field">
                                                            <span>Complemento</span>
                                                            <input
                                                                type="text"
                                                                name="billing_complement"
                                                                value=""
                                                                autocomplete="address-line2"
                                                                placeholder="Apto, bloco, sala..."
                                                                data-checkout-sync="billing_complement"
                                                            >
                                                        </label>

                                                        <label class="checkout-online-card-field">
                                                            <span>Bairro</span>
                                                            <input
                                                                type="text"
                                                                name="billing_neighborhood"
                                                                value=""
                                                                autocomplete="address-level3"
                                                                placeholder="Bairro"
                                                                data-checkout-sync="billing_neighborhood"
                                                            >
                                                        </label>

                                                        <label class="checkout-online-card-field">
                                                            <span>Cidade</span>
                                                            <input
                                                                type="text"
                                                                name="billing_city"
                                                                value=""
                                                                autocomplete="address-level2"
                                                                placeholder="Cidade"
                                                                data-checkout-sync="billing_city"
                                                            >
                                                        </label>

                                                        <label class="checkout-online-card-field">
                                                            <span>Estado</span>
                                                            <input
                                                                type="text"
                                                                name="billing_state"
                                                                value=""
                                                                autocomplete="address-level1"
                                                                maxlength="2"
                                                                placeholder="UF"
                                                                data-checkout-sync="billing_state"
                                                            >
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endif; ?>

                                    </div>

                                    <div class="checkout-payment-scope-group checkout-payment-scope-group--delivery<?= ((string) ($checkout['payment_scope'] ?? '') === 'on_delivery') ? ' is-active' : ''; ?>">
                                        <label class="cart-choice checkout-payment-card checkout-payment-card--scope checkout-payment-card--delivery">
                                            <input
                                                type="radio"
                                                name="payment_scope"
                                                value="on_delivery"
                                                <?= checked((string) ($checkout['payment_scope'] ?? ''), 'on_delivery'); ?>
                                            >
                                            <span class="checkout-payment-card__content">
                                                <span class="checkout-payment-card__copy">
                                                    <strong><?= e($offlinePaymentLabel); ?></strong>
                                                </span>
                                                <span class="checkout-payment-card__icon-stack" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" class="checkout-payment-card__icon checkout-payment-card__icon--wallet checkout-payment-card__icon--default" focusable="false" aria-hidden="true">
                                                        <path d="M21,18V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5A2,2 0 0,1 5,3H19A2,2 0 0,1 21,5V6H12C10.89,6 10,6.9 10,8V16A2,2 0 0,0 12,18M12,16H22V8H12M16,13.5A1.5,1.5 0 0,1 14.5,12A1.5,1.5 0 0,1 16,10.5A1.5,1.5 0 0,1 17.5,12A1.5,1.5 0 0,1 16,13.5Z"></path>
                                                    </svg>
                                                    <svg viewBox="0 0 24 24" class="checkout-payment-card__icon checkout-payment-card__icon--payment" focusable="false" aria-hidden="true">
                                                        <path d="M2,17H22V21H2V17M6.25,7H9V6H6V3H18V6H15V7H17.75L19,17H5L6.25,7M9,10H15V8H9V10M9,13H15V11H9V13Z"></path>
                                                    </svg>
                                                    <svg viewBox="0 0 24 24" class="checkout-payment-card__icon checkout-payment-card__icon--cash" focusable="false" aria-hidden="true">
                                                        <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"></path>
                                                    </svg>
                                                    <svg viewBox="0 0 24 24" class="checkout-payment-card__icon checkout-payment-card__icon--check" focusable="false" aria-hidden="true">
                                                        <path d="M9,16.17L4.83,12L3.41,13.41L9,19L21,7L19.59,5.59L9,16.17Z"></path>
                                                    </svg>
                                                </span>
                                            </span>
                                        </label>

                                    <?php if (!empty($checkout['show_delivery_payment_options'])): ?>
                                        <div class="cart-choice-group__options cart-choice-group__options--stacked checkout-payment-grid checkout-payment-grid--suboptions checkout-payment-grid--delivery-methods">
                                            <label class="cart-choice checkout-payment-card checkout-payment-card--suboption checkout-payment-card--suboption-action checkout-payment-card--suboption-delivery">
                                                <input
                                                    type="radio"
                                                    name="delivery_payment_method"
                                                    value="card"
                                                    <?= checked((string) ($checkout['on_delivery_payment_method'] ?? 'card'), 'card'); ?>
                                                >
                                                <span class="checkout-payment-card__content">
                                                    <span class="checkout-payment-card__delivery-copy">
                                                        <strong><?= e($cardOfflineLabel); ?></strong>
                                                    </span>
                                                    <span class="checkout-payment-card__delivery-arrow" aria-hidden="true">
                                                        <svg
                                                            width="25"
                                                            height="25"
                                                            viewBox="0 0 45 38"
                                                            fill="none"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                        >
                                                            <path
                                                                d="M43.7678 20.7678C44.7441 19.7915 44.7441 18.2085 43.7678 17.2322L27.8579 1.32233C26.8816 0.34602 25.2986 0.34602 24.3223 1.32233C23.346 2.29864 23.346 3.88155 24.3223 4.85786L38.4645 19L24.3223 33.1421C23.346 34.1184 23.346 35.7014 24.3223 36.6777C25.2986 37.654 26.8816 37.654 27.8579 36.6777L43.7678 20.7678ZM0 21.5L42 21.5V16.5L0 16.5L0 21.5Z"
                                                                fill="currentColor"
                                                            ></path>
                                                        </svg>
                                                    </span>
                                                </span>
                                            </label>

                                            <label class="cart-choice checkout-payment-card checkout-payment-card--suboption checkout-payment-card--suboption-action checkout-payment-card--suboption-delivery">
                                                <input
                                                    type="radio"
                                                    name="delivery_payment_method"
                                                    value="cash"
                                                    <?= checked((string) ($checkout['on_delivery_payment_method'] ?? ''), 'cash'); ?>
                                                >
                                                <span class="checkout-payment-card__content">
                                                    <span class="checkout-payment-card__delivery-copy">
                                                        <strong><?= e($cashOfflineLabel); ?></strong>
                                                    </span>
                                                    <span class="checkout-payment-card__delivery-arrow" aria-hidden="true">
                                                        <svg
                                                            width="25"
                                                            height="25"
                                                            viewBox="0 0 45 38"
                                                            fill="none"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                        >
                                                            <path
                                                                d="M43.7678 20.7678C44.7441 19.7915 44.7441 18.2085 43.7678 17.2322L27.8579 1.32233C26.8816 0.34602 25.2986 0.34602 24.3223 1.32233C23.346 2.29864 23.346 3.88155 24.3223 4.85786L38.4645 19L24.3223 33.1421C23.346 34.1184 23.346 35.7014 24.3223 36.6777C25.2986 37.654 26.8816 37.654 27.8579 36.6777L43.7678 20.7678ZM0 21.5L42 21.5V16.5L0 16.5L0 21.5Z"
                                                                fill="currentColor"
                                                            ></path>
                                                        </svg>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($checkout['show_delivery_payment_options'])): ?>
                                        <div
                                            class="checkout-cash-field<?= !empty($checkout['cash_requires_input']) ? '' : ' checkout-cash-field--hidden'; ?>"
                                            <?= !empty($checkout['cash_requires_input']) ? '' : 'hidden'; ?>
                                            data-checkout-cash-field
                                            data-checkout-total-value="<?= e($checkoutTotalRawValue); ?>"
                                            data-checkout-total-input-value="<?= e($checkoutTotalInputValue); ?>"
                                        >
                                            <div class="checkout-cash-choice-grid">
                                                <label class="checkout-cash-choice">
                                                    <input
                                                        type="radio"
                                                        name="cash_change_choice"
                                                        value="change"
                                                        <?= checked($checkoutCashChoiceNeedsChange ? 'change' : '', 'change'); ?>
                                                        data-checkout-cash-choice
                                                    >
                                                    <span>Preciso de troco</span>
                                                </label>

                                                <label class="checkout-cash-choice">
                                                    <input
                                                        type="radio"
                                                        name="cash_change_choice"
                                                        value="exact"
                                                        <?= checked($checkoutCashChoiceNoChange ? 'exact' : '', 'exact'); ?>
                                                        data-checkout-cash-choice
                                                    >
                                                    <span>Nao preciso de troco</span>
                                                </label>
                                            </div>

                                            <div
                                                class="checkout-cash-field__amount<?= $checkoutCashChoiceNoChange ? ' checkout-cash-field__amount--hidden' : ''; ?>"
                                                <?= $checkoutCashChoiceNoChange ? 'hidden' : ''; ?>
                                                data-checkout-cash-amount
                                            >
                                                <label class="checkout-cash-field__label" for="cash-change-for">Troco para quanto?</label>
                                                <input
                                                    id="cash-change-for"
                                                    class="checkout-cash-field__input"
                                                    type="text"
                                                    name="cash_change_for"
                                                    value="<?= e((string) ($checkout['cash_change_for_formatted'] ?? '')); ?>"
                                                    inputmode="decimal"
                                                    placeholder="Ex.: 100,00"
                                                    data-checkout-cash-change-input
                                                >
                                                <?php if (!empty($checkout['cash_change_valid']) && !empty($checkout['cash_change_for'])): ?>
                                                    <?php if ((float) ($checkout['cash_change_due'] ?? 0) > 0): ?>
                                                        <small class="checkout-cash-field__helper">
                                                            <?= $isPickup
                                                                ? 'Troco previsto: ' . e((string) ($checkout['cash_change_due_formatted'] ?? '')) . '.'
                                                                : 'O entregador deve levar ' . e((string) ($checkout['cash_change_due_formatted'] ?? '')) . ' de troco.'; ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="checkout-cash-field__helper">Pagamento em dinheiro exato, sem troco.</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <small class="checkout-cash-field__helper checkout-cash-field__helper--warning">Informe um valor maior ou igual ao total do pedido.</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    </div>

                                    <input type="hidden" name="payment_method" value="<?= e((string) ($checkout['payment_method'] ?? '')); ?>">
                                </div>
                            </form>
                        </section>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <aside class="cart-sidebar checkout-sidebar">
            <section class="catalog-section cart-summary checkout-card checkout-card--summary">
                <div class="checkout-payment-coupon">
                    <div class="cart-summary__coupon-minimal checkout-payment-coupon__minimal">
                        <?php if (!empty($checkout['coupon_valid'])): ?>
                            <details class="cart-summary__coupon-manage checkout-payment-coupon__manage">
                                <summary class="cart-summary__coupon-applied cart-summary__coupon-applied--manage checkout-payment-coupon__applied">
                                    <div class="cart-summary__coupon-applied-copy">
                                        <span class="cart-summary__coupon-label">Cupom adicionado:</span>
                                        <span class="cart-summary__coupon-chip"><?= e((string) ($checkout['coupon_code'] ?? '')); ?></span>
                                    </div>
                                    <span class="cart-summary__coupon-manage-button checkout-payment-coupon__manage-button">Remover/trocar</span>
                                </summary>

                                <div class="cart-summary__coupon-saved-list checkout-payment-coupon__list">
                                    <?php $matchedCheckoutCoupon = false; ?>
                                    <?php foreach ($checkoutRedeemedCoupons as $savedCoupon): ?>
                                        <?php $isCurrentCoupon = (string) ($savedCoupon['codigo'] ?? '') === (string) ($checkout['coupon_code'] ?? ''); ?>
                                        <?php if ($isCurrentCoupon) {
                                            $matchedCheckoutCoupon = true;
                                        } ?>
                                        <form method="post" class="cart-summary__coupon-saved-item checkout-payment-coupon__item<?= $isCurrentCoupon ? ' cart-summary__coupon-saved-item--active' : ''; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <?php if ($isCurrentCoupon): ?>
                                                <input type="hidden" name="action" value="remove_coupon">
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="apply_saved_coupon">
                                                <input type="hidden" name="coupon_id" value="<?= e((string) ((int) ($savedCoupon['id'] ?? 0))); ?>">
                                            <?php endif; ?>

                                            <div class="cart-summary__coupon-saved-copy">
                                                <strong><?= e((string) ($savedCoupon['codigo'] ?? 'CUPOM')); ?></strong>
                                                <small><?= e(trim((string) ($savedCoupon['descricao'] ?? '')) !== '' ? (string) $savedCoupon['descricao'] : coupon_build_description($savedCoupon)); ?></small>
                                            </div>

                                            <?php if ($isCurrentCoupon): ?>
                                                <button class="btn btn--ghost cart-summary__coupon-saved-button cart-summary__coupon-saved-button--remove" type="submit">Remover</button>
                                            <?php else: ?>
                                                <button class="btn btn--ghost cart-summary__coupon-saved-button" type="submit">Selecionar</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endforeach; ?>

                                    <?php if (!$matchedCheckoutCoupon): ?>
                                        <form method="post" class="cart-summary__coupon-saved-item checkout-payment-coupon__item cart-summary__coupon-saved-item--active">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="remove_coupon">
                                            <div class="cart-summary__coupon-saved-copy">
                                                <strong><?= e((string) ($checkout['coupon_code'] ?? '')); ?></strong>
                                                <small>Cupom atualmente aplicado nesta compra.</small>
                                            </div>
                                            <button class="btn btn--ghost cart-summary__coupon-saved-button cart-summary__coupon-saved-button--remove" type="submit">Remover</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php elseif (!empty($checkout['coupon_error'])): ?>
                            <p class="cart-summary__coupon-feedback cart-summary__coupon-feedback--error"><?= e((string) $checkout['coupon_error']); ?></p>
                        <?php endif; ?>

                        <?php if (empty($checkout['coupon_valid'])): ?>
                            <details class="cart-summary__coupon-saved checkout-payment-coupon__saved">
                                <summary class="cart-summary__coupon-saved-toggle cart-summary__coupon-saved-toggle--add checkout-payment-coupon__toggle">
                                    <span>Adicionar cupom</span>
                                    <span class="cart-summary__coupon-saved-toggle-icon" aria-hidden="true">+</span>
                                </summary>

                                <div class="cart-summary__coupon-saved-list checkout-payment-coupon__list">
                                    <?php if ($checkoutRedeemedCoupons === []): ?>
                                        <p class="cart-summary__coupon-saved-empty">Voce nao possui cupom disponivel para esta compra agora.</p>
                                    <?php else: ?>
                                        <?php foreach ($checkoutRedeemedCoupons as $savedCoupon): ?>
                                            <form method="post" class="cart-summary__coupon-saved-item checkout-payment-coupon__item">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="apply_saved_coupon">
                                                <input type="hidden" name="coupon_id" value="<?= e((string) ((int) ($savedCoupon['id'] ?? 0))); ?>">

                                                <div class="cart-summary__coupon-saved-copy">
                                                    <strong><?= e((string) ($savedCoupon['codigo'] ?? 'CUPOM')); ?></strong>
                                                    <small><?= e(trim((string) ($savedCoupon['descricao'] ?? '')) !== '' ? (string) $savedCoupon['descricao'] : coupon_build_description($savedCoupon)); ?></small>
                                                </div>

                                                <?php if ((string) ($checkout['coupon_code'] ?? '') === (string) ($savedCoupon['codigo'] ?? '')): ?>
                                                    <span class="status-pill status-pill--success">Aplicado</span>
                                                <?php else: ?>
                                                    <button class="btn btn--ghost cart-summary__coupon-saved-button" type="submit">Selecionar</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cart-summary__rows">
                    <div class="cart-summary__row">
                        <span>Subtotal</span>
                        <strong><?= e((string) $checkout['subtotal_formatted']); ?></strong>
                    </div>
                    <?php if ((float) ($checkout['coupon_discount'] ?? 0) > 0): ?>
                        <div class="cart-summary__row cart-summary__row--discount">
                            <span>Desconto do cupom</span>
                            <strong>-<?= e((string) ($checkout['coupon_discount_formatted'] ?? '')); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php
                    $deliveryFeeBase = (float) ($checkout['delivery_fee_base'] ?? 0);
                    $shippingDiscount = (float) ($checkout['coupon_shipping_discount'] ?? 0);
                    $deliveryDiscountFullyApplied = $shippingDiscount > 0 && $shippingDiscount >= $deliveryFeeBase;
                    ?>
                    <div class="cart-summary__row<?= $deliveryDiscountFullyApplied ? ' cart-summary__row--discount' : ''; ?>">
                        <span>Taxa de entrega</span>
                        <strong>
                            <?= $deliveryDiscountFullyApplied
                                ? '-' . e((string) ($checkout['coupon_shipping_discount_formatted'] ?? format_currency($shippingDiscount)))
                                : e((string) $checkout['delivery_fee_formatted']); ?>
                        </strong>
                    </div>
                    <?php if ($shippingDiscount > 0 && !$deliveryDiscountFullyApplied): ?>
                        <div class="cart-summary__row cart-summary__row--discount">
                            <span>Desconto no frete</span>
                            <strong>-<?= e((string) ($checkout['coupon_shipping_discount_formatted'] ?? '')); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="cart-summary__row cart-summary__row--total">
                        <span><?= (string) $checkout['fulfillment_method'] === 'delivery' ? 'Total com entrega' : 'Total do pedido'; ?></span>
                        <strong><?= e((string) $checkout['total_formatted']); ?></strong>
                    </div>
                </div>

                    <div
                        class="cart-summary__actions checkout-summary__actions"
                        data-checkout-finalize-actions
                    >
                        <form method="post" id="checkout-place-order-form" data-checkout-place-order-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="place_order">
                            <input type="hidden" name="fulfillment_method" value="<?= e((string) $checkout['fulfillment_method']); ?>">
                            <input type="hidden" name="payment_scope" value="<?= e((string) ($checkout['payment_scope'] ?? '')); ?>" data-checkout-payment-scope-hidden>
                            <input type="hidden" name="delivery_payment_method" value="<?= e((string) ($checkout['on_delivery_payment_method'] ?? '')); ?>" data-checkout-delivery-method-hidden>
                            <input type="hidden" name="online_payment_method" value="<?= e((string) ($checkout['online_payment_method'] ?? '')); ?>" data-checkout-online-method-hidden>
                            <input type="hidden" name="payment_method" value="<?= e((string) $checkout['payment_method']); ?>" data-checkout-payment-method-hidden>
                            <input type="hidden" name="cash_change_choice" value="<?= e((string) ($checkout['cash_change_choice'] ?? '')); ?>" data-checkout-cash-choice-hidden>
                            <input type="hidden" name="cash_change_for" value="<?= e((string) ($checkout['cash_change_for_formatted'] ?? '')); ?>" data-checkout-cash-change-hidden>
                            <input type="hidden" name="card_holder_name" value="" data-checkout-sync-target="card_holder_name">
                            <input type="hidden" name="card_holder_cpf" value="" data-checkout-sync-target="card_holder_cpf">
                            <input type="hidden" name="card_number" value="" data-checkout-sync-target="card_number">
                            <input type="hidden" name="card_expiry" value="" data-checkout-sync-target="card_expiry">
                            <input type="hidden" name="card_cvv" value="" data-checkout-sync-target="card_cvv">
                            <input type="hidden" name="billing_same_as_delivery" value="1" data-checkout-sync-target="billing_same_as_delivery">
                            <input type="hidden" name="billing_postal_code" value="" data-checkout-sync-target="billing_postal_code">
                            <input type="hidden" name="billing_street" value="" data-checkout-sync-target="billing_street">
                            <input type="hidden" name="billing_number" value="" data-checkout-sync-target="billing_number">
                            <input type="hidden" name="billing_complement" value="" data-checkout-sync-target="billing_complement">
                            <input type="hidden" name="billing_neighborhood" value="" data-checkout-sync-target="billing_neighborhood">
                            <input type="hidden" name="billing_city" value="" data-checkout-sync-target="billing_city">
                            <input type="hidden" name="billing_state" value="" data-checkout-sync-target="billing_state">
                            <button class="btn btn--primary" type="submit" data-checkout-primary-button>Finalizar pedido</button>
                        </form>
                    </div>
                    <div class="checkout-summary__pending checkout-summary__pending--error" data-checkout-pending-state <?= empty($checkout['can_finalize']) ? '' : 'hidden'; ?>>
                        <strong data-checkout-pending-title><?= e($checkoutFinalizeTitle); ?></strong>
                        <p data-checkout-pending-message><?= e($checkoutFinalizeMessage); ?></p>

                        <a
                            class="btn btn--ghost"
                            href="<?= e($checkoutFinalizeActionHref !== '' ? $checkoutFinalizeActionHref : app_url()); ?>"
                            data-checkout-pending-action
                            <?= ($checkoutFinalizeActionHref !== '' && $checkoutFinalizeActionLabel !== '') ? '' : 'hidden'; ?>
                        ><?= e($checkoutFinalizeActionLabel !== '' ? $checkoutFinalizeActionLabel : 'Continuar'); ?></a>
                    </div>
                </section>
        </aside>
    </section>

    <div class="checkout-pix-modal" data-checkout-pix-modal hidden>
        <div class="checkout-pix-modal__backdrop" data-checkout-pix-close></div>
        <div class="checkout-pix-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="checkout-pix-modal-title" tabindex="-1">
            <button class="checkout-pix-modal__close" type="button" aria-label="Fechar" data-checkout-pix-close>&times;</button>
            <span class="checkout-pix-modal__icon">PIX</span>
            <h2 id="checkout-pix-modal-title">Gerar Pix agora?</h2>
            <p class="checkout-pix-modal__lead">Voce vai continuar dentro da Moda Tropical. Na proxima tela vamos mostrar o QR Code e o copia e cola do PagBank.</p>

            <div class="checkout-pix-modal__summary">
                <div class="checkout-pix-modal__key-card">
                    <span>Pagamento</span>
                    <strong>Pix PagBank</strong>
                    <small>Sem redirecionamento para fora da loja.</small>
                </div>

                <div class="checkout-pix-modal__total-card">
                    <span>Total</span>
                    <strong><?= e((string) ($checkout['total_formatted'] ?? '')); ?></strong>
                </div>
            </div>

            <div class="checkout-pix-modal__qr-panel" data-checkout-pix-qr-panel hidden>
                <div class="checkout-pix-modal__copy-code">
                    <span>Como funciona</span>
                    <p>Primeiro criamos o Pix no PagBank. Em seguida voce recebe o QR Code e o codigo copia e cola na tela do pedido para pagar sem sair do site.</p>
                </div>
            </div>

            <div class="checkout-pix-modal__actions">
                <div class="checkout-pix-modal__quick-actions">
                    <button
                        class="btn checkout-pix-modal__toggle-button"
                        type="button"
                        data-checkout-pix-toggle-qr
                        data-label-open="Como funciona"
                        data-label-close="Ocultar"
                        aria-expanded="false"
                    >
                        Como funciona
                    </button>
                    <button class="btn checkout-pix-modal__confirm-button" type="button" data-checkout-pix-confirm>Gerar Pix</button>
                </div>
            </div>
        </div>
    </div>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
