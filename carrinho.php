<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/storefront.php';

function cart_request_wants_json(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return posted_value('ajax') === '1'
        || $requestedWith === 'xmlhttprequest'
        || str_contains($accept, 'application/json');
}

function cart_product_count_label(int $count): string
{
    return $count . ' produto' . ($count === 1 ? '' : 's');
}

function cart_available_saved_coupons(?array $currentCustomer, array $cart, array $checkoutPreview): array
{
    if (!$currentCustomer) {
        return [];
    }

    $wallet = fetch_customer_coupon_wallet((int) ($currentCustomer['id'] ?? 0));
    $savedCoupons = array_values(array_filter(
        $wallet,
        static fn(array $coupon): bool => !empty($coupon['is_redeemed']) && !empty($coupon['is_active_now'])
    ));

    if ($savedCoupons === []) {
        return [];
    }

    $availableCoupons = [];

    foreach ($savedCoupons as $coupon) {
        $couponCode = (string) ($coupon['codigo'] ?? '');

        if ($couponCode === '') {
            continue;
        }

        $evaluation = storefront_resolve_coupon_application(
            $cart,
            (string) ($checkoutPreview['fulfillment_method'] ?? 'delivery'),
            $couponCode,
            $currentCustomer,
            $checkoutPreview['delivery_address'] ?? null
        );

        if (empty($evaluation['valid'])) {
            continue;
        }

        $coupon['cart_description'] = (string) ($evaluation['description'] ?? coupon_build_description($coupon));
        $availableCoupons[] = $coupon;
    }

    return $availableCoupons;
}

