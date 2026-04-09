<?php
declare(strict_types=1);

function order_normalize_tracking_code(?string $value): string
{
    $normalized = strtoupper(trim((string) $value));

    return preg_replace('/[^A-Z0-9-]+/', '', $normalized) ?? '';
}

function order_tracking_code_exists(string $trackingCode): bool
{
    $statement = db()->prepare('SELECT COUNT(*) FROM pedidos WHERE codigo_rastreio = :codigo');
    $statement->execute(['codigo' => order_normalize_tracking_code($trackingCode)]);

    return (int) $statement->fetchColumn() > 0;
}

function order_generate_tracking_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $suffix = '';

    for ($index = 0; $index < 4; $index++) {
        $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return 'MT-' . date('dmy') . '-' . $suffix;
}

function order_generate_unique_tracking_code(): string
{
    do {
        $code = order_generate_tracking_code();
    } while (order_tracking_code_exists($code));

    return $code;
}

function order_status_definitions(): array
{
    return [
        'pending' => [
            'label' => 'Aguardando aprovacao',
            'admin_label' => 'Novo pedido',
            'badge' => 'status-pill--accent',
        ],
        'approved' => [
            'label' => 'Pedido aceito',
            'admin_label' => 'Aceito',
            'badge' => 'status-pill--warning',
        ],
        'ready_pickup' => [
            'label' => 'Pronto para retirada',
            'admin_label' => 'Pronto para retirada',
            'badge' => 'status-pill--success',
        ],
        'out_for_delivery' => [
            'label' => 'Saiu para entrega',
            'admin_label' => 'Saiu para entrega',
            'badge' => 'status-pill--accent',
        ],
        'completed' => [
            'label' => 'Pedido concluido',
            'admin_label' => 'Concluido',
            'badge' => 'status-pill--success',
        ],
        'cancelled' => [
            'label' => 'Pedido cancelado',
            'admin_label' => 'Cancelado',
            'badge' => 'status-pill--danger',
        ],
        'rejected' => [
            'label' => 'Pedido recusado',
            'admin_label' => 'Recusado',
            'badge' => 'status-pill--danger',
        ],
    ];
}

function order_normalize_status(?string $value): string
{
    $status = strtolower(trim((string) $value));
    $definitions = order_status_definitions();

    return array_key_exists($status, $definitions)
        ? $status
        : 'pending';
}

function order_status_label(?string $value): string
{
    $status = order_normalize_status($value);
    $definitions = order_status_definitions();

    return $definitions[$status]['label'] ?? $definitions['pending']['label'];
}

function order_status_admin_label(?string $value): string
{
    $status = order_normalize_status($value);
    $definitions = order_status_definitions();

    return $definitions[$status]['admin_label'] ?? $definitions['pending']['admin_label'];
}

function order_status_badge_class(?string $value): string
{
    $status = order_normalize_status($value);
    $definitions = order_status_definitions();

    return $definitions[$status]['badge'] ?? 'status-pill--neutral';
}

function order_payment_status_definitions(): array
{
    return [
        'none' => [
            'label' => 'Pagamento offline',
            'badge' => 'status-pill--neutral',
        ],
        'waiting' => [
            'label' => 'Aguardando pagamento online',
            'badge' => 'status-pill--warning',
        ],
        'authorized' => [
            'label' => 'Pagamento autorizado',
            'badge' => 'status-pill--accent',
        ],
        'paid' => [
            'label' => 'Pagamento online pago',
            'badge' => 'status-pill--success',
        ],
        'declined' => [
            'label' => 'Pagamento online recusado',
            'badge' => 'status-pill--danger',
        ],
        'cancelled' => [
            'label' => 'Pagamento online cancelado',
            'badge' => 'status-pill--danger',
        ],
    ];
}

function order_normalize_payment_status(?string $value): string
{
    $status = strtolower(trim((string) $value));
    $definitions = order_payment_status_definitions();

    return array_key_exists($status, $definitions)
        ? $status
        : 'none';
}

function order_payment_status_label(?string $value): string
{
    $status = order_normalize_payment_status($value);
    $definitions = order_payment_status_definitions();

    return $definitions[$status]['label'] ?? $definitions['none']['label'];
}

function order_payment_status_badge_class(?string $value): string
{
    $status = order_normalize_payment_status($value);
    $definitions = order_payment_status_definitions();

    return $definitions[$status]['badge'] ?? 'status-pill--neutral';
}

function order_uses_online_payment(?string $fulfillmentMethod, ?string $paymentMethod, ?string $paymentProvider = null): bool
{
    return in_array(storefront_normalize_checkout_payment($paymentMethod), ['pix', 'online_card'], true);
}

function order_requires_paid_online_payment(array $order): bool
{
    return order_uses_online_payment(
        (string) ($order['fulfillment_method'] ?? 'delivery'),
        (string) ($order['payment_method'] ?? ''),
        (string) ($order['payment_provider'] ?? '')
    );
}

function order_requires_paid_pix(array $order): bool
{
    return order_requires_paid_online_payment($order);
}

function order_payment_is_paid(array $order): bool
{
    return order_normalize_payment_status((string) ($order['payment_status'] ?? 'none')) === 'paid';
}

function order_fulfillment_label(?string $value): string
{
    return storefront_normalize_checkout_method($value) === 'pickup'
        ? 'Buscar na loja'
        : 'Receber em casa';
}

function order_payment_label(?string $value, ?string $fulfillmentMethod = null, ?string $paymentProvider = null): string
{
    return storefront_checkout_payment_display_label((string) $value, $fulfillmentMethod);
}

