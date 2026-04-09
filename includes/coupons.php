<?php
declare(strict_types=1);

function coupon_type_options(): array
{
    return [
        'percent' => 'Desconto percentual',
        'fixed' => 'Desconto em valor total',
        'free_shipping' => 'Frete gratis',
        'above_value' => 'Acima de x valor',
    ];
}

function coupon_scope_options(): array
{
    return [
        'order' => 'Pedido inteiro',
        'products' => 'Produtos selecionados',
        'brands' => 'Marcas selecionadas',
    ];
}

function coupon_normalize_code(?string $value): string
{
    $normalized = strtoupper(trim((string) $value));
    return preg_replace('/[^A-Z0-9_-]+/', '', $normalized) ?? '';
}

function coupon_normalize_type(?string $value): string
{
    $normalized = strtolower(trim((string) $value));
    $allowed = array_keys(coupon_type_options());

    return in_array($normalized, $allowed, true) ? $normalized : 'percent';
}

function coupon_normalize_scope(?string $value): string
{
    $normalized = strtolower(trim((string) $value));
    $allowed = array_keys(coupon_scope_options());

    return in_array($normalized, $allowed, true) ? $normalized : 'order';
}

function coupon_normalize_datetime_input(?string $value): ?string
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($raw))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function coupon_compose_datetime_input(?string $date, ?string $time = null, bool $isEnd = false): ?string
{
    $date = trim((string) $date);
    $time = trim((string) $time);

    if ($date === '') {
        return null;
    }

    if ($time === '') {
        $time = $isEnd ? '23:59' : '00:00';
    }

    return coupon_normalize_datetime_input($date . ' ' . $time);
}

function coupon_display_value(array $coupon): string
{
    $type = coupon_normalize_type((string) ($coupon['tipo'] ?? 'percent'));

    return match ($type) {
        'fixed', 'above_value' => format_currency((float) ($coupon['valor_desconto'] ?? 0)),
        'free_shipping' => 'Frete gratis',
        default => rtrim(rtrim(number_format((float) ($coupon['valor_desconto'] ?? 0), 2, ',', '.'), '0'), ',') . '%',
    };
}

function coupon_type_label(?string $value): string
{
    $type = coupon_normalize_type($value);
    $options = coupon_type_options();

    return $options[$type] ?? $options['percent'];
}

function coupon_scope_label(?string $value): string
{
    $scope = coupon_normalize_scope($value);
    $options = coupon_scope_options();

    return $options[$scope] ?? $options['order'];
}

function find_coupon(int $couponId): ?array
{
    $statement = db()->prepare('SELECT * FROM cupons WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $couponId]);
    $coupon = $statement->fetch();

    return $coupon ?: null;
}

function find_coupon_by_code(string $code): ?array
{
    $normalizedCode = coupon_normalize_code($code);

    if ($normalizedCode === '') {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM cupons WHERE codigo = :codigo LIMIT 1');
    $statement->execute(['codigo' => $normalizedCode]);
    $coupon = $statement->fetch();

    return $coupon ?: null;
}

function fetch_all_coupons(): array
{
    $coupons = db()->query(
        'SELECT
            c.*,
            (
                SELECT COUNT(*)
                FROM cliente_cupons cc
                WHERE cc.cupom_id = c.id
            ) AS redemption_count
         FROM cupons c
         ORDER BY c.ativo DESC, c.criado_em DESC, c.id DESC'
    )->fetchAll();

    foreach ($coupons as &$coupon) {
        $coupon = coupon_enrich_redemption_stats($coupon);
    }
    unset($coupon);

    return $coupons;
}

function coupon_redemption_limit(array $coupon): ?int
{
    $limit = isset($coupon['limite_resgates']) ? (int) $coupon['limite_resgates'] : 0;

    return $limit > 0 ? $limit : null;
}

function coupon_count_redemptions(int $couponId): int
{
    if ($couponId <= 0) {
        return 0;
    }

    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM cliente_cupons
         WHERE cupom_id = :cupom_id'
    );
    $statement->execute(['cupom_id' => $couponId]);

    return (int) $statement->fetchColumn();
}

function coupon_customer_has_redeemed(int $customerId, int $couponId): bool
{
    if ($customerId <= 0 || $couponId <= 0) {
        return false;
    }

    $statement = db()->prepare(
        'SELECT 1
         FROM cliente_cupons
         WHERE cliente_id = :cliente_id
           AND cupom_id = :cupom_id
         LIMIT 1'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'cupom_id' => $couponId,
    ]);

    return (bool) $statement->fetchColumn();
}