function render_cart_summary_panel(array $storeSettings, ?array $currentCustomer, array $cart): string
{
    $selectedCart = storefront_selected_cart($cart);
    $checkoutPreview = storefront_build_cart_checkout(
        $storeSettings,
        $currentCustomer,
        $selectedCart,
        null,
        null,
        null,
        storefront_coupon_session_code()
    );
    $couponCode = (string) ($checkoutPreview['coupon_code'] ?? '');
    $couponValid = !empty($checkoutPreview['coupon_valid']);
    $couponError = trim((string) ($checkoutPreview['coupon_error'] ?? ''));
    $couponDiscount = (float) ($checkoutPreview['coupon_total_discount'] ?? 0);
    $savedCoupons = $selectedCart['items'] === []
        ? []
        : cart_available_saved_coupons($currentCustomer, $selectedCart, $checkoutPreview);

    ob_start();
    ?>
    <div class="cart-summary__coupon-minimal">
        <?php if ($couponValid && $couponCode !== ''): ?>
            <details class="cart-summary__coupon-manage">
                <summary class="cart-summary__coupon-applied cart-summary__coupon-applied--manage">
                    <div class="cart-summary__coupon-applied-copy">
                        <span class="cart-summary__coupon-label">Cupom adicionado:</span>
                        <span class="cart-summary__coupon-chip"><?= e($couponCode); ?></span>
                    </div>
                    <span class="cart-summary__coupon-manage-button">Remover/trocar</span>
                </summary>

                <div class="cart-summary__coupon-saved-list">
                    <?php $matchedAppliedCoupon = false; ?>
                    <?php foreach ($savedCoupons as $savedCoupon): ?>
                        <?php $isCurrentCoupon = (string) ($savedCoupon['codigo'] ?? '') === $couponCode; ?>
                        <?php if ($isCurrentCoupon) {
                            $matchedAppliedCoupon = true;
                        } ?>
                        <form method="post" class="cart-summary__coupon-saved-item<?= $isCurrentCoupon ? ' cart-summary__coupon-saved-item--active' : ''; ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <?php if ($isCurrentCoupon): ?>
                                <input type="hidden" name="action" value="remove_coupon">
                            <?php else: ?>
                                <input type="hidden" name="action" value="apply_saved_coupon">
                                <input type="hidden" name="coupon_id" value="<?= e((string) ((int) ($savedCoupon['id'] ?? 0))); ?>">
                            <?php endif; ?>

                            <div class="cart-summary__coupon-saved-copy">
                                <strong><?= e((string) ($savedCoupon['codigo'] ?? 'CUPOM')); ?></strong>
                                <small><?= e((string) ($savedCoupon['cart_description'] ?? coupon_build_description($savedCoupon))); ?></small>
                            </div>

                            <?php if ($isCurrentCoupon): ?>
                                <button class="btn btn--ghost cart-summary__coupon-saved-button cart-summary__coupon-saved-button--remove" type="submit">Remover</button>
                            <?php else: ?>
                                <button class="btn btn--ghost cart-summary__coupon-saved-button" type="submit">Selecionar</button>
                            <?php endif; ?>
                        </form>
                    <?php endforeach; ?>

                    <?php if (!$matchedAppliedCoupon): ?>
                        <form method="post" class="cart-summary__coupon-saved-item cart-summary__coupon-saved-item--active">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="remove_coupon">
                            <div class="cart-summary__coupon-saved-copy">
                                <strong><?= e($couponCode); ?></strong>
                                <small>Cupom atualmente aplicado neste carrinho.</small>
                            </div>
                            <button class="btn btn--ghost cart-summary__coupon-saved-button cart-summary__coupon-saved-button--remove" type="submit">Remover</button>
                        </form>
                    <?php endif; ?>
                </div>
            </details>
        <?php elseif ($couponError !== ''): ?>
            <p class="cart-summary__coupon-feedback cart-summary__coupon-feedback--error"><?= e($couponError); ?></p>
        <?php endif; ?>

        <?php if ($selectedCart['items'] === []): ?>
            <p class="cart-summary__coupon-feedback">Os cupons aparecem quando voce marcar algum item.</p>
        <?php elseif ($currentCustomer && !($couponValid && $couponCode !== '')): ?>
            <details class="cart-summary__coupon-saved">
                <summary class="cart-summary__coupon-saved-toggle cart-summary__coupon-saved-toggle--add">
                    <span>Adicionar cupom</span>
                    <span class="cart-summary__coupon-saved-toggle-icon" aria-hidden="true">+</span>
                </summary>

                <div class="cart-summary__coupon-saved-list">
                    <?php if ($savedCoupons === []): ?>
                        <p class="cart-summary__coupon-saved-empty">Voce nao possui cupom disponivel para este carrinho agora.</p>
                    <?php else: ?>
                        <?php foreach ($savedCoupons as $savedCoupon): ?>
                            <form method="post" class="cart-summary__coupon-saved-item">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="apply_saved_coupon">
                                <input type="hidden" name="coupon_id" value="<?= e((string) ((int) ($savedCoupon['id'] ?? 0))); ?>">

                                <div class="cart-summary__coupon-saved-copy">
                                    <strong><?= e((string) ($savedCoupon['codigo'] ?? 'CUPOM')); ?></strong>
                                    <small><?= e((string) ($savedCoupon['cart_description'] ?? coupon_build_description($savedCoupon))); ?></small>
                                </div>

                                <?php if ((string) ($checkoutPreview['coupon_code'] ?? '') === (string) ($savedCoupon['codigo'] ?? '')): ?>
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

    <div class="cart-summary__rows">
        <?php if ($couponValid && $couponDiscount > 0): ?>
            <div class="cart-summary__row cart-summary__row--discount">
                <span>Desconto</span>
                <strong>-<?= e((string) ($checkoutPreview['coupon_total_discount_formatted'] ?? format_currency($couponDiscount))); ?></strong>
            </div>

            <div class="cart-summary__row cart-summary__row--total">
                <span>Total</span>
                <strong><?= e((string) ($checkoutPreview['total_formatted'] ?? format_currency((float) ($selectedCart['subtotal'] ?? 0)))); ?></strong>
            </div>
        <?php else: ?>
            <div class="cart-summary__row cart-summary__row--total">
                <span>Total</span>
                <strong><?= e(format_currency((float) ($selectedCart['subtotal'] ?? 0))); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <div class="cart-summary__actions">
        <?php if ($selectedCart['items'] === []): ?>
            <button class="btn btn--primary" type="button" disabled>Nenhum item selecionado</button>
        <?php else: ?>
            <a class="btn btn--primary" href="<?= e(app_url('finalizar-pedido.php')); ?>">Continuar pedido</a>
        <?php endif; ?>
    </div>
    <?php

    return trim((string) ob_get_clean());
}