function order_cash_change_for_amount(array $order): ?float
{
    if (storefront_normalize_checkout_payment((string) ($order['payment_method'] ?? '')) !== 'cash') {
        return null;
    }

    if (!isset($order['cash_change_for']) || $order['cash_change_for'] === null || $order['cash_change_for'] === '') {
        return null;
    }

    return (float) $order['cash_change_for'];
}

function order_cash_change_due_amount(array $order): ?float
{
    $cashChangeFor = order_cash_change_for_amount($order);

    if ($cashChangeFor === null) {
        return null;
    }

    if (isset($order['cash_change_due']) && $order['cash_change_due'] !== null && $order['cash_change_due'] !== '') {
        return max(0, (float) $order['cash_change_due']);
    }

    return max(0, $cashChangeFor - (float) ($order['total'] ?? 0));
}

function order_has_cash_change_details(array $order): bool
{
    return storefront_normalize_checkout_payment((string) ($order['payment_method'] ?? '')) === 'cash';
}

function order_address_snapshot(array $storeSettings, string $fulfillmentMethod, ?array $deliveryAddress = null): array
{
    if (storefront_normalize_checkout_method($fulfillmentMethod) === 'pickup') {
        return [
            'cep' => null,
            'rua' => null,
            'bairro' => null,
            'numero' => null,
            'complemento' => null,
            'cidade' => null,
            'uf' => null,
            'endereco_snapshot' => trim((string) ($storeSettings['endereco'] ?? '')) !== ''
                ? 'Retirada na loja | ' . trim((string) $storeSettings['endereco'])
                : 'Retirada na loja',
        ];
    }

    if (!$deliveryAddress) {
        return [
            'cep' => null,
            'rua' => null,
            'bairro' => null,
            'numero' => null,
            'complemento' => null,
            'cidade' => null,
            'uf' => null,
            'endereco_snapshot' => '',
        ];
    }

    return [
        'cep' => normalize_cep((string) ($deliveryAddress['cep'] ?? '')),
        'rua' => trim((string) ($deliveryAddress['rua'] ?? '')),
        'bairro' => trim((string) ($deliveryAddress['bairro'] ?? '')),
        'numero' => trim((string) ($deliveryAddress['numero'] ?? '')),
        'complemento' => trim((string) ($deliveryAddress['complemento'] ?? '')),
        'cidade' => trim((string) ($deliveryAddress['cidade'] ?? '')),
        'uf' => strtoupper(trim((string) ($deliveryAddress['uf'] ?? ''))),
        'endereco_snapshot' => build_customer_address_string(
            (string) ($deliveryAddress['rua'] ?? ''),
            (string) ($deliveryAddress['bairro'] ?? ''),
            (string) ($deliveryAddress['numero'] ?? ''),
            (string) ($deliveryAddress['complemento'] ?? ''),
            (string) ($deliveryAddress['cidade'] ?? ''),
            (string) ($deliveryAddress['uf'] ?? '')
        ),
    ];
}

function order_history_copy(string $status, string $fulfillmentMethod): array
{
    $normalizedStatus = order_normalize_status($status);
    $pickup = storefront_normalize_checkout_method($fulfillmentMethod) === 'pickup';

    return match ($normalizedStatus) {
        'approved' => [
            'titulo' => 'Pedido aceito',
            'descricao' => 'A loja aprovou o pedido e iniciou a separacao.',
        ],
        'ready_pickup' => [
            'titulo' => 'Pronto para retirada',
            'descricao' => 'Seu pedido foi separado e esta pronto para retirada na loja.',
        ],
        'out_for_delivery' => [
            'titulo' => 'Saiu para entrega',
            'descricao' => 'O pedido saiu para entrega e esta a caminho do endereco cadastrado.',
        ],
        'completed' => [
            'titulo' => 'Pedido concluido',
            'descricao' => $pickup
                ? 'A retirada do pedido foi confirmada.'
                : 'A entrega do pedido foi concluida com sucesso.',
        ],
        'cancelled' => [
            'titulo' => 'Pedido cancelado',
            'descricao' => 'A loja cancelou o pedido e o estoque reservado foi devolvido.',
        ],
        'rejected' => [
            'titulo' => 'Pedido recusado',
            'descricao' => 'A loja recusou o pedido e o estoque reservado foi devolvido.',
        ],
        default => [
            'titulo' => 'Pedido recebido',
            'descricao' => 'Recebemos seu pedido e ele aguarda aprovacao da loja.',
        ],
    };
}

function order_insert_history(int $orderId, string $status, string $fulfillmentMethod, ?int $adminId = null): void
{
    $copy = order_history_copy($status, $fulfillmentMethod);
    $statement = db()->prepare(
        'INSERT INTO pedido_historico (
            pedido_id, status, titulo, descricao, admin_id
         ) VALUES (
            :pedido_id, :status, :titulo, :descricao, :admin_id
         )'
    );
    $statement->execute([
        'pedido_id' => $orderId,
        'status' => order_normalize_status($status),
        'titulo' => $copy['titulo'],
        'descricao' => $copy['descricao'],
        'admin_id' => $adminId,
    ]);
}

function order_notify_customer_status(array $order, string $status): void
{
    $customerId = (int) ($order['cliente_id'] ?? 0);

    if ($customerId <= 0) {
        return;
    }

    try {
        if (!table_exists('cliente_notificacoes')) {
            return;
        }

        $copy = order_history_copy($status, (string) ($order['fulfillment_method'] ?? 'delivery'));
        create_customer_notification(
            $customerId,
            'pedido',
            $copy['titulo'],
            $copy['descricao'],
            'rastreio.php?codigo=' . rawurlencode((string) ($order['codigo_rastreio'] ?? '')),
            [
                'pedido_id' => (int) ($order['id'] ?? 0),
                'codigo_rastreio' => (string) ($order['codigo_rastreio'] ?? ''),
                'status' => order_normalize_status($status),
            ]
        );
    } catch (Throwable $exception) {
        error_log('[orders] Falha ao notificar cliente do pedido #' . (int) ($order['id'] ?? 0) . ': ' . $exception->getMessage());
    }
}