function coupon_enrich_redemption_stats(array $coupon): array
{
    $limit = coupon_redemption_limit($coupon);
    $count = isset($coupon['redemption_count'])
        ? (int) $coupon['redemption_count']
        : coupon_count_redemptions((int) ($coupon['id'] ?? 0));

    $coupon['redemption_count'] = $count;
    $coupon['redemption_limit'] = $limit;
    $coupon['redemptions_remaining'] = $limit !== null ? max(0, $limit - $count) : null;
    $coupon['redemption_limit_reached'] = $limit !== null && $count >= $limit;

    return $coupon;
}

function fetch_customer_coupon_wallet(int $customerId): array
{
    if ($customerId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT
            c.*,
            cc.resgatado_em,
            (
                SELECT COUNT(*)
                FROM cliente_cupons ccr
                WHERE ccr.cupom_id = c.id
            ) AS redemption_count
         FROM cupons c
         LEFT JOIN cliente_cupons cc
            ON cc.cupom_id = c.id
           AND cc.cliente_id = :cliente_id
         ORDER BY
            cc.resgatado_em IS NOT NULL DESC,
            c.ativo DESC,
            c.criado_em DESC,
            c.id DESC'
    );
    $statement->execute(['cliente_id' => $customerId]);

    $coupons = $statement->fetchAll();

    foreach ($coupons as &$coupon) {
        $coupon = coupon_enrich_redemption_stats($coupon);
        $coupon['is_redeemed'] = !empty($coupon['resgatado_em']);
        $coupon['is_active_now'] = coupon_is_active($coupon);
        $coupon['can_redeem_now'] = !$coupon['is_redeemed']
            && $coupon['is_active_now']
            && empty($coupon['redemption_limit_reached']);
    }
    unset($coupon);

    return $coupons;
}

function find_customer_coupon_wallet_entry(int $customerId, int $couponId): ?array
{
    if ($customerId <= 0 || $couponId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT
            c.*,
            cc.resgatado_em,
            (
                SELECT COUNT(*)
                FROM cliente_cupons ccr
                WHERE ccr.cupom_id = c.id
            ) AS redemption_count
         FROM cupons c
         LEFT JOIN cliente_cupons cc
            ON cc.cupom_id = c.id
           AND cc.cliente_id = :cliente_id
         WHERE c.id = :cupom_id
         LIMIT 1'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'cupom_id' => $couponId,
    ]);
    $coupon = $statement->fetch();

    if (!$coupon) {
        return null;
    }

    $coupon = coupon_enrich_redemption_stats($coupon);
    $coupon['is_redeemed'] = !empty($coupon['resgatado_em']);
    $coupon['is_active_now'] = coupon_is_active($coupon);
    $coupon['can_redeem_now'] = !$coupon['is_redeemed']
        && $coupon['is_active_now']
        && empty($coupon['redemption_limit_reached']);

    return $coupon;
}