function render_cart_overlay_panel(array $cart): string
{
    $count = (int) ($cart['count'] ?? 0);
    $checkoutUrl = app_url('finalizar-pedido.php');

    ob_start();
    ?>
    <div class="cart-overlay-panel">
        <div class="cart-overlay-panel__top">
            <div>
                <span class="catalog-section__eyebrow">seu carrinho</span>
                <h2 id="cart-overlay-title">Escolha os itens e siga para a entrega.</h2>
            </div>

            <div class="cart-overlay-panel__header-actions">
                <span class="cart-overlay-panel__count"><?= e(strtoupper((string) $count . ' item(ns)')); ?></span>
                <button class="cart-overlay-panel__close" type="button" aria-label="Fechar carrinho" data-cart-overlay-close>&#10005;</button>
            </div>
        </div>

        <?php if (($cart['items'] ?? []) === []): ?>
            <div class="cart-overlay-panel__empty">
                <strong>Seu carrinho esta vazio.</strong>
                <p>Adicione produtos antes de seguir para o fechamento do pedido.</p>
            </div>
        <?php else: ?>
            <div class="cart-overlay-list">
                <?php foreach (($cart['items'] ?? []) as $item): ?>
                    <?php $product = $item['product'] ?? []; ?>
                    <?php $productUrl = storefront_product_url((string) ($product['slug'] ?? '')); ?>
                    <article
                        class="cart-overlay-item"
                        data-cart-overlay-item
                        data-item-key="<?= e((string) ($item['key'] ?? '')); ?>"
                        data-max-quantity="<?= e((string) ($item['max_quantity'] ?? 1)); ?>"
                    >
                        <a class="cart-overlay-item__media" href="<?= e($productUrl); ?>">
                            <?php if (!empty($product['imagem'])): ?>
                                <img src="<?= e(app_url((string) $product['imagem'])); ?>" alt="<?= e((string) ($product['nome'] ?? 'Produto')); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="cart-overlay-item__placeholder">Sem imagem</div>
                            <?php endif; ?>
                        </a>

                        <div class="cart-overlay-item__content">
                            <div class="cart-overlay-item__copy">
                                <strong><?= e((string) ($product['nome'] ?? 'Produto')); ?></strong>
                                <p><?= e((string) ($item['quantity'] ?? 0)); ?>x <?= e(format_currency((float) ($item['unit_price'] ?? 0))); ?></p>
                                <?php if (!empty($item['unit_original_price']) && (float) $item['unit_original_price'] > (float) ($item['unit_price'] ?? 0)): ?>
                                    <small>De <?= e(format_currency((float) $item['unit_original_price'])); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($item['flavor'])): ?>
                                    <small>Tamanho: <?= e((string) $item['flavor']); ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="cart-overlay-item__side">
                                <strong class="cart-overlay-item__price"><?= e(format_currency((float) ($item['line_total'] ?? 0))); ?></strong>

                                <div class="cart-overlay-item__controls">
                                    <button
                                        class="cart-overlay-item__button cart-overlay-item__button--minus"
                                        type="button"
                                        aria-label="Diminuir quantidade"
                                        data-cart-overlay-step="-1"
                                    >
                                        -
                                    </button>
                                    <span class="cart-overlay-item__quantity" data-cart-overlay-quantity><?= e((string) ($item['quantity'] ?? 0)); ?></span>
                                    <button
                                        class="cart-overlay-item__button cart-overlay-item__button--plus"
                                        type="button"
                                        aria-label="Aumentar quantidade"
                                        data-cart-overlay-step="1"
                                    >
                                        +
                                    </button>
                                    <button
                                        class="cart-overlay-item__button cart-overlay-item__button--remove"
                                        type="button"
                                        aria-label="Remover item"
                                        data-cart-overlay-remove
                                    >
                                        &#10005;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="cart-overlay-summary">
                <div class="cart-overlay-summary__row">
                    <span>Subtotal dos produtos</span>
                    <strong><?= e(format_currency((float) ($cart['subtotal'] ?? 0))); ?></strong>
                </div>
                <div class="cart-overlay-summary__row">
                    <span>Frete</span>
                    <strong>Calcular</strong>
                </div>
            </div>

            <div class="cart-overlay-panel__actions">
                <a class="btn btn--primary cart-overlay-panel__checkout" href="<?= e($checkoutUrl); ?>">Prosseguir com a compra</a>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return trim((string) ob_get_clean());
}