function order_insert_custom_history(
    int $orderId,
    string $status,
    string $title,
    string $description,
    ?int $adminId = null
): void {
    $statement = db()->prepare(
        'INSERT INTO pedido_historico (
            pedido_id, status, titulo, descricao, admin_id
         ) VALUES (
            :pedido_id, :status, :titulo, :descricao, :admin_id
         )'
    );
    $statement->execute([
        'pedido_id' => $orderId,
        'status' => order_normalize_status($status),
        'titulo' => trim($title) !== '' ? trim($title) : 'Atualizacao do pedido',
        'descricao' => trim($description) !== '' ? trim($description) : null,
        'admin_id' => $adminId,
    ]);
}

function order_adjust_stock_for_item(int $productId, ?string $flavorName, int $delta): void
{
    $productStatement = db()->prepare(
        'SELECT id, estoque
         FROM produtos
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $productStatement->execute(['id' => $productId]);
    $product = $productStatement->fetch();

    if (!$product) {
        throw new RuntimeException('Produto do pedido nao foi encontrado.');
    }

    $currentStock = max(0, (int) ($product['estoque'] ?? 0));
    $nextStock = $currentStock + $delta;

    if ($nextStock < 0) {
        throw new RuntimeException('Estoque insuficiente para concluir o pedido.');
    }

    $normalizedFlavor = storefront_normalize_flavor($flavorName);

    if ($normalizedFlavor !== '') {
        $flavorStatement = db()->prepare(
            'SELECT ps.sabor_id, ps.estoque, s.nome
             FROM produto_sabores ps
             INNER JOIN sabores s ON s.id = ps.sabor_id
             WHERE ps.produto_id = :produto_id
             FOR UPDATE'
        );
        $flavorStatement->execute(['produto_id' => $productId]);
        $flavors = $flavorStatement->fetchAll();
        $matchedFlavor = null;

        foreach ($flavors as $flavor) {
            if (storefront_normalize_flavor((string) ($flavor['nome'] ?? '')) === $normalizedFlavor) {
                $matchedFlavor = $flavor;
                break;
            }
        }

        if (!$matchedFlavor) {
            if ($delta < 0) {
                throw new RuntimeException('O tamanho do pedido nao foi encontrado em estoque.');
            }
        } else {
            $currentFlavorStock = max(0, (int) ($matchedFlavor['estoque'] ?? 0));
            $nextFlavorStock = $currentFlavorStock + $delta;

            if ($nextFlavorStock < 0) {
                throw new RuntimeException('O tamanho selecionado nao possui estoque suficiente.');
            }

            $updateFlavor = db()->prepare(
                'UPDATE produto_sabores
                 SET estoque = :estoque
                 WHERE produto_id = :produto_id
                   AND sabor_id = :sabor_id'
            );
            $updateFlavor->execute([
                'estoque' => $nextFlavorStock,
                'produto_id' => $productId,
                'sabor_id' => (int) $matchedFlavor['sabor_id'],
            ]);
        }
    }

    $updateProduct = db()->prepare(
        'UPDATE produtos
         SET estoque = :estoque
         WHERE id = :id'
    );
    $updateProduct->execute([
        'estoque' => $nextStock,
        'id' => $productId,
    ]);

    sync_product_flavor_cache($productId);
}

function order_requires_customer(array $checkout): bool
{
    return storefront_normalize_checkout_method((string) ($checkout['fulfillment_method'] ?? 'delivery')) === 'pickup';
}

function order_asaas_payment_history_copy(string $paymentStatus): array
{
    return match (order_normalize_payment_status($paymentStatus)) {
        'paid' => [
            'titulo' => 'Pagamento online confirmado',
            'descricao' => 'O Asaas confirmou o pagamento online. A loja ja pode seguir com a separacao.',
        ],
        'cancelled' => [
            'titulo' => 'Pagamento online cancelado',
            'descricao' => 'O checkout online foi cancelado ou expirou no Asaas.',
        ],
        default => [
            'titulo' => 'Pagamento online aguardando',
            'descricao' => 'O checkout online foi gerado. Assim que o Asaas confirmar o pagamento, a loja podera continuar.',
        ],
    };
}

function order_pagbank_payment_history_copy(string $paymentStatus): array
{
    return match (order_normalize_payment_status($paymentStatus)) {
        'paid' => [
            'titulo' => 'Pagamento PagBank confirmado',
            'descricao' => 'O PagBank confirmou o pagamento online. A loja ja pode seguir com a separacao.',
        ],
        'authorized' => [
            'titulo' => 'Pagamento PagBank autorizado',
            'descricao' => 'O PagBank autorizou o pagamento e esta aguardando confirmacao final.',
        ],
        'declined' => [
            'titulo' => 'Pagamento PagBank recusado',
            'descricao' => 'O PagBank recusou o pagamento online.',
        ],
        'cancelled' => [
            'titulo' => 'Pagamento PagBank cancelado',
            'descricao' => 'O pagamento online foi cancelado ou expirou no PagBank.',
        ],
        default => [
            'titulo' => 'Pagamento PagBank aguardando',
            'descricao' => 'O pagamento online foi iniciado e esta aguardando atualizacao do PagBank.',
        ],
    };
}

function order_apply_asaas_payment_data(int $orderId, array $paymentData): void
{
    $order = find_order($orderId);

    if (!$order) {
        throw new RuntimeException('Pedido nao encontrado.');
    }

    db()->beginTransaction();

    try {
        $lockedStatement = db()->prepare(
            'SELECT *
             FROM pedidos
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $lockedStatement->execute(['id' => $orderId]);
        $lockedOrder = $lockedStatement->fetch();

        if (!$lockedOrder) {
            throw new RuntimeException('Pedido nao encontrado.');
        }

        $previousPaymentStatus = order_normalize_payment_status((string) ($lockedOrder['payment_status'] ?? 'none'));
        $nextPaymentStatus = order_normalize_payment_status((string) ($paymentData['payment_status'] ?? $previousPaymentStatus));
        $paymentPaidAt = !empty($paymentData['payment_paid_at'])
            ? (string) $paymentData['payment_paid_at']
            : (!empty($lockedOrder['payment_paid_at']) ? (string) $lockedOrder['payment_paid_at'] : null);

        $update = db()->prepare(
            'UPDATE pedidos
             SET payment_provider = :payment_provider,
                 payment_status = :payment_status,
                 payment_external_order_id = :payment_external_order_id,
                 payment_payload = :payment_payload,
                 payment_paid_at = :payment_paid_at,
                 payment_last_webhook_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'payment_provider' => 'asaas',
            'payment_status' => $nextPaymentStatus,
            'payment_external_order_id' => trim((string) ($paymentData['external_order_id'] ?? '')) !== ''
                ? trim((string) $paymentData['external_order_id'])
                : (string) ($lockedOrder['payment_external_order_id'] ?? null),
            'payment_payload' => trim((string) ($paymentData['payload_json'] ?? '')) !== ''
                ? (string) $paymentData['payload_json']
                : (string) ($lockedOrder['payment_payload'] ?? null),
            'payment_paid_at' => $paymentPaidAt,
            'id' => $orderId,
        ]);

        if ($previousPaymentStatus !== $nextPaymentStatus) {
            $copy = order_asaas_payment_history_copy($nextPaymentStatus);
            order_insert_custom_history(
                $orderId,
                (string) ($lockedOrder['status'] ?? 'pending'),
                $copy['titulo'],
                $copy['descricao']
            );
        }

        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }
}

function order_apply_pagbank_payment_data(int $orderId, array $paymentData): void
{
    $order = find_order($orderId);

    if (!$order) {
        throw new RuntimeException('Pedido nao encontrado.');
    }

    db()->beginTransaction();

    try {
        $lockedStatement = db()->prepare(
            'SELECT *
             FROM pedidos
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $lockedStatement->execute(['id' => $orderId]);
        $lockedOrder = $lockedStatement->fetch();

        if (!$lockedOrder) {
            throw new RuntimeException('Pedido nao encontrado.');
        }

        $previousPaymentStatus = order_normalize_payment_status((string) ($lockedOrder['payment_status'] ?? 'none'));
        $nextPaymentStatus = order_normalize_payment_status((string) ($paymentData['payment_status'] ?? $previousPaymentStatus));
        $paymentPaidAt = !empty($paymentData['payment_paid_at'])
            ? (string) $paymentData['payment_paid_at']
            : (!empty($lockedOrder['payment_paid_at']) ? (string) $lockedOrder['payment_paid_at'] : null);

        $update = db()->prepare(
            'UPDATE pedidos
             SET payment_provider = :payment_provider,
                 payment_status = :payment_status,
                 payment_external_order_id = :payment_external_order_id,
                 payment_external_charge_id = :payment_external_charge_id,
                 payment_external_qr_id = :payment_external_qr_id,
                 payment_pix_text = :payment_pix_text,
                 payment_pix_image_base64 = :payment_pix_image_base64,
                 payment_payload = :payment_payload,
                 payment_paid_at = :payment_paid_at,
                 payment_last_webhook_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'payment_provider' => 'pagbank',
            'payment_status' => $nextPaymentStatus,
            'payment_external_order_id' => trim((string) ($paymentData['external_order_id'] ?? '')) !== ''
                ? trim((string) $paymentData['external_order_id'])
                : (string) ($lockedOrder['payment_external_order_id'] ?? null),
            'payment_external_charge_id' => trim((string) ($paymentData['external_charge_id'] ?? '')) !== ''
                ? trim((string) $paymentData['external_charge_id'])
                : (string) ($lockedOrder['payment_external_charge_id'] ?? null),
            'payment_external_qr_id' => trim((string) ($paymentData['external_qr_id'] ?? '')) !== ''
                ? trim((string) $paymentData['external_qr_id'])
                : (string) ($lockedOrder['payment_external_qr_id'] ?? null),
            'payment_pix_text' => trim((string) ($paymentData['payment_pix_text'] ?? '')) !== ''
                ? (string) $paymentData['payment_pix_text']
                : (string) ($lockedOrder['payment_pix_text'] ?? null),
            'payment_pix_image_base64' => trim((string) ($paymentData['payment_pix_image_base64'] ?? '')) !== ''
                ? (string) $paymentData['payment_pix_image_base64']
                : (string) ($lockedOrder['payment_pix_image_base64'] ?? null),
            'payment_payload' => trim((string) ($paymentData['payload_json'] ?? '')) !== ''
                ? (string) $paymentData['payload_json']
                : (string) ($lockedOrder['payment_payload'] ?? null),
            'payment_paid_at' => $paymentPaidAt,
            'id' => $orderId,
        ]);

        if ($previousPaymentStatus !== $nextPaymentStatus) {
            $copy = order_pagbank_payment_history_copy($nextPaymentStatus);
            order_insert_custom_history(
                $orderId,
                (string) ($lockedOrder['status'] ?? 'pending'),
                $copy['titulo'],
                $copy['descricao']
            );
        }

        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }
}

function create_storefront_order(
    array $storeSettings,
    ?array $currentCustomer,
    array $cart,
    array $checkout
): array {
    if ($cart['items'] === []) {
        throw new RuntimeException('Seu carrinho esta vazio.');
    }

    if (!$currentCustomer) {
        throw new RuntimeException('Entre na sua conta para concluir o pedido.');
    }

    $fulfillmentMethod = storefront_normalize_checkout_method((string) ($checkout['fulfillment_method'] ?? 'delivery'));
    $paymentMethod = storefront_normalize_checkout_payment((string) ($checkout['payment_method'] ?? 'pix'));
    $cashChangeChoice = storefront_normalize_checkout_cash_change_choice($checkout['cash_change_choice'] ?? null);
    $cashChangeFor = $paymentMethod === 'cash'
        ? storefront_normalize_checkout_cash_change_for($checkout['cash_change_for'] ?? null)
        : null;
    $cashChangeDue = $cashChangeFor !== null
        ? max(0, $cashChangeFor - (float) ($checkout['total'] ?? 0))
        : null;

    if ($fulfillmentMethod === 'delivery' && !storefront_address_supports_delivery($checkout['delivery_address'] ?? null)) {
        throw new RuntimeException('Adicione um endereco valido em Volta Redonda-RJ ou Barra Mansa-RJ para concluir a entrega.');
    }

    if ($paymentMethod === 'cash') {
        if ($cashChangeChoice === 'exact') {
            $cashChangeFor = null;
            $cashChangeDue = null;
        } elseif ($cashChangeFor === null) {
            throw new RuntimeException('Informe para quanto precisa de troco no pagamento em dinheiro.');
        } elseif ($cashChangeFor < (float) ($checkout['total'] ?? 0)) {
            throw new RuntimeException('O valor informado para troco precisa ser maior ou igual ao total do pedido.');
        }
    }

    $addressSnapshot = order_address_snapshot($storeSettings, $fulfillmentMethod, $checkout['delivery_address'] ?? null);
    $trackingCode = function_exists('storefront_checkout_preview_tracking_code')
        ? storefront_checkout_preview_tracking_code()
        : '';

    if ($trackingCode === '' || order_tracking_code_exists($trackingCode)) {
        $trackingCode = order_generate_unique_tracking_code();
    }
    $paymentProvider = null;
    $paymentStatus = 'none';
    $paymentExternalOrderId = null;
    $paymentExternalChargeId = null;
    $paymentExternalQrId = null;
    $paymentPixText = null;
    $paymentPixImageBase64 = null;
    $paymentPayload = null;
    $paymentPaidAt = null;
    $paymentCheckoutUrl = null;
    $coupon = !empty($checkout['coupon']) && is_array($checkout['coupon']) ? $checkout['coupon'] : null;
    $couponDiscountAmount = max(0.0, (float) ($checkout['coupon_discount'] ?? 0));
    $couponShippingDiscount = max(0.0, (float) ($checkout['coupon_shipping_discount'] ?? 0));

    if (in_array($paymentMethod, ['pix', 'online_card'], true)) {
        $onlineProvider = online_payment_resolve_provider();

        if ($onlineProvider === 'pagbank' && $paymentMethod === 'pix') {
            $pagbankPixOrder = pagbank_create_pix_order($storeSettings, $currentCustomer, $cart, $checkout, $trackingCode);
            $paymentProvider = 'pagbank';
            $paymentStatus = order_normalize_payment_status((string) ($pagbankPixOrder['payment_status'] ?? 'waiting'));
            $paymentExternalOrderId = trim((string) ($pagbankPixOrder['external_order_id'] ?? ''));
            $paymentExternalChargeId = trim((string) ($pagbankPixOrder['external_charge_id'] ?? ''));
            $paymentExternalQrId = trim((string) ($pagbankPixOrder['external_qr_id'] ?? ''));
            $paymentPixText = trim((string) ($pagbankPixOrder['payment_pix_text'] ?? ''));
            $paymentPixImageBase64 = trim((string) ($pagbankPixOrder['payment_pix_image_base64'] ?? ''));
            $paymentPayload = (string) ($pagbankPixOrder['payload_json'] ?? '');
        } elseif ($onlineProvider === 'pagbank' && $paymentMethod === 'online_card') {
            throw new RuntimeException('Cartao PagBank sera liberado na proxima etapa. Use Pix por enquanto.');
        } elseif ($onlineProvider !== 'asaas') {
            throw new RuntimeException('O pagamento online esta indisponivel no momento.');
        } else {
            $asaasCheckout = asaas_create_checkout($storeSettings, $currentCustomer, $cart, $checkout, $trackingCode);
            $paymentProvider = 'asaas';
            $paymentStatus = order_normalize_payment_status((string) ($asaasCheckout['payment_status'] ?? 'waiting'));
            $paymentExternalOrderId = trim((string) ($asaasCheckout['external_order_id'] ?? ''));
            $paymentPayload = (string) ($asaasCheckout['payload_json'] ?? '');
            $paymentCheckoutUrl = trim((string) ($asaasCheckout['pay_url'] ?? ''));

            if ($paymentExternalOrderId === '' || $paymentCheckoutUrl === '') {
                throw new RuntimeException('Nao foi possivel gerar o checkout online agora. Tente novamente.');
            }
        }
    }

    db()->beginTransaction();

    try {
        $orderInsert = db()->prepare(
            'INSERT INTO pedidos (
                cliente_id, codigo_rastreio, status, fulfillment_method, payment_method,
                payment_provider, payment_status, payment_external_order_id, payment_external_charge_id,
                payment_external_qr_id, payment_pix_text, payment_pix_image_base64, payment_payload,
                payment_paid_at,
                cupom_id, cupom_codigo, cupom_nome, cupom_tipo, cupom_desconto, cupom_frete_desconto,
                subtotal, taxa_entrega, total, cash_change_for, cash_change_due,
                nome_cliente, email_cliente, telefone_cliente, cpf_cliente,
                cep, rua, bairro, numero, complemento, cidade, uf, endereco_snapshot,
                status_atualizado_em
             ) VALUES (
                :cliente_id, :codigo_rastreio, :status, :fulfillment_method, :payment_method,
                :payment_provider, :payment_status, :payment_external_order_id, :payment_external_charge_id,
                :payment_external_qr_id, :payment_pix_text, :payment_pix_image_base64, :payment_payload,
                :payment_paid_at,
                :cupom_id, :cupom_codigo, :cupom_nome, :cupom_tipo, :cupom_desconto, :cupom_frete_desconto,
                :subtotal, :taxa_entrega, :total, :cash_change_for, :cash_change_due,
                :nome_cliente, :email_cliente, :telefone_cliente, :cpf_cliente,
                :cep, :rua, :bairro, :numero, :complemento, :cidade, :uf, :endereco_snapshot,
                NOW()
             )'
        );

        $orderInsert->execute([
            'cliente_id' => (int) $currentCustomer['id'],
            'codigo_rastreio' => $trackingCode,
            'status' => 'pending',
            'fulfillment_method' => $fulfillmentMethod,
            'payment_method' => $paymentMethod,
            'payment_provider' => $paymentProvider,
            'payment_status' => $paymentStatus,
            'payment_external_order_id' => $paymentExternalOrderId,
            'payment_external_charge_id' => $paymentExternalChargeId,
            'payment_external_qr_id' => $paymentExternalQrId,
            'payment_pix_text' => $paymentPixText,
            'payment_pix_image_base64' => $paymentPixImageBase64,
            'payment_payload' => $paymentPayload,
            'payment_paid_at' => $paymentPaidAt,
            'cupom_id' => $coupon ? (int) ($coupon['id'] ?? 0) : null,
            'cupom_codigo' => $coupon ? (string) ($coupon['codigo'] ?? '') : null,
            'cupom_nome' => $coupon ? (string) ($coupon['nome'] ?? '') : null,
            'cupom_tipo' => $coupon ? (string) ($coupon['tipo'] ?? '') : null,
            'cupom_desconto' => $couponDiscountAmount,
            'cupom_frete_desconto' => $couponShippingDiscount,
            'subtotal' => (float) ($cart['subtotal'] ?? 0),
            'taxa_entrega' => (float) ($checkout['delivery_fee'] ?? 0),
            'total' => (float) ($checkout['total'] ?? 0),
            'cash_change_for' => $cashChangeFor,
            'cash_change_due' => $cashChangeDue,
            'nome_cliente' => normalize_person_name((string) ($currentCustomer['nome'] ?? 'Cliente')),
            'email_cliente' => normalize_email((string) ($currentCustomer['email'] ?? '')),
            'telefone_cliente' => digits_only((string) ($currentCustomer['telefone'] ?? '')),
            'cpf_cliente' => normalize_cpf((string) ($currentCustomer['cpf'] ?? '')),
            'cep' => $addressSnapshot['cep'],
            'rua' => $addressSnapshot['rua'],
            'bairro' => $addressSnapshot['bairro'],
            'numero' => $addressSnapshot['numero'],
            'complemento' => $addressSnapshot['complemento'],
            'cidade' => $addressSnapshot['cidade'],
            'uf' => $addressSnapshot['uf'],
            'endereco_snapshot' => $addressSnapshot['endereco_snapshot'],
        ]);

        $orderId = (int) db()->lastInsertId();
        $itemInsert = db()->prepare(
            'INSERT INTO pedido_itens (
                pedido_id, produto_id, produto_nome, categoria_nome, marca_nome, sabor,
                quantidade, preco_unitario, subtotal_item, imagem
             ) VALUES (
                :pedido_id, :produto_id, :produto_nome, :categoria_nome, :marca_nome, :sabor,
                :quantidade, :preco_unitario, :subtotal_item, :imagem
             )'
        );

        foreach ($cart['items'] as $item) {
            $product = $item['product'] ?? null;

            if (!is_array($product) || empty($product['id'])) {
                throw new RuntimeException('Um item do carrinho nao esta mais disponivel.');
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 0));
            $flavor = trim((string) ($item['flavor'] ?? ''));
            order_adjust_stock_for_item((int) $product['id'], $flavor !== '' ? $flavor : null, -$quantity);

            $unitPrice = isset($item['unit_price'])
                ? (float) $item['unit_price']
                : product_final_price($product);
            $itemInsert->execute([
                'pedido_id' => $orderId,
                'produto_id' => (int) $product['id'],
                'produto_nome' => (string) ($product['nome'] ?? ''),
                'categoria_nome' => (string) ($product['categoria_nome'] ?? ''),
                'marca_nome' => (string) ($product['marca_nome'] ?? ''),
                'sabor' => $flavor !== '' ? $flavor : null,
                'quantidade' => $quantity,
                'preco_unitario' => $unitPrice,
                'subtotal_item' => $unitPrice * $quantity,
                'imagem' => (string) ($product['imagem'] ?? ''),
            ]);
        }

        order_insert_history($orderId, 'pending', $fulfillmentMethod, null);

        if ($paymentProvider === 'asaas') {
            $paymentHistory = order_asaas_payment_history_copy($paymentStatus);
            order_insert_custom_history($orderId, 'pending', $paymentHistory['titulo'], $paymentHistory['descricao']);
        } elseif ($paymentProvider === 'pagbank') {
            $paymentHistory = order_pagbank_payment_history_copy($paymentStatus);
            order_insert_custom_history($orderId, 'pending', $paymentHistory['titulo'], $paymentHistory['descricao']);
        }

        db()->commit();

        $createdOrder = find_order($orderId);

        if (!$createdOrder) {
            throw new RuntimeException('Pedido criado, mas nao foi possivel carregar os dados finais.');
        }

        $createdOrder['payment_checkout_url'] = $paymentCheckoutUrl;

        order_notify_customer_status($createdOrder, 'pending');

        return $createdOrder;
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }
}