function redeem_customer_coupon(int $customerId, int $couponId): bool
{
    if ($customerId <= 0 || $couponId <= 0) {
        return false;
    }

    db()->beginTransaction();

    try {
        if (coupon_customer_has_redeemed($customerId, $couponId)) {
            db()->commit();
            return true;
        }

        $statement = db()->prepare(
            'SELECT *
             FROM cupons
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['id' => $couponId]);
        $coupon = $statement->fetch();

        if (!$coupon || !coupon_is_active($coupon)) {
            db()->rollBack();
            return false;
        }

        $coupon = coupon_enrich_redemption_stats($coupon);

        if (!empty($coupon['redemption_limit_reached'])) {
            db()->rollBack();
            return false;
        }

        $insert = db()->prepare(
            'INSERT INTO cliente_cupons (cliente_id, cupom_id)
             VALUES (:cliente_id, :cupom_id)'
        );
        $insert->execute([
            'cliente_id' => $customerId,
            'cupom_id' => $couponId,
        ]);

        db()->commit();
        return true;
    } catch (Throwable) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        return false;
    }
}

function fetch_coupon_product_ids(int $couponId): array
{
    if ($couponId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT produto_id
         FROM cupom_produtos
         WHERE cupom_id = :cupom_id'
    );
    $statement->execute(['cupom_id' => $couponId]);

    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function fetch_coupon_products(int $couponId): array
{
    if ($couponId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT p.*
         FROM cupom_produtos cp
         INNER JOIN produtos p ON p.id = cp.produto_id
         WHERE cp.cupom_id = :cupom_id
         ORDER BY p.nome ASC'
    );
    $statement->execute(['cupom_id' => $couponId]);

    return $statement->fetchAll();
}

function fetch_coupon_brand_ids(int $couponId): array
{
    if ($couponId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT marca_id
         FROM cupom_marcas
         WHERE cupom_id = :cupom_id'
    );
    $statement->execute(['cupom_id' => $couponId]);

    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function fetch_coupon_brands(int $couponId): array
{
    if ($couponId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT m.*
         FROM cupom_marcas cm
         INNER JOIN marcas m ON m.id = cm.marca_id
         WHERE cm.cupom_id = :cupom_id
         ORDER BY m.nome ASC'
    );
    $statement->execute(['cupom_id' => $couponId]);

    return $statement->fetchAll();
}

function coupon_sync_products(int $couponId, array $productIds): void
{
    $productIds = array_values(array_unique(array_filter(
        array_map(static fn(mixed $value): int => (int) $value, $productIds),
        static fn(int $value): bool => $value > 0
    )));

    $delete = db()->prepare('DELETE FROM cupom_produtos WHERE cupom_id = :cupom_id');
    $delete->execute(['cupom_id' => $couponId]);

    if ($productIds === []) {
        return;
    }

    $insert = db()->prepare(
        'INSERT INTO cupom_produtos (cupom_id, produto_id)
         VALUES (:cupom_id, :produto_id)'
    );

    foreach ($productIds as $productId) {
        $insert->execute([
            'cupom_id' => $couponId,
            'produto_id' => $productId,
        ]);
    }
}

function coupon_sync_brands(int $couponId, array $brandIds): void
{
    $brandIds = array_values(array_unique(array_filter(
        array_map(static fn(mixed $value): int => (int) $value, $brandIds),
        static fn(int $value): bool => $value > 0
    )));

    $delete = db()->prepare('DELETE FROM cupom_marcas WHERE cupom_id = :cupom_id');
    $delete->execute(['cupom_id' => $couponId]);

    if ($brandIds === []) {
        return;
    }

    $insert = db()->prepare(
        'INSERT INTO cupom_marcas (cupom_id, marca_id)
         VALUES (:cupom_id, :marca_id)'
    );

    foreach ($brandIds as $brandId) {
        $insert->execute([
            'cupom_id' => $couponId,
            'marca_id' => $brandId,
        ]);
    }
}

function coupon_is_active(array $coupon, ?DateTimeImmutable $now = null): bool
{
    if ((int) ($coupon['ativo'] ?? 0) !== 1) {
        return false;
    }

    $now = $now ?? new DateTimeImmutable('now');
    $startsAt = !empty($coupon['starts_at']) ? new DateTimeImmutable((string) $coupon['starts_at']) : null;
    $endsAt = !empty($coupon['ends_at']) ? new DateTimeImmutable((string) $coupon['ends_at']) : null;

    if ($startsAt && $startsAt > $now) {
        return false;
    }

    if ($endsAt && $endsAt < $now) {
        return false;
    }

    return true;
}

function coupon_match_subtotal(array $coupon, array $cart): float
{
    $scope = coupon_normalize_scope((string) ($coupon['escopo'] ?? 'order'));

    if ($scope === 'order') {
        return (float) ($cart['subtotal'] ?? 0);
    }

    $productIds = [];
    $brandIds = [];

    if ($scope === 'products') {
        $productIds = fetch_coupon_product_ids((int) ($coupon['id'] ?? 0));

        if ($productIds === []) {
            return 0.0;
        }
    }

    if ($scope === 'brands') {
        $brandIds = fetch_coupon_brand_ids((int) ($coupon['id'] ?? 0));

        if ($brandIds === []) {
            return 0.0;
        }
    }

    $subtotal = 0.0;

    foreach (($cart['items'] ?? []) as $item) {
        $productId = (int) (($item['product']['id'] ?? 0));
        $brandId = (int) (($item['product']['marca_id'] ?? 0));

        if (
            ($scope === 'products' && in_array($productId, $productIds, true))
            || ($scope === 'brands' && in_array($brandId, $brandIds, true))
        ) {
            $subtotal += (float) ($item['line_total'] ?? 0);
        }
    }

    return $subtotal;
}

function coupon_build_description(array $coupon): string
{
    $type = coupon_normalize_type((string) ($coupon['tipo'] ?? 'percent'));
    $scope = coupon_normalize_scope((string) ($coupon['escopo'] ?? 'order'));
    $value = coupon_display_value($coupon);
    $minimumSubtotal = max(0, (float) ($coupon['subtotal_minimo'] ?? 0));
    $scopeText = match ($scope) {
        'products' => 'em produtos selecionados',
        'brands' => 'em marcas selecionadas',
        default => 'no pedido inteiro',
    };

    if ($type === 'free_shipping') {
        $description = $scope === 'order'
            ? 'Frete gratis para pedidos com entrega'
            : 'Frete gratis ' . $scopeText;

        if ($minimumSubtotal > 0) {
            $description .= ' acima de ' . format_currency($minimumSubtotal);
        }

        return $description . '.';
    }

    $description = $value . ' ' . $scopeText;

    if ($minimumSubtotal > 0) {
        $description .= ' acima de ' . format_currency($minimumSubtotal);
    }

    return $description . '.';
}

function coupon_evaluate(array $coupon, array $cart, string $fulfillmentMethod = 'delivery', ?array $deliveryAddress = null): array
{
    $type = coupon_normalize_type((string) ($coupon['tipo'] ?? 'percent'));
    $scope = coupon_normalize_scope((string) ($coupon['escopo'] ?? 'order'));
    $subtotal = (float) ($cart['subtotal'] ?? 0);
    $eligibleSubtotal = coupon_match_subtotal($coupon, $cart);
    $minSubtotal = max(0, (float) ($coupon['subtotal_minimo'] ?? 0));
    $comparisonSubtotal = $scope === 'order' ? $subtotal : $eligibleSubtotal;

    if (!coupon_is_active($coupon)) {
        return [
            'valid' => false,
            'error' => 'Este cupom nao esta ativo no momento.',
            'coupon' => $coupon,
        ];
    }

    if ($subtotal <= 0) {
        return [
            'valid' => false,
            'error' => 'Seu carrinho esta vazio.',
            'coupon' => $coupon,
        ];
    }

    if ($comparisonSubtotal <= 0) {
        return [
            'valid' => false,
            'error' => match ($scope) {
                'products' => 'Este cupom nao se aplica aos produtos do seu carrinho.',
                'brands' => 'Este cupom nao se aplica as marcas do seu carrinho.',
                default => 'Nao foi possivel aplicar este cupom.',
            },
            'coupon' => $coupon,
        ];
    }

    if ($minSubtotal > 0 && $comparisonSubtotal < $minSubtotal) {
        return [
            'valid' => false,
            'error' => 'Este cupom exige subtotal minimo de ' . format_currency($minSubtotal) . '.',
            'coupon' => $coupon,
        ];
    }

    if ($type === 'free_shipping') {
        if (storefront_normalize_checkout_method($fulfillmentMethod) !== 'delivery') {
            return [
                'valid' => false,
                'error' => 'Este cupom libera o frete apenas para entrega.',
                'coupon' => $coupon,
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'type' => $type,
            'scope' => $scope,
            'discount_amount' => 0.0,
            'shipping_discount' => storefront_delivery_fee($deliveryAddress),
            'total_discount' => storefront_delivery_fee($deliveryAddress),
            'eligible_subtotal' => $eligibleSubtotal,
            'description' => coupon_build_description($coupon),
        ];
    }

    $value = max(0, (float) ($coupon['valor_desconto'] ?? 0));
    $discountAmount = in_array($type, ['fixed', 'above_value'], true)
        ? min($eligibleSubtotal, $value)
        : round($eligibleSubtotal * ($value / 100), 2);

    if ($discountAmount <= 0) {
        return [
            'valid' => false,
            'error' => 'Este cupom nao gerou desconto para o pedido atual.',
            'coupon' => $coupon,
        ];
    }

    return [
        'valid' => true,
        'coupon' => $coupon,
        'type' => $type,
        'scope' => $scope,
        'discount_amount' => $discountAmount,
        'shipping_discount' => 0.0,
        'total_discount' => $discountAmount,
        'eligible_subtotal' => $eligibleSubtotal,
        'description' => coupon_build_description($coupon),
    ];
}

function storefront_coupon_session_code(): string
{
    return coupon_normalize_code((string) ($_SESSION['storefront_coupon_code'] ?? ''));
}

function storefront_save_coupon_code(?string $code): void
{
    $normalizedCode = coupon_normalize_code($code);

    if ($normalizedCode === '') {
        unset($_SESSION['storefront_coupon_code']);
        return;
    }

    $_SESSION['storefront_coupon_code'] = $normalizedCode;
}

function storefront_clear_coupon_code(): void
{
    unset($_SESSION['storefront_coupon_code']);
}

function storefront_resolve_coupon_application(
    array $cart,
    string $fulfillmentMethod = 'delivery',
    ?string $code = null,
    ?array $currentCustomer = null,
    ?array $deliveryAddress = null
): array
{
    $resolvedCode = $code !== null ? coupon_normalize_code($code) : storefront_coupon_session_code();

    if ($resolvedCode === '') {
        return [
            'code' => '',
            'valid' => false,
            'coupon' => null,
            'discount_amount' => 0.0,
            'shipping_discount' => 0.0,
            'total_discount' => 0.0,
            'description' => '',
            'error' => '',
        ];
    }

    $coupon = find_coupon_by_code($resolvedCode);

    if (!$coupon) {
        return [
            'code' => $resolvedCode,
            'valid' => false,
            'coupon' => null,
            'discount_amount' => 0.0,
            'shipping_discount' => 0.0,
            'total_discount' => 0.0,
            'description' => '',
            'error' => 'Cupom nao encontrado.',
        ];
    }

    $coupon = coupon_enrich_redemption_stats($coupon);
    $redemptionLimit = (int) ($coupon['redemption_limit'] ?? 0);
    $customerId = (int) ($currentCustomer['id'] ?? 0);

    if ($redemptionLimit > 0 && !coupon_customer_has_redeemed($customerId, (int) ($coupon['id'] ?? 0))) {
        return [
            'code' => $resolvedCode,
            'valid' => false,
            'coupon' => $coupon,
            'discount_amount' => 0.0,
            'shipping_discount' => 0.0,
            'total_discount' => 0.0,
            'description' => '',
            'error' => 'Resgate este cupom em Meus cupons antes de usar no fechamento.',
        ];
    }

    $evaluation = coupon_evaluate($coupon, $cart, $fulfillmentMethod, $deliveryAddress);
    $evaluation['code'] = $resolvedCode;

    return $evaluation;
}

function coupon_should_dispatch_launch_notifications(array $coupon): bool
{
    return coupon_is_active($coupon) && empty($coupon['notificado_em']);
}

function coupon_dispatch_launch_notifications(array $coupon): void
{
    if (empty($coupon['id']) || !coupon_should_dispatch_launch_notifications($coupon)) {
        return;
    }

    $title = 'Novo cupom liberado: ' . (string) ($coupon['codigo'] ?? '');
    $message = trim((string) ($coupon['descricao'] ?? '')) !== ''
        ? (string) $coupon['descricao']
        : coupon_build_description($coupon);
    $message = trim($message) !== ''
        ? $message
        : 'Confira o novo cupom disponivel na loja.';

    create_bulk_customer_notification(
        'cupom',
        $title,
        $message,
        'meus-cupons.php?cupom=' . (int) ($coupon['id'] ?? 0),
        [
            'cupom_id' => (int) ($coupon['id'] ?? 0),
            'codigo' => (string) ($coupon['codigo'] ?? ''),
        ]
    );

    $statement = db()->prepare(
        'UPDATE cupons
         SET notificado_em = NOW()
         WHERE id = :id'
    );
    $statement->execute(['id' => (int) $coupon['id']]);
}

function coupon_dispatch_reminder_notifications(array $coupon): int
{
    $couponId = (int) ($coupon['id'] ?? 0);

    if (
        $couponId <= 0
        || !coupon_is_active($coupon)
        || !table_exists('cliente_notificacoes')
        || !table_exists('clientes')
        || !table_exists('cliente_cupons')
    ) {
        return 0;
    }

    $coupon = coupon_enrich_redemption_stats($coupon);

    if (!empty($coupon['redemption_limit_reached'])) {
        return 0;
    }

    $title = 'Lembrete de cupom: ' . (string) ($coupon['codigo'] ?? '');
    $message = trim((string) ($coupon['descricao'] ?? '')) !== ''
        ? (string) ($coupon['descricao'] ?? '')
        : coupon_build_description($coupon);
    $message = trim($message) !== ''
        ? 'Voce ainda tem este cupom disponivel. ' . $message
        : 'Voce ainda tem um cupom disponivel para resgatar na loja.';

    $statement = db()->prepare(
        'INSERT INTO cliente_notificacoes (
            cliente_id, tipo, titulo, mensagem, link_url, payload_json
         )
         SELECT
            c.id,
            :tipo,
            :titulo,
            :mensagem,
            :link_url,
            :payload_json
         FROM clientes c
         WHERE c.ativo = 1
           AND NOT EXISTS (
                SELECT 1
                FROM cliente_cupons cc
                WHERE cc.cliente_id = c.id
                  AND cc.cupom_id = :cupom_id
           )'
    );
    $statement->execute([
        'tipo' => 'cupom',
        'titulo' => $title,
        'mensagem' => trim($message),
        'link_url' => 'meus-cupons.php?cupom=' . $couponId,
        'payload_json' => json_encode([
            'cupom_id' => $couponId,
            'codigo' => (string) ($coupon['codigo'] ?? ''),
            'kind' => 'reminder',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'cupom_id' => $couponId,
    ]);

    if ($statement->rowCount() > 0) {
        $update = db()->prepare(
            'UPDATE cupons
             SET notificado_em = NOW()
             WHERE id = :id'
        );
        $update->execute(['id' => $couponId]);
    }

    return $statement->rowCount();
}