function cart_response_payload(): array
{
    $storeSettings = fetch_store_settings();
    $currentCustomer = current_customer();
    $cart = storefront_build_cart();
    $selectedCart = storefront_selected_cart($cart);

    return [
        'count' => (int) $cart['count'],
        'count_label' => cart_product_count_label((int) $cart['count']),
        'empty' => $cart['items'] === [],
        'subtotal_formatted' => format_currency((float) ($selectedCart['subtotal'] ?? 0)),
        'delivery_fee_formatted' => format_currency(0),
        'total_formatted' => format_currency((float) ($selectedCart['subtotal'] ?? 0)),
        'checkout_url' => '',
        'summary_html' => render_cart_summary_panel($storeSettings, $currentCustomer, $cart),
        'overlay_html' => render_cart_overlay_panel($cart),
        'selected_empty' => $selectedCart['items'] === [],
        'all_selected' => !empty($cart['all_selected']),
        'items' => array_map(
            static function (array $item): array {
                return [
                    'key' => (string) $item['key'],
                    'quantity' => (int) $item['quantity'],
                    'max_quantity' => (int) $item['max_quantity'],
                    'selected' => !empty($item['selected']),
                    'product_name' => (string) (($item['product']['nome'] ?? 'Produto')),
                    'product_price_formatted' => format_currency((float) (($item['unit_price'] ?? 0))),
                    'product_url' => app_url('produto.php') . '?slug=' . rawurlencode((string) (($item['product']['slug'] ?? ''))),
                    'flavor' => (string) ($item['flavor'] ?? ''),
                ];
            },
            $cart['items']
        ),
    ];
}

function cart_respond_json(int $statusCode, bool $success, string $message, array $extra = []): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'cart' => cart_response_payload(),
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function cart_require_customer_access(bool $expectsJson): void
{
    if (storefront_customer_can_use_cart(current_customer())) {
        return;
    }

    $message = 'Entre ou crie sua conta para usar o carrinho.';

    if ($expectsJson) {
        cart_respond_json(401, false, $message, [
            'login_url' => app_url('entrar.php'),
        ]);
    }

    set_flash('error', $message);
    redirect('entrar.php');
}