function find_order(int $orderId): ?array
{
    $statement = db()->prepare(
        'SELECT p.*,
                a.nome AS admin_nome
         FROM pedidos p
         LEFT JOIN admins a ON a.id = p.ultimo_admin_id
         WHERE p.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $orderId]);
    $order = $statement->fetch();

    return $order ?: null;
}

function find_order_by_tracking_code(string $trackingCode): ?array
{
    $statement = db()->prepare(
        'SELECT p.*,
                a.nome AS admin_nome
         FROM pedidos p
         LEFT JOIN admins a ON a.id = p.ultimo_admin_id
         WHERE p.codigo_rastreio = :codigo
         LIMIT 1'
    );
    $statement->execute(['codigo' => order_normalize_tracking_code($trackingCode)]);
    $order = $statement->fetch();

    return $order ?: null;
}

function fetch_order_items(int $orderId): array
{
    $statement = db()->prepare(
        'SELECT *
         FROM pedido_itens
         WHERE pedido_id = :pedido_id
         ORDER BY id ASC'
    );
    $statement->execute(['pedido_id' => $orderId]);

    return $statement->fetchAll();
}

function fetch_order_history(int $orderId): array
{
    $statement = db()->prepare(
        'SELECT ph.*,
                a.nome AS admin_nome
         FROM pedido_historico ph
         LEFT JOIN admins a ON a.id = ph.admin_id
         WHERE ph.pedido_id = :pedido_id
         ORDER BY ph.criado_em ASC, ph.id ASC'
    );
    $statement->execute(['pedido_id' => $orderId]);

    return $statement->fetchAll();
}