if (is_post()) {
    $expectsJson = cart_request_wants_json();
    cart_require_customer_access($expectsJson);

    if (!verify_csrf_token(posted_value('csrf_token'))) {
        if ($expectsJson) {
            cart_respond_json(419, false, 'Sessao expirada. Tente novamente.');
        }

        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('carrinho.php');
    }

    $action = trim((string) posted_value('action'));
    $redirectTo = trim((string) posted_value('redirect_to', 'carrinho.php'));
    $redirectTo = preg_match('/^https?:\/\//i', $redirectTo) === 1 ? 'carrinho.php' : ltrim($redirectTo, '/');
    $success = true;
    $message = 'Carrinho atualizado.';
    $statusCode = 200;

    if ($action === 'add') {
        $productId = (int) posted_value('product_id');
        $products = storefront_fetch_products_by_ids([$productId]);
        $product = $products[$productId] ?? null;

        if ($product === null) {
            if ($expectsJson) {
                cart_respond_json(404, false, 'Produto nao encontrado ou indisponivel.');
            }

            set_flash('error', 'Produto nao encontrado ou indisponivel.');
            redirect($redirectTo !== '' ? $redirectTo : 'carrinho.php');
        }

        $result = storefront_cart_add_item(
            $product,
            trim((string) posted_value('flavor')),
            max(1, (int) posted_value('quantity', 1))
        );

        $success = (bool) ($result['success'] ?? false);
        $message = (string) ($result['message'] ?? 'Nao foi possivel atualizar o carrinho.');

        if ($expectsJson) {
            $statusCode = ($result['reason'] ?? '') === 'auth_required' ? 401 : ($success ? 200 : 422);
            $extra = [];

            if (($result['reason'] ?? '') === 'auth_required' && !empty($result['login_url'])) {
                $extra['login_url'] = (string) $result['login_url'];
            }

            cart_respond_json($statusCode, $success, $message, $extra);
        }

        set_flash($success ? 'success' : 'error', $message);
        redirect($redirectTo !== '' ? $redirectTo : 'carrinho.php');
    }

    if ($action === 'update') {
        storefront_cart_update_item((string) posted_value('item_key'), (int) posted_value('quantity', 1));
        $message = (int) posted_value('quantity', 1) <= 0
            ? 'Item removido do carrinho.'
            : 'Carrinho atualizado.';

        if ($expectsJson) {
            cart_respond_json(200, true, $message);
        }

        set_flash('success', $message);
        redirect('carrinho.php');
    }

    if ($action === 'remove') {
        storefront_cart_remove_item((string) posted_value('item_key'));
        if ($expectsJson) {
            cart_respond_json(200, true, 'Item removido do carrinho.');
        }

        set_flash('success', 'Item removido do carrinho.');
        redirect('carrinho.php');
    }

    if ($action === 'toggle_selection') {
        $selected = in_array(strtolower((string) posted_value('selected', '0')), ['1', 'true', 'on', 'yes'], true);
        storefront_cart_set_item_selected(
            (string) posted_value('item_key'),
            $selected
        );

        if ($expectsJson) {
            cart_respond_json(200, true, 'Selecao atualizada.');
        }

        redirect('carrinho.php');
    }

    if ($action === 'toggle_all_selection') {
        $selected = in_array(strtolower((string) posted_value('selected', '0')), ['1', 'true', 'on', 'yes'], true);
        storefront_cart_select_all_items($selected);

        if ($expectsJson) {
            cart_respond_json(200, true, 'Selecao atualizada.');
        }

        redirect('carrinho.php');
    }

    if ($action === 'clear') {
        storefront_cart_clear();
        if ($expectsJson) {
            cart_respond_json(200, true, 'Carrinho limpo.');
        }

        set_flash('success', 'Carrinho limpo.');
        redirect('carrinho.php');
    }

    if ($action === 'apply_coupon') {
        $couponCode = (string) posted_value('coupon_code');
        storefront_save_coupon_code($couponCode);

        $cart = storefront_selected_cart(storefront_build_cart());
        $storeSettings = fetch_store_settings();
        $currentCustomer = current_customer();
        $checkoutPreview = storefront_build_cart_checkout(
            $storeSettings,
            $currentCustomer,
            $cart,
            null,
            null,
            null,
            storefront_coupon_session_code()
        );

        if (!empty($checkoutPreview['coupon_valid'])) {
            set_flash('success', 'Cupom aplicado no carrinho.');
        } else {
            set_flash('error', (string) ($checkoutPreview['coupon_error'] ?? 'Nao foi possivel aplicar este cupom.'));
        }

        redirect('carrinho.php');
    }

    if ($action === 'apply_saved_coupon') {
        $couponId = (int) posted_value('coupon_id');
        $currentCustomer = current_customer();
        $coupon = $currentCustomer ? find_customer_coupon_wallet_entry((int) ($currentCustomer['id'] ?? 0), $couponId) : null;

        if (!$coupon || empty($coupon['is_redeemed'])) {
            storefront_clear_coupon_code();
            set_flash('error', 'Resgate esse cupom antes de usar.');
            redirect('carrinho.php');
        }

        if (empty($coupon['is_active_now'])) {
            storefront_clear_coupon_code();
            set_flash('error', 'Esse cupom nao esta disponivel no momento.');
            redirect('carrinho.php');
        }

        $cart = storefront_selected_cart(storefront_build_cart());
        $storeSettings = fetch_store_settings();
        $checkoutPreview = storefront_build_cart_checkout(
            $storeSettings,
            $currentCustomer,
            $cart,
            null,
            null,
            null,
            (string) ($coupon['codigo'] ?? '')
        );

        if (!empty($checkoutPreview['coupon_valid'])) {
            storefront_save_coupon_code((string) ($coupon['codigo'] ?? ''));
            set_flash('success', 'Cupom aplicado no carrinho.');
        } else {
            storefront_clear_coupon_code();
            set_flash('error', (string) ($checkoutPreview['coupon_error'] ?? 'Nao foi possivel aplicar esse cupom.'));
        }

        redirect('carrinho.php');
    }

    if ($action === 'remove_coupon') {
        storefront_clear_coupon_code();
        set_flash('success', 'Cupom removido do carrinho.');
        redirect('carrinho.php');
    }

    if ($expectsJson) {
        cart_respond_json(400, false, 'Acao invalida.');
    }
}

if (!is_post() && cart_request_wants_json()) {
    cart_require_customer_access(true);
    cart_respond_json(200, true, 'Carrinho carregado.');
}

cart_require_customer_access(false);

extract(storefront_build_context(), EXTR_SKIP);

$pageTitle = 'Carrinho | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body cart-page-active';
$cart = storefront_build_cart();
$cartItems = $cart['items'];

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront cart-page" data-cart-page data-cart-csrf="<?= e(csrf_token()); ?>">
    <section class="storefront-page-heading storefront-page-heading--cart">
        <div class="storefront-page-heading__content">
            <div class="storefront-page-heading__actions">
                <a class="cart-heading-link" href="<?= e(app_url()); ?>" style="--clr: #c86f67">
                    <span class="cart-heading-link__icon-wrapper" aria-hidden="true">
                        <svg
                            viewBox="0 0 14 15"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            class="cart-heading-link__icon-svg"
                            width="10"
                        >
                            <path
                                d="M13.376 11.552l-.264-10.44-10.44-.24.024 2.28 6.96-.048L.2 12.56l1.488 1.488 9.432-9.432-.048 6.912 2.304.024z"
                                fill="currentColor"
                            ></path>
                        </svg>
                        <svg
                            viewBox="0 0 14 15"
                            fill="none"
                            width="10"
                            xmlns="http://www.w3.org/2000/svg"
                            class="cart-heading-link__icon-svg cart-heading-link__icon-svg--copy"
                        >
                            <path
                                d="M13.376 11.552l-.264-10.44-10.44-.24.024 2.28 6.96-.048L.2 12.56l1.488 1.488 9.432-9.432-.048 6.912 2.304.024z"
                                fill="currentColor"
                            ></path>
                        </svg>
                    </span>
                    Continuar comprando
                </a>
            </div>
            <h1>Meu carrinho</h1>
        </div>
    </section>

        <section class="empty-state empty-state--cart" data-cart-empty <?= $cartItems === [] ? '' : 'hidden'; ?>>
            <strong>Seu carrinho esta vazio.</strong>
            <p>Adicione produtos da vitrine para montar o pedido e finalizar depois.</p>
            <a class="btn btn--primary" href="<?= e(app_url()); ?>">Voltar para a vitrine</a>
        </section>

        <section class="cart-layout" data-cart-layout <?= $cartItems !== [] ? '' : 'hidden'; ?>>
            <div class="cart-main">
                <section class="catalog-section cart-panel">
                    <div class="catalog-section__header cart-panel__header">
                        <div class="cart-panel__heading-group">
                            <form method="post" class="cart-panel__select-all-form" data-cart-select-all-form>
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="toggle_all_selection">
                                <label class="cart-panel__select-all">
                                    <input
                                        type="checkbox"
                                        name="selected"
                                        value="1"
                                        data-cart-select-all-input
                                        <?= !empty($cart['all_selected']) ? 'checked' : ''; ?>
                                    >
                                    <span></span>
                                    <strong>Marcar tudo</strong>
                                </label>
                            </form>

                            <h2 data-cart-count-heading><?= e(cart_product_count_label((int) $cart['count'])); ?></h2>
                        </div>

                        <div class="cart-panel__header-actions">
                            <form
                                method="post"
                                class="cart-panel__clear"
                                data-cart-clear-form
                                data-confirm-title="Limpar carrinho?"
                                data-confirm-message=""
                                data-confirm-accept-label="Limpar tudo"
                            >
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="clear">
                                <button class="btn btn--ghost" type="submit">Limpar carrinho</button>
                            </form>
                        </div>
                    </div>

                    <div class="cart-list" data-cart-list>
                        <?php foreach ($cartItems as $item): ?>
                            <?php $product = $item['product']; ?>
                            <article class="cart-item<?= !empty($item['selected']) ? '' : ' is-unselected'; ?>" data-cart-item data-item-key="<?= e((string) $item['key']); ?>">
                                <form method="post" class="cart-item__select-form" data-cart-select-form>
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="toggle_selection">
                                    <input type="hidden" name="item_key" value="<?= e((string) $item['key']); ?>">
                                    <label class="cart-item__select">
                                        <input
                                            type="checkbox"
                                            name="selected"
                                            value="1"
                                            data-cart-select-input
                                            <?= !empty($item['selected']) ? 'checked' : ''; ?>
                                        >
                                        <span></span>
                                    </label>
                                </form>

                                <a class="cart-item__media" href="<?= e(storefront_product_url((string) ($product['slug'] ?? ''))); ?>">
                                    <?php if (!empty($product['imagem'])): ?>
                                        <img src="<?= e(app_url($product['imagem'])); ?>" alt="<?= e($product['nome']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="cart-item__placeholder">sem imagem</div>
                                    <?php endif; ?>
                                </a>

                                <div class="cart-item__body">
                                    <div class="cart-item__info">
                                        <span class="cart-item__brand"><?= e(storefront_brand_display_name($product)); ?></span>
                                        <h3><a href="<?= e(storefront_product_url((string) ($product['slug'] ?? ''))); ?>"><?= e($product['nome']); ?></a></h3>
                                        <?php if (!empty($item['flavor'])): ?>
                                            <p class="cart-item__flavor">Tamanho: <?= e((string) $item['flavor']); ?></p>
                                        <?php endif; ?>
                                        <p class="cart-item__unit">Unitario: <?= e(format_currency((float) ($item['unit_price'] ?? 0))); ?></p>
                                        <?php if (!empty($item['unit_original_price']) && (float) $item['unit_original_price'] > (float) ($item['unit_price'] ?? 0)): ?>
                                            <p class="cart-item__unit cart-item__unit--old">De <?= e(format_currency((float) $item['unit_original_price'])); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="cart-item__controls">
                                        <form method="post" class="cart-item__quantity-form" data-cart-quantity-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="item_key" value="<?= e((string) $item['key']); ?>">
                                            <div class="cart-item__quantity-field">
                                                <span class="cart-item__quantity-label">Quantidade</span>
                                                <div class="cart-item__quantity-stepper">
                                                    <button
                                                        class="cart-item__quantity-button cart-item__quantity-button--minus"
                                                        type="button"
                                                        data-cart-quantity-step="-1"
                                                        aria-label="Diminuir quantidade"
                                                    >
                                                        -
                                                    </button>
                                                    <input
                                                        type="number"
                                                        name="quantity"
                                                        min="1"
                                                        max="<?= e((string) $item['max_quantity']); ?>"
                                                        value="<?= e((string) $item['quantity']); ?>"
                                                        data-cart-quantity-input
                                                    >
                                                    <button
                                                        class="cart-item__quantity-button cart-item__quantity-button--plus"
                                                        type="button"
                                                        data-cart-quantity-step="1"
                                                        aria-label="Aumentar quantidade"
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                            </div>
                                        </form>

                                        <form method="post" data-cart-remove-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="item_key" value="<?= e((string) $item['key']); ?>">
                                            <button class="btn btn--mini btn--outline" type="submit">Remover</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <aside class="cart-sidebar">
                <section class="catalog-section cart-summary" data-cart-summary-panel>
                    <?= render_cart_summary_panel($storeSettings, $currentCustomer, $cart); ?>
                </section>
            </aside>
        </section>

        <div class="cart-confirm-modal" data-cart-confirm-modal hidden>
            <div class="cart-confirm-modal__backdrop" data-cart-confirm-close></div>
            <div
                class="cart-confirm-modal__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cart-confirm-title"
                aria-describedby="cart-confirm-message"
                tabindex="-1"
            >
                <button class="cart-confirm-modal__dismiss" type="button" aria-label="Fechar" data-cart-confirm-close>
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="cart-confirm-modal__icon" aria-hidden="true">!</div>
                <span class="cart-confirm-modal__eyebrow">Confirmar acao</span>
                <h3 id="cart-confirm-title" data-cart-confirm-title>Limpar carrinho?</h3>
                <p id="cart-confirm-message" data-cart-confirm-message></p>
                <div class="cart-confirm-modal__actions">
                    <button class="btn btn--ghost" type="button" data-cart-confirm-cancel>Cancelar</button>
                    <button class="btn btn--primary cart-confirm-modal__accept" type="button" data-cart-confirm-accept>Sim, limpar</button>
                </div>
            </div>
        </div>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