function fetch_all_orders(): array
{
    return db()->query(
        'SELECT *
         FROM pedidos
         ORDER BY FIELD(status, \'pending\', \'approved\', \'ready_pickup\', \'out_for_delivery\', \'completed\', \'cancelled\', \'rejected\'),
                  COALESCE(status_atualizado_em, criado_em) DESC,
                  id DESC'
    )->fetchAll();
}

function fetch_customer_orders(int $customerId): array
{
    if ($customerId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT p.*,
                a.nome AS admin_nome
         FROM pedidos p
         LEFT JOIN admins a ON a.id = p.ultimo_admin_id
         WHERE p.cliente_id = :cliente_id
         ORDER BY FIELD(p.status, \'pending\', \'approved\', \'ready_pickup\', \'out_for_delivery\', \'completed\', \'cancelled\', \'rejected\'),
                  COALESCE(p.status_atualizado_em, p.criado_em) DESC,
                  p.id DESC'
    );
    $statement->execute(['cliente_id' => $customerId]);

    return $statement->fetchAll();
}

function order_restore_stock(int $orderId): void
{
    $order = find_order($orderId);

    if (!$order || !empty($order['estoque_devolvido_em'])) {
        return;
    }

    $items = fetch_order_items($orderId);

    foreach ($items as $item) {
        order_adjust_stock_for_item(
            (int) ($item['produto_id'] ?? 0),
            !empty($item['sabor']) ? (string) $item['sabor'] : null,
            (int) ($item['quantidade'] ?? 0)
        );
    }

    $statement = db()->prepare(
        'UPDATE pedidos
         SET estoque_devolvido_em = NOW()
         WHERE id = :id'
    );
    $statement->execute(['id' => $orderId]);
}

function order_allowed_transitions(array $order): array
{
    $status = order_normalize_status((string) ($order['status'] ?? 'pending'));
    $fulfillment = storefront_normalize_checkout_method((string) ($order['fulfillment_method'] ?? 'delivery'));
    $canApprove = !order_requires_paid_online_payment($order) || order_payment_is_paid($order);

    return match ($status) {
        'pending' => $canApprove ? ['approved', 'rejected'] : ['rejected', 'cancelled'],
        'approved' => $fulfillment === 'pickup'
            ? ['ready_pickup', 'completed', 'cancelled']
            : ['out_for_delivery', 'completed', 'cancelled'],
        'ready_pickup', 'out_for_delivery' => ['completed', 'cancelled'],
        default => [],
    };
}

function order_can_transition(array $order, string $nextStatus): bool
{
    return in_array(
        order_normalize_status($nextStatus),
        order_allowed_transitions($order),
        true
    );
}

function order_transition(int $orderId, string $nextStatus, int $adminId): void
{
    $nextStatus = order_normalize_status($nextStatus);
    $order = find_order($orderId);

    if (!$order) {
        throw new RuntimeException('Pedido nao encontrado.');
    }

    if ($nextStatus === 'approved' && order_requires_paid_online_payment($order) && !order_payment_is_paid($order)) {
        throw new RuntimeException('Aguarde a confirmacao do pagamento online antes de aprovar este pedido.');
    }

    if (!order_can_transition($order, $nextStatus)) {
        throw new RuntimeException('Transicao de status invalida para este pedido.');
    }

    db()->beginTransaction();

    try {
        $lockedStatement = db()->prepare(
            'SELECT *
             FROM pedidos
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $lockedStatement->execute(['id' => $orderId]);
        $lockedOrder = $lockedStatement->fetch();

        if (!$lockedOrder) {
            throw new RuntimeException('Pedido nao encontrado.');
        }

        if (in_array($nextStatus, ['rejected', 'cancelled'], true) && empty($lockedOrder['estoque_devolvido_em'])) {
            order_restore_stock($orderId);
            $lockedStatement->execute(['id' => $orderId]);
            $lockedOrder = $lockedStatement->fetch() ?: $lockedOrder;
        }

        $update = db()->prepare(
            'UPDATE pedidos
             SET status = :status,
                 ultimo_admin_id = :admin_id,
                 status_atualizado_em = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'status' => $nextStatus,
            'admin_id' => $adminId,
            'id' => $orderId,
        ]);

        order_insert_history($orderId, $nextStatus, (string) ($lockedOrder['fulfillment_method'] ?? 'delivery'), $adminId);
        db()->commit();

        $updatedOrder = find_order($orderId);

        if ($updatedOrder) {
            order_notify_customer_status($updatedOrder, $nextStatus);
        }
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }
}

function order_delete(int $orderId): void
{
    $order = find_order($orderId);

    if (!$order) {
        throw new RuntimeException('Pedido nao encontrado.');
    }

    db()->beginTransaction();

    try {
        $lockedStatement = db()->prepare(
            'SELECT *
             FROM pedidos
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $lockedStatement->execute(['id' => $orderId]);
        $lockedOrder = $lockedStatement->fetch();

        if (!$lockedOrder) {
            throw new RuntimeException('Pedido nao encontrado.');
        }

        if (empty($lockedOrder['estoque_devolvido_em'])) {
            order_restore_stock($orderId);
        }

        $delete = db()->prepare('DELETE FROM pedidos WHERE id = :id');
        $delete->execute(['id' => $orderId]);

        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }
}

function order_public_steps(array $order): array
{
    $status = order_normalize_status((string) ($order['status'] ?? 'pending'));
    $pickup = storefront_normalize_checkout_method((string) ($order['fulfillment_method'] ?? 'delivery')) === 'pickup';
    $onlinePayment = order_requires_paid_online_payment($order);
    $paymentConfirmed = in_array(
        order_normalize_payment_status((string) ($order['payment_status'] ?? 'none')),
        ['authorized', 'paid'],
        true
    );

    if ($status === 'rejected') {
        return [
            [
                'status' => 'pending',
                'title' => 'Pedido recebido',
                'active' => true,
            ],
            [
                'status' => 'rejected',
                'title' => 'Pedido recusado',
                'active' => true,
            ],
        ];
    }

    if ($status === 'cancelled') {
        return [
            [
                'status' => 'pending',
                'title' => 'Pedido recebido',
                'active' => true,
            ],
            [
                'status' => 'approved',
                'title' => 'Pedido aceito',
                'active' => true,
            ],
            [
                'status' => 'cancelled',
                'title' => 'Pedido cancelado',
                'active' => true,
                'current' => true,
            ],
        ];
    }

    if ($onlinePayment) {
        $orderedStatuses = $pickup
            ? ['pending', 'payment_confirmation', 'approved', 'ready_pickup', 'completed']
            : ['pending', 'payment_confirmation', 'approved', 'out_for_delivery', 'completed'];

        $titles = [
            'pending' => 'Pedido recebido',
            'payment_confirmation' => 'Confirmacao de pagamento',
            'approved' => 'Pedido aceito',
            'ready_pickup' => 'Pronto para retirada',
            'out_for_delivery' => 'Saiu para entrega',
            'completed' => 'Concluido',
            'cancelled' => 'Pedido cancelado',
        ];

        $steps = [];

        foreach ($orderedStatuses as $itemStatus) {
            $isActive = match ($itemStatus) {
                'pending' => true,
                'payment_confirmation' => $paymentConfirmed || in_array($status, ['approved', 'ready_pickup', 'out_for_delivery', 'completed'], true),
                'approved' => in_array($status, ['approved', 'ready_pickup', 'out_for_delivery', 'completed'], true),
                'ready_pickup' => in_array($status, ['ready_pickup', 'completed'], true),
                'out_for_delivery' => in_array($status, ['out_for_delivery', 'completed'], true),
                'completed' => $status === 'completed',
                default => false,
            };

            $isCurrent = match ($itemStatus) {
                'payment_confirmation' => $status === 'pending',
                default => $status === $itemStatus,
            };

            $steps[] = [
                'status' => $itemStatus,
                'title' => $titles[$itemStatus] ?? $itemStatus,
                'active' => $isActive,
                'current' => $isCurrent,
            ];
        }

        return $steps;
    }

    $orderedStatuses = $pickup
        ? ['pending', 'approved', 'ready_pickup', 'completed']
        : ['pending', 'approved', 'out_for_delivery', 'completed'];

    $activeIndex = array_search($status, $orderedStatuses, true);
    $activeIndex = $activeIndex === false ? 0 : $activeIndex;

    $titles = [
        'pending' => 'Pedido recebido',
        'approved' => 'Pedido aceito',
        'ready_pickup' => 'Pronto para retirada',
        'out_for_delivery' => 'Saiu para entrega',
        'completed' => 'Concluido',
        'cancelled' => 'Pedido cancelado',
    ];

    $steps = [];

    foreach ($orderedStatuses as $index => $itemStatus) {
        $steps[] = [
            'status' => $itemStatus,
            'title' => $titles[$itemStatus] ?? $itemStatus,
            'active' => $index <= $activeIndex,
            'current' => $status === $itemStatus,
        ];
    }

    return $steps;
}
