<?php
declare(strict_types=1);

function storefront_fetch_active_categories(): array
{
    return db()->query(
        'SELECT *
         FROM categorias
         WHERE ativa = 1
         ORDER BY ordem ASC, nome ASC'
    )->fetchAll();
}

function storefront_fetch_active_brands(): array
{
    return db()->query(
        'SELECT *
         FROM marcas
         WHERE ativa = 1
         ORDER BY ordem ASC, nome ASC'
    )->fetchAll();
}

function storefront_fetch_active_products(?int $categoryId = null): array
{
    $sql = 'SELECT p.*,
                   c.slug AS categoria_slug,
                   c.nome AS categoria_nome,
                   m.slug AS marca_slug,
                   m.nome AS marca_nome
            FROM produtos p
            INNER JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN marcas m ON m.id = p.marca_id
            WHERE p.ativo = 1
              AND c.ativa = 1';
    $params = [];

    if ($categoryId !== null) {
        $sql .= ' AND p.categoria_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    $sql .= ' ORDER BY c.ordem ASC, p.destaque DESC, p.nome ASC';

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function storefront_group_products_by_category(array $products): array
{
    $grouped = [];

    foreach ($products as $product) {
        $grouped[(int) $product['categoria_id']][] = $product;
    }

    return $grouped;
}

function storefront_group_products_by_brand(array $products): array
{
    $grouped = [];

    foreach ($products as $product) {
        $brandKey = (int) ($product['marca_id'] ?? 0);
        $grouped[$brandKey][] = $product;
    }

    return $grouped;
}

function storefront_sort_brand_groups(array $brands, array $productsByBrand): array
{
    $ordered = [];

    foreach ($brands as $brand) {
        $brandId = (int) ($brand['id'] ?? 0);

        if ($brandId > 0 && !empty($productsByBrand[$brandId] ?? [])) {
            $ordered[$brandId] = $productsByBrand[$brandId];
        }
    }

    if (!empty($productsByBrand[0] ?? [])) {
        $ordered[0] = $productsByBrand[0];
    }

    foreach ($productsByBrand as $brandId => $brandProducts) {
        if (!isset($ordered[$brandId])) {
            $ordered[$brandId] = $brandProducts;
        }
    }

    return $ordered;
}

function storefront_filter_visible_brands(array $brands, array $productsByBrand): array
{
    return array_values(array_filter(
        $brands,
        static fn(array $brand): bool => !empty($productsByBrand[(int) ($brand['id'] ?? 0)] ?? [])
    ));
}

function storefront_brand_url(string $slug): string
{
    return app_url('marca.php') . '?slug=' . rawurlencode($slug);
}

function storefront_product_url(string $slug): string
{
    return app_url('produto.php') . '?slug=' . rawurlencode($slug);
}

function storefront_truncate_label(string $value, int $limit = 46): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

    if ($value === '') {
        return '';
    }

    $length = function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);

    if ($length <= $limit) {
        return $value;
    }

    $sliceLength = max(1, $limit - 3);
    $slice = function_exists('mb_substr')
        ? mb_substr($value, 0, $sliceLength, 'UTF-8')
        : substr($value, 0, $sliceLength);

    $slice = preg_replace('/\s+\S*$/u', '', $slice) ?? $slice;
    $slice = rtrim($slice, " \t\n\r\0\x0B-–—,.;:/");

    if ($slice === '') {
        $slice = function_exists('mb_substr')
            ? mb_substr($value, 0, $sliceLength, 'UTF-8')
            : substr($value, 0, $sliceLength);
        $slice = rtrim($slice);
    }

    return $slice . '...';
}

function storefront_product_card_name(array $product): string
{
    $shortName = trim((string) ($product['nome_curto'] ?? ''));

    if ($shortName !== '') {
        return $shortName;
    }

    return storefront_truncate_label((string) ($product['nome'] ?? 'Produto'));
}

function storefront_filter_visible_categories(array $categories, array $productsByCategory): array
{
    if ($productsByCategory === []) {
        return array_values($categories);
    }

    return array_values(array_filter(
        $categories,
        static fn(array $category): bool => !empty($productsByCategory[(int) $category['id']] ?? [])
    ));
}

function storefront_category_url(string $slug): string
{
    return app_url('categoria.php') . '?slug=' . rawurlencode($slug);
}

function storefront_find_category_by_slug(array $categories, string $slug): ?array
{
    foreach ($categories as $category) {
        if (($category['slug'] ?? '') === $slug) {
            return $category;
        }
    }

    return null;
}

function storefront_find_brand_by_slug(array $brands, string $slug): ?array
{
    foreach ($brands as $brand) {
        if (($brand['slug'] ?? '') === $slug) {
            return $brand;
        }
    }

    return null;
}

function storefront_find_product_by_slug(string $slug): ?array
{
    $statement = db()->prepare(
        'SELECT p.*,
                c.slug AS categoria_slug,
                c.nome AS categoria_nome,
                m.slug AS marca_slug,
                m.nome AS marca_nome
         FROM produtos p
         INNER JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN marcas m ON m.id = p.marca_id
         WHERE p.slug = :slug
           AND p.ativo = 1
           AND c.ativa = 1
         LIMIT 1'
    );
    $statement->execute(['slug' => $slug]);
    $product = $statement->fetch();

    return $product ?: null;
}

function storefront_search_alias_groups(): array
{
    return [
        ['whisky', 'whiskey', 'uisque'],
        ['vodka', 'vodca'],
        ['energetico', 'energético', 'energy'],
        ['refri', 'refrigerante'],
    ];
}

function storefront_normalize_search_text(string $value): string
{
    $normalized = function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);

    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($normalized, Normalizer::FORM_D) ?: $normalized;
    }

    $normalized = preg_replace('/[\p{Mn}]+/u', '', $normalized) ?? $normalized;
    $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? $normalized;
    $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);

    foreach (storefront_search_alias_groups() as $group) {
        $canonical = $group[0] ?? '';

        if ($canonical === '') {
            continue;
        }

        foreach ($group as $alias) {
            $pattern = '/\b' . preg_quote($alias, '/') . '\b/i';
            $normalized = preg_replace($pattern, $canonical, $normalized) ?? $normalized;
        }
    }

    return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
}

function storefront_search_term_groups(string $query): array
{
    $normalized = storefront_normalize_search_text($query);

    if ($normalized === '') {
        return [];
    }

    $tokens = array_values(array_filter(explode(' ', $normalized)));
    $groups = [];

    foreach ($tokens as $token) {
        $variants = [$token];

        foreach (storefront_search_alias_groups() as $group) {
            if (in_array($token, $group, true)) {
                $variants = array_values(array_unique(array_map(
                    static fn(string $value): string => storefront_normalize_search_text($value),
                    $group
                )));
                break;
            }
        }

        $groups[] = $variants;
    }

    return $groups;
}

function storefront_search_term_prefixes(string $term): array
{
    $term = storefront_normalize_search_text($term);
    $length = strlen($term);

    if ($length < 5) {
        return [];
    }

    $prefixes = [];
    $maxLength = min($length - 1, 6);

    for ($size = $maxLength; $size >= 4; $size--) {
        $prefixes[] = substr($term, 0, $size);
    }

    return array_values(array_unique(array_filter($prefixes)));
}

function storefront_search_text_has_prefix(string $text, string $prefix): bool
{
    if ($text === '' || $prefix === '') {
        return false;
    }

    return preg_match('/\b' . preg_quote($prefix, '/') . '/i', $text) === 1;
}

function storefront_active_product_flavors_map(): array
{
    $statement = db()->query(
        'SELECT ps.produto_id, s.nome
         FROM produto_sabores ps
         INNER JOIN sabores s ON s.id = ps.sabor_id
         WHERE s.ativo = 1
         ORDER BY s.ordem ASC, s.nome ASC'
    );

    $map = [];

    foreach ($statement->fetchAll() as $row) {
        $productId = (int) ($row['produto_id'] ?? 0);
        $flavorName = trim((string) ($row['nome'] ?? ''));

        if ($productId <= 0 || $flavorName === '') {
            continue;
        }

        $map[$productId][] = $flavorName;
    }

    return $map;
}

function storefront_product_search_blob(array $product, array $flavors = []): string
{
    return trim(implode(' ', array_filter([
        (string) ($product['nome'] ?? ''),
        (string) ($product['descricao'] ?? ''),
        (string) ($product['categoria_nome'] ?? ''),
        (string) ($product['marca_nome'] ?? ''),
        implode(' ', $flavors),
    ])));
}

function storefront_search_products(string $query): array
{
    $query = trim($query);

    if ($query === '') {
        return [];
    }

    $buildMatches = static function (bool $allowPrefixFallback) use ($query): array {
        $products = storefront_fetch_active_products();
        $flavorMap = storefront_active_product_flavors_map();
        $termGroups = storefront_search_term_groups($query);
        $normalizedQuery = storefront_normalize_search_text($query);
        $matches = [];

        foreach ($products as $product) {
            $productId = (int) ($product['id'] ?? 0);
            $flavors = $flavorMap[$productId] ?? [];
            $normalizedName = storefront_normalize_search_text((string) ($product['nome'] ?? ''));
            $normalizedDescription = storefront_normalize_search_text((string) ($product['descricao'] ?? ''));
            $normalizedMeta = storefront_normalize_search_text(implode(' ', array_filter([
                (string) ($product['categoria_nome'] ?? ''),
                (string) ($product['marca_nome'] ?? ''),
                implode(' ', $flavors),
            ])));
            $normalizedBlob = storefront_normalize_search_text(storefront_product_search_blob($product, $flavors));
            $score = 0;
            $allTermsMatched = true;

            foreach ($termGroups as $variants) {
                $matchedVariant = false;

                foreach ($variants as $variant) {
                    if ($variant === '') {
                        continue;
                    }

                    if (str_contains($normalizedName, $variant)) {
                        $score += 12;
                        $matchedVariant = true;
                        break;
                    }

                    if (str_contains($normalizedDescription, $variant)) {
                        $score += 7;
                        $matchedVariant = true;
                        break;
                    }

                    if (str_contains($normalizedMeta, $variant)) {
                        $score += 5;
                        $matchedVariant = true;
                        break;
                    }
                }

                if (!$matchedVariant && $allowPrefixFallback) {
                    foreach ($variants as $variant) {
                        foreach (storefront_search_term_prefixes($variant) as $prefix) {
                            if (storefront_search_text_has_prefix($normalizedName, $prefix)) {
                                $score += 6;
                                $matchedVariant = true;
                                break 2;
                            }

                            if (storefront_search_text_has_prefix($normalizedDescription, $prefix)) {
                                $score += 4;
                                $matchedVariant = true;
                                break 2;
                            }

                            if (storefront_search_text_has_prefix($normalizedMeta, $prefix)) {
                                $score += 3;
                                $matchedVariant = true;
                                break 2;
                            }
                        }
                    }
                }

                if (!$matchedVariant) {
                    $allTermsMatched = false;
                    break;
                }
            }

            if (!$allTermsMatched) {
                continue;
            }

            if ($normalizedQuery !== '') {
                if ($normalizedName === $normalizedQuery) {
                    $score += 120;
                } elseif (str_contains($normalizedName, $normalizedQuery)) {
                    $score += 50;
                } elseif (str_contains($normalizedBlob, $normalizedQuery)) {
                    $score += 18;
                } elseif ($allowPrefixFallback) {
                    foreach (storefront_search_term_prefixes($normalizedQuery) as $prefix) {
                        if (storefront_search_text_has_prefix($normalizedName, $prefix)) {
                            $score += 12;
                            break;
                        }

                        if (storefront_search_text_has_prefix($normalizedBlob, $prefix)) {
                            $score += 6;
                            break;
                        }
                    }
                }
            }

            if ((int) ($product['destaque'] ?? 0) === 1) {
                $score += 3;
            }

            $product['__search_score'] = $score;
            $matches[] = $product;
        }

        usort($matches, static function (array $left, array $right): int {
            $scoreCompare = ((int) ($right['__search_score'] ?? 0)) <=> ((int) ($left['__search_score'] ?? 0));

            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strcasecmp((string) ($left['nome'] ?? ''), (string) ($right['nome'] ?? ''));
        });

        return array_map(static function (array $product): array {
            unset($product['__search_score']);
            return $product;
        }, $matches);
    };

    $matches = $buildMatches(false);

    if ($matches === []) {
        $matches = $buildMatches(true);
    }

    return $matches;
}

function storefront_announcement_text(array $storeSettings): string
{
    $items = [
        'Entregas para ' . storefront_delivery_supported_locations_text(' e '),
        'Frete: ' . storefront_delivery_fee_breakdown_text(),
        storefront_delivery_eta_text(),
    ];

    if (!empty($storeSettings['telefone_whatsapp'])) {
        $items[] = 'WhatsApp: ' . format_phone($storeSettings['telefone_whatsapp']);
    }

    return implode(' | ', $items);
}

function storefront_store_whatsapp_link(array $storeSettings, ?array $currentCustomer): string
{
    $message = $currentCustomer
        ? 'Ola! Sou ' . $currentCustomer['nome'] . ' e quero fazer um pedido em ' . ($storeSettings['nome_estabelecimento'] ?? 'sua loja') . '.'
        : 'Ola! Quero fazer um pedido em ' . ($storeSettings['nome_estabelecimento'] ?? 'sua loja') . '.';

    return whatsapp_link($storeSettings['telefone_whatsapp'] ?? '', $message);
}

function storefront_product_whatsapp_link(array $storeSettings, ?array $currentCustomer, array $product, array $category): string
{
    $message = $currentCustomer
        ? sprintf(
            'Ola! Sou %s e quero pedir o item %s da categoria %s.',
            $currentCustomer['nome'],
            $product['nome'],
            $category['nome']
        )
        : sprintf(
            'Ola! Quero pedir o item %s da categoria %s.',
            $product['nome'],
            $category['nome']
        );

    return whatsapp_link($storeSettings['telefone_whatsapp'] ?? '', $message);
}

function storefront_delivery_city(): string
{
    return 'Volta Redonda';
}

function storefront_delivery_state(): string
{
    return 'RJ';
}

function storefront_delivery_areas(): array
{
    return [
        [
            'city' => 'Volta Redonda',
            'state' => 'RJ',
            'fee' => 15.00,
        ],
        [
            'city' => 'Barra Mansa',
            'state' => 'RJ',
            'fee' => 30.00,
        ],
    ];
}

function storefront_delivery_area_label(array $area): string
{
    return trim((string) ($area['city'] ?? '')) . '-' . strtoupper(trim((string) ($area['state'] ?? '')));
}

function storefront_delivery_supported_locations_text(string $glue = ' e '): string
{
    $labels = array_map(
        static fn(array $area): string => storefront_delivery_area_label($area),
        storefront_delivery_areas()
    );

    if ($labels === []) {
        return '';
    }

    if (count($labels) === 1) {
        return $labels[0];
    }

    $last = array_pop($labels);

    return implode(', ', $labels) . $glue . $last;
}

function storefront_delivery_fee_breakdown_text(): string
{
    $parts = array_map(
        static fn(array $area): string => storefront_delivery_area_label($area) . ' por ' . format_currency((float) ($area['fee'] ?? 0)),
        storefront_delivery_areas()
    );

    if ($parts === []) {
        return '';
    }

    if (count($parts) === 1) {
        return $parts[0];
    }

    $last = array_pop($parts);

    return implode(', ', $parts) . ' e ' . $last;
}

function storefront_resolve_delivery_area(?array $address): ?array
{
    if (!$address) {
        return null;
    }

    $city = storefront_normalize_flavor((string) ($address['cidade'] ?? ''));
    $state = strtoupper(trim((string) ($address['uf'] ?? '')));

    foreach (storefront_delivery_areas() as $area) {
        if (
            $city === storefront_normalize_flavor((string) ($area['city'] ?? ''))
            && $state === strtoupper(trim((string) ($area['state'] ?? '')))
        ) {
            return $area;
        }
    }

    return null;
}

function storefront_delivery_fee(?array $address = null): float
{
    $area = storefront_resolve_delivery_area($address);

    if ($area) {
        return (float) ($area['fee'] ?? 0);
    }

    $areas = storefront_delivery_areas();

    return (float) ($areas[0]['fee'] ?? 0);
}

function storefront_delivery_eta_text(): string
{
    return 'Entrega em ate 3h apos pagamento confirmado.';
}

function storefront_delivery_notice_text(): string
{
    return sprintf(
        'Entregas disponiveis para %s | %s | %s',
        storefront_delivery_supported_locations_text(' e '),
        storefront_delivery_fee_breakdown_text(),
        storefront_delivery_eta_text()
    );
}

function storefront_manual_pix_key(): string
{
    return '24998592033';
}

function storefront_manual_pix_name(): string
{
    return 'Lucas Nogueira De Assis';
}

function storefront_manual_pix_whatsapp_number(): string
{
    return '5524998592033';
}

function storefront_manual_pix_whatsapp_label(): string
{
    return '24998592033';
}

function storefront_manual_pix_notice_text(): string
{
    return 'Apos o pagamento via Pix, envie o comprovante pelo WhatsApp para dar prosseguimento ao pedido.';
}

function storefront_manual_pix_qr_code_url(): string
{
    return '/qrcode.jpeg';
}

function storefront_manual_pix_copy_paste_code(): string
{
    return '00020126360014BR.GOV.BCB.PIX0114+55249985920335204000053039865802BR5923LUCAS NOGUEIRA DE ASSIS6009SAO PAULO622605225EcN4fJ4rprrsUZal1h2yy6304860B';
}

function storefront_manual_pix_receipt_message(?array $order = null): string
{
    $lines = [
        'Ola! Estou enviando o comprovante do Pix do meu pedido.',
    ];

    if (is_array($order) && !empty($order['codigo_rastreio'])) {
        $lines[] = 'Codigo do pedido: *' . (string) $order['codigo_rastreio'] . '*';
    }

    if (is_array($order) && !empty($order['nome_cliente'])) {
        $lines[] = 'Cliente: ' . (string) $order['nome_cliente'];
    }

    if (is_array($order) && isset($order['total'])) {
        $lines[] = 'Total: ' . format_currency((float) $order['total']);
    }

    $lines[] = '';
    $lines[] = 'Segue o comprovante para dar prosseguimento ao pedido.';

    return implode("\n", $lines);
}

function storefront_manual_pix_receipt_link(?array $order = null): string
{
    return whatsapp_link(
        storefront_manual_pix_whatsapp_number(),
        storefront_manual_pix_receipt_message($order)
    );
}

function storefront_order_support_message(array $order): string
{
    $status = order_normalize_status((string) ($order['status'] ?? 'pending'));
    $label = $status === 'rejected' ? 'recusado' : ($status === 'cancelled' ? 'cancelado' : 'em andamento');
    $lines = [
        'Ola! Tenho duvidas sobre meu pedido.',
        'Codigo do pedido: ' . (string) ($order['codigo_rastreio'] ?? ''),
        'Status atual: ' . $label,
    ];

    if (!empty($order['nome_cliente'])) {
        $lines[] = 'Cliente: ' . (string) $order['nome_cliente'];
    }

    return implode("\n", $lines);
}

function storefront_order_support_link(array $storeSettings, array $order): string
{
    $phone = digits_only((string) ($storeSettings['telefone_whatsapp'] ?? ''));

    if ($phone === '') {
        $phone = storefront_manual_pix_whatsapp_number();
    }

    return whatsapp_link($phone, storefront_order_support_message($order));
}

function storefront_checkout_payment_options(): array
{
    return [
        'pix' => 'Pix online',
        'online_card' => 'Cartao online',
        'card' => 'Cartao na entrega',
        'cash' => 'Dinheiro na entrega',
    ];
}

function storefront_checkout_payment_display_label(?string $paymentMethod, ?string $fulfillmentMethod = 'delivery'): string
{
    $payment = storefront_normalize_checkout_payment($paymentMethod);
    $pickup = storefront_normalize_checkout_method($fulfillmentMethod) === 'pickup';

    return match ($payment) {
        'pix' => 'Pix online',
        'online_card' => 'Cartao online',
        'card' => $pickup ? 'Cartao na retirada' : 'Cartao na entrega',
        'cash' => $pickup ? 'Dinheiro na retirada' : 'Dinheiro na entrega',
        default => $pickup ? 'Pagamento na retirada' : storefront_checkout_payment_label($payment),
    };
}

function storefront_checkout_payment_scope(?string $value): string
{
    $payment = storefront_normalize_checkout_payment($value);

    if ($payment === '') {
        return '';
    }

    return in_array($payment, ['pix', 'online_card'], true)
        ? 'online'
        : 'on_delivery';
}

function storefront_resolve_checkout_payment_input(
    ?string $paymentScope,
    ?string $paymentMethod,
    ?string $deliveryPaymentMethod = null,
    ?string $onlinePaymentMethod = null
): string {
    $scope = strtolower(trim((string) $paymentScope));

    if ($scope === 'online') {
        $resolvedOnline = storefront_normalize_checkout_payment($onlinePaymentMethod);

        return in_array($resolvedOnline, ['pix', 'online_card'], true)
            ? $resolvedOnline
            : '';
    }

    if ($scope === 'on_delivery') {
        $resolvedDelivery = storefront_normalize_checkout_payment($deliveryPaymentMethod);

        return in_array($resolvedDelivery, ['card', 'cash'], true)
            ? $resolvedDelivery
            : '';
    }

    $resolved = storefront_normalize_checkout_payment($paymentMethod);

    return in_array($resolved, ['pix', 'online_card'], true)
        ? $resolved
        : (in_array($resolved, ['card', 'cash'], true) ? $resolved : '');
}

function storefront_normalize_checkout_cash_change_for(mixed $value): ?float
{
    if ($value === null) {
        return null;
    }

    $raw = trim((string) $value);

    if ($raw === '') {
        return null;
    }

    $amount = normalize_money_input($raw);

    return $amount > 0 ? $amount : null;
}

function storefront_normalize_checkout_cash_change_choice(mixed $value): ?string
{
    $choice = strtolower(trim((string) $value));

    return in_array($choice, ['change', 'exact'], true) ? $choice : null;
}

function storefront_normalize_checkout_method(?string $value): string
{
    return strtolower(trim((string) $value)) === 'pickup'
        ? 'pickup'
        : 'delivery';
}

function storefront_normalize_checkout_payment(?string $value): string
{
    $payment = strtolower(trim((string) $value));

    if ($payment === '') {
        return '';
    }

    if (in_array($payment, ['debit', 'credit'], true)) {
        $payment = 'card';
    }

    if (in_array($payment, ['money', 'dinheiro'], true)) {
        $payment = 'cash';
    }

    if ($payment === 'online') {
        $payment = 'pix';
    }

    if (in_array($payment, ['credit_card_online', 'online-credit-card', 'cartao_online', 'cartao-online'], true)) {
        $payment = 'online_card';
    }

    $allowed = array_keys(storefront_checkout_payment_options());

    return in_array($payment, $allowed, true) ? $payment : '';
}

function storefront_checkout_payment_label(?string $value): string
{
    $payment = storefront_normalize_checkout_payment($value);
    $options = storefront_checkout_payment_options();

    return $options[$payment] ?? 'Pagamento';
}

function storefront_checkout_session_selection(): array
{
    $raw = $_SESSION['storefront_checkout'] ?? [];

    if (!is_array($raw)) {
        return [];
    }

    return [
        'fulfillment_method' => storefront_normalize_checkout_method((string) ($raw['fulfillment_method'] ?? 'delivery')),
        'payment_method' => storefront_normalize_checkout_payment((string) ($raw['payment_method'] ?? '')),
        'cash_change_for' => storefront_normalize_checkout_cash_change_for($raw['cash_change_for'] ?? null),
        'cash_change_choice' => storefront_normalize_checkout_cash_change_choice($raw['cash_change_choice'] ?? null),
    ];
}

function storefront_checkout_preview_tracking_code(): string
{
    $raw = $_SESSION['storefront_checkout'] ?? [];
    if (is_array($raw)) {
        $savedCode = order_normalize_tracking_code((string) ($raw['preview_tracking_code'] ?? ''));
        if ($savedCode !== '') {
            return $savedCode;
        }
    }

    $trackingCode = order_generate_unique_tracking_code();
    if (!isset($_SESSION['storefront_checkout']) || !is_array($_SESSION['storefront_checkout'])) {
        $_SESSION['storefront_checkout'] = [];
    }

    $_SESSION['storefront_checkout']['preview_tracking_code'] = $trackingCode;

    return $trackingCode;
}

function storefront_save_checkout_selection(
    string $fulfillmentMethod,
    ?string $paymentMethod = null,
    mixed $cashChangeFor = null,
    mixed $cashChangeChoice = null
): void
{
    $resolvedMethod = storefront_normalize_checkout_method($fulfillmentMethod);
    $resolvedPayment = storefront_normalize_checkout_payment((string) $paymentMethod);
    $resolvedCashChangeChoice = $resolvedPayment === 'cash'
        ? storefront_normalize_checkout_cash_change_choice($cashChangeChoice)
        : null;
    $existing = $_SESSION['storefront_checkout'] ?? [];

    if (!is_array($existing)) {
        $existing = [];
    }

    $_SESSION['storefront_checkout'] = array_merge($existing, [
        'fulfillment_method' => $resolvedMethod,
        'payment_method' => $resolvedPayment,
        'cash_change_for' => $resolvedPayment === 'cash'
            ? storefront_normalize_checkout_cash_change_for($cashChangeFor)
            : null,
        'cash_change_choice' => $resolvedCashChangeChoice,
    ]);
}

function storefront_default_checkout_method(?array $currentCustomer): string
{
    $deliveryAddress = storefront_customer_delivery_address($currentCustomer);

    return storefront_address_supports_delivery($deliveryAddress)
        ? 'delivery'
        : 'pickup';
}

function storefront_current_checkout_selection(
    ?array $currentCustomer,
    ?string $fulfillmentMethod = null,
    ?string $paymentMethod = null,
    mixed $cashChangeFor = null,
    mixed $cashChangeChoice = null
): array {
    $saved = storefront_checkout_session_selection();
    $resolvedMethod = $fulfillmentMethod !== null && $fulfillmentMethod !== ''
        ? storefront_normalize_checkout_method($fulfillmentMethod)
        : (string) ($saved['fulfillment_method'] ?? storefront_default_checkout_method($currentCustomer));

    $resolvedPayment = $paymentMethod !== null && $paymentMethod !== ''
        ? storefront_normalize_checkout_payment($paymentMethod)
        : '';
    $resolvedCashChangeFor = $cashChangeFor !== null
        ? storefront_normalize_checkout_cash_change_for($cashChangeFor)
        : null;
    $resolvedCashChangeChoice = $cashChangeChoice !== null
        ? storefront_normalize_checkout_cash_change_choice($cashChangeChoice)
        : null;

    if ($resolvedPayment !== 'cash') {
        $resolvedCashChangeFor = null;
        $resolvedCashChangeChoice = null;
    }

    return [
        'fulfillment_method' => $resolvedMethod,
        'payment_method' => $resolvedPayment,
        'cash_change_for' => $resolvedCashChangeFor,
        'cash_change_choice' => $resolvedCashChangeChoice,
    ];
}

function storefront_normalize_flavor(?string $value): string
{
    $normalized = trim((string) $value);

    if ($normalized === '') {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($normalized, 'UTF-8')
        : strtolower($normalized);
}

function storefront_customer_can_use_cart(?array $customer = null): bool
{
    $customer ??= current_customer();

    return $customer !== null
        && (int) ($customer['ativo'] ?? 0) === 1
        && !empty($customer['email_verificado_em']);
}

function storefront_cart_storage_table(): string
{
    return 'cliente_carrinho_itens';
}

function storefront_ensure_customer_cart_storage(): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    db()->exec(
        'CREATE TABLE IF NOT EXISTS ' . storefront_cart_storage_table() . ' (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cliente_id INT UNSIGNED NOT NULL,
            item_key CHAR(40) NOT NULL,
            produto_id INT UNSIGNED NOT NULL,
            sabor VARCHAR(190) NULL,
            quantidade INT UNSIGNED NOT NULL DEFAULT 1,
            selecionado TINYINT(1) NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_cliente_carrinho_item (cliente_id, item_key),
            KEY idx_cliente_carrinho_cliente (cliente_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured = true;
}

function storefront_cart_legacy_session_items(): array
{
    $items = $_SESSION['storefront_cart'] ?? [];

    return is_array($items) ? $items : [];
}

function storefront_cart_forget_legacy_session(): void
{
    unset($_SESSION['storefront_cart']);
}

function storefront_cart_customer_id(?array $customer = null): int
{
    $customer ??= current_customer();

    if (!storefront_customer_can_use_cart($customer)) {
        return 0;
    }

    return max(0, (int) ($customer['id'] ?? 0));
}

function storefront_cart_key(int $productId, ?string $flavor = null): string
{
    return sha1($productId . '|' . storefront_normalize_flavor($flavor));
}

function storefront_cart_normalize_items(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $productId = max(0, (int) ($item['product_id'] ?? 0));

        if ($productId <= 0) {
            continue;
        }

        $flavor = trim((string) ($item['flavor'] ?? ''));
        $itemKey = storefront_cart_key($productId, $flavor !== '' ? $flavor : null);
        $quantity = max(0, (int) ($item['quantity'] ?? 0));

        if ($quantity <= 0) {
            continue;
        }

        $selected = storefront_cart_item_selected($item['selected'] ?? null);

        if (isset($normalized[$itemKey])) {
            $normalized[$itemKey]['quantity'] += $quantity;
            $normalized[$itemKey]['selected'] = (!empty($normalized[$itemKey]['selected']) || $selected) ? 1 : 0;
            continue;
        }

        $normalized[$itemKey] = [
            'product_id' => $productId,
            'flavor' => $flavor !== '' ? $flavor : null,
            'quantity' => $quantity,
            'selected' => $selected ? 1 : 0,
        ];
    }

    return $normalized;
}

function storefront_customer_cart_fetch_items(int $customerId): array
{
    if ($customerId <= 0) {
        return [];
    }

    storefront_ensure_customer_cart_storage();

    $statement = db()->prepare(
        'SELECT item_key, produto_id, sabor, quantidade, selecionado
         FROM ' . storefront_cart_storage_table() . '
         WHERE cliente_id = :cliente_id
         ORDER BY id ASC'
    );
    $statement->execute(['cliente_id' => $customerId]);

    $items = [];

    foreach ($statement->fetchAll() as $row) {
        $itemKey = trim((string) ($row['item_key'] ?? ''));

        if ($itemKey === '') {
            continue;
        }

        $items[$itemKey] = [
            'product_id' => max(0, (int) ($row['produto_id'] ?? 0)),
            'flavor' => ($row['sabor'] ?? null) !== null && trim((string) $row['sabor']) !== ''
                ? (string) $row['sabor']
                : null,
            'quantity' => max(0, (int) ($row['quantidade'] ?? 0)),
            'selected' => storefront_cart_item_selected($row['selecionado'] ?? null) ? 1 : 0,
        ];
    }

    return storefront_cart_normalize_items($items);
}

function storefront_customer_cart_write_items(int $customerId, array $items): void
{
    if ($customerId <= 0) {
        return;
    }

    storefront_ensure_customer_cart_storage();

    $normalized = storefront_cart_normalize_items($items);
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $delete = $pdo->prepare('DELETE FROM ' . storefront_cart_storage_table() . ' WHERE cliente_id = :cliente_id');
        $delete->execute(['cliente_id' => $customerId]);

        if ($normalized !== []) {
            $insert = $pdo->prepare(
                'INSERT INTO ' . storefront_cart_storage_table() . ' (
                    cliente_id, item_key, produto_id, sabor, quantidade, selecionado, criado_em, atualizado_em
                 ) VALUES (
                    :cliente_id, :item_key, :produto_id, :sabor, :quantidade, :selecionado, NOW(), NOW()
                 )'
            );

            foreach ($normalized as $itemKey => $item) {
                $insert->execute([
                    'cliente_id' => $customerId,
                    'item_key' => $itemKey,
                    'produto_id' => max(0, (int) ($item['product_id'] ?? 0)),
                    'sabor' => ($item['flavor'] ?? null) !== null && trim((string) ($item['flavor'] ?? '')) !== ''
                        ? (string) $item['flavor']
                        : null,
                    'quantidade' => max(1, (int) ($item['quantity'] ?? 1)),
                    'selecionado' => storefront_cart_item_selected($item['selected'] ?? null) ? 1 : 0,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function storefront_cart_session_items(): array
{
    if (!storefront_customer_can_use_cart()) {
        return [];
    }

    $customerId = storefront_cart_customer_id();
    $legacyItems = storefront_cart_normalize_items(storefront_cart_legacy_session_items());

    if ($customerId <= 0) {
        return $legacyItems;
    }

    $databaseItems = storefront_customer_cart_fetch_items($customerId);

    if ($legacyItems === []) {
        return $databaseItems;
    }

    $mergedItems = storefront_cart_normalize_items(array_merge(
        array_values($databaseItems),
        array_values($legacyItems)
    ));

    storefront_customer_cart_write_items($customerId, $mergedItems);
    storefront_cart_forget_legacy_session();

    return $mergedItems;
}

function storefront_cart_write(array $items): void
{
    $normalized = storefront_cart_normalize_items($items);
    $customerId = storefront_cart_customer_id();

    if ($customerId > 0) {
        storefront_customer_cart_write_items($customerId, $normalized);
        storefront_cart_forget_legacy_session();
        return;
    }

    $_SESSION['storefront_cart'] = $normalized;
}

function storefront_product_available_flavor_entries(array $product): array

{
    $productId = (int) ($product['id'] ?? 0);

    if ($productId <= 0) {
        return [];
    }

    $entries = array_values(array_filter(
        fetch_product_flavors($productId, false),
        static fn(array $flavor): bool => (int) ($flavor['ativo'] ?? 1) === 1
            && max(0, (int) ($flavor['estoque'] ?? 0)) > 0
    ));

    if ($entries !== []) {
        return array_map(
            static fn(array $flavor): array => [
                'nome' => (string) ($flavor['nome'] ?? ''),
                'estoque' => max(0, (int) ($flavor['estoque'] ?? 0)),
            ],
            $entries
        );
    }

    return parse_product_flavor_entries($product['sabores'] ?? null);
}

function storefront_product_available_flavors(array $product): array
{
    return array_values(array_map(
        static fn(array $entry): string => (string) ($entry['nome'] ?? ''),
        storefront_product_available_flavor_entries($product)
    ));
}

function storefront_resolve_product_stock(array $product, ?string $flavor = null): int
{
    $normalizedFlavor = storefront_normalize_flavor($flavor);

    if ($normalizedFlavor !== '') {
        foreach (storefront_product_available_flavor_entries($product) as $entry) {
            if (storefront_normalize_flavor((string) ($entry['nome'] ?? '')) === $normalizedFlavor) {
                return max(0, (int) ($entry['estoque'] ?? 0));
            }
        }

        return 0;
    }

    return max(0, (int) ($product['estoque'] ?? 0));
}

function storefront_cart_count(): int
{
    $count = 0;

    foreach (storefront_cart_session_items() as $item) {
        if (!is_array($item)) {
            continue;
        }

        $count += max(0, (int) ($item['quantity'] ?? 0));
    }

    return $count;
}

function storefront_fetch_products_by_ids(array $productIds): array
{
    $ids = array_values(array_unique(array_filter(
        array_map(static fn(mixed $value): int => (int) $value, $productIds),
        static fn(int $value): bool => $value > 0
    )));

    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = db()->prepare(
        "SELECT p.*,
                c.slug AS categoria_slug,
                c.nome AS categoria_nome,
                m.slug AS marca_slug,
                m.nome AS marca_nome
         FROM produtos p
         INNER JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN marcas m ON m.id = p.marca_id
         WHERE p.ativo = 1
           AND c.ativa = 1
           AND p.id IN ({$placeholders})"
    );
    $statement->execute($ids);

    $products = [];

    foreach ($statement->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }

    return $products;
}

function storefront_cart_add_item(array $product, ?string $flavor = null, int $quantity = 1): array
{
    if (!storefront_customer_can_use_cart()) {
        return [
            'success' => false,
            'reason' => 'auth_required',
            'message' => 'Entre ou crie sua conta para usar o carrinho.',
            'login_url' => app_url('entrar.php'),
        ];
    }

    $productId = (int) ($product['id'] ?? 0);

    if ($productId <= 0) {
        return ['success' => false, 'message' => 'Produto invalido.'];
    }

    $availableFlavors = storefront_product_available_flavor_entries($product);
    $selectedFlavor = trim((string) $flavor);

    if ($availableFlavors !== []) {
        if ($selectedFlavor === '' && count($availableFlavors) === 1) {
            $selectedFlavor = (string) $availableFlavors[0]['nome'];
        }

        if ($selectedFlavor === '') {
            return ['success' => false, 'message' => 'Selecione um tamanho para adicionar ao carrinho.'];
        }

        $selectedFlavor = array_values(array_filter(
            array_map(static fn(array $entry): string => (string) ($entry['nome'] ?? ''), $availableFlavors),
            static fn(string $name): bool => storefront_normalize_flavor($name) === storefront_normalize_flavor($selectedFlavor)
        ))[0] ?? '';

        if ($selectedFlavor === '') {
            return ['success' => false, 'message' => 'O tamanho selecionado nao esta disponivel.'];
        }
    } else {
        $selectedFlavor = '';
    }

    $maxQuantity = storefront_resolve_product_stock($product, $selectedFlavor !== '' ? $selectedFlavor : null);

    if ($maxQuantity <= 0) {
        return ['success' => false, 'message' => 'Esse item esta sem estoque no momento.'];
    }

    $items = storefront_cart_session_items();
    $key = storefront_cart_key($productId, $selectedFlavor);
    $existingQuantity = max(0, (int) ($items[$key]['quantity'] ?? 0));
    $desiredQuantity = max(1, $quantity);
    $finalQuantity = min($existingQuantity + $desiredQuantity, $maxQuantity);

    $items[$key] = [
        'product_id' => $productId,
        'flavor' => $selectedFlavor !== '' ? $selectedFlavor : null,
        'quantity' => $finalQuantity,
        'selected' => 1,
    ];

    storefront_cart_write($items);

    return [
        'success' => true,
        'message' => $finalQuantity > $existingQuantity + $desiredQuantity - 1
            ? 'Item adicionado ao carrinho.'
            : 'Carrinho atualizado com o limite disponivel em estoque.',
    ];
}

function storefront_cart_item_selected(mixed $value): bool
{
    if ($value === null) {
        return true;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }

    $normalized = strtolower(trim((string) $value));

    return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
}

function storefront_cart_remove_item(string $key): void
{
    $items = storefront_cart_session_items();
    unset($items[$key]);
    storefront_cart_write($items);
}

function storefront_cart_clear(): void
{
    storefront_cart_write([]);
    storefront_clear_coupon_code();
}

function storefront_cart_remove_items(array $keys): void
{
    if ($keys === []) {
        return;
    }

    $items = storefront_cart_session_items();

    foreach ($keys as $key) {
        unset($items[(string) $key]);
    }

    if ($items === []) {
        storefront_cart_clear();
        return;
    }

    storefront_cart_write($items);
}

function storefront_cart_update_item(string $key, int $quantity): void
{
    $items = storefront_cart_session_items();

    if (!isset($items[$key]) || !is_array($items[$key])) {
        return;
    }

    if ($quantity <= 0) {
        unset($items[$key]);
        storefront_cart_write($items);
        return;
    }

    $products = storefront_fetch_products_by_ids([(int) ($items[$key]['product_id'] ?? 0)]);
    $productId = (int) ($items[$key]['product_id'] ?? 0);
    $product = $products[$productId] ?? null;

    if ($product === null) {
        unset($items[$key]);
        storefront_cart_write($items);
        return;
    }

    $flavor = (string) ($items[$key]['flavor'] ?? '');
    $maxQuantity = storefront_resolve_product_stock($product, $flavor !== '' ? $flavor : null);

    if ($maxQuantity <= 0) {
        unset($items[$key]);
        storefront_cart_write($items);
        return;
    }

    $items[$key]['quantity'] = min(max(1, $quantity), $maxQuantity);
    storefront_cart_write($items);
}

function storefront_cart_set_item_selected(string $key, bool $selected): void
{
    $items = storefront_cart_session_items();

    if (!isset($items[$key]) || !is_array($items[$key])) {
        return;
    }

    $items[$key]['selected'] = $selected ? 1 : 0;
    storefront_cart_write($items);
}

function storefront_cart_select_all_items(bool $selected): void
{
    $items = storefront_cart_session_items();

    if ($items === []) {
        return;
    }

    foreach ($items as $key => $item) {
        if (!is_array($item)) {
            continue;
        }

        $items[$key]['selected'] = $selected ? 1 : 0;
    }

    storefront_cart_write($items);
}

function storefront_build_cart(): array
{
    $rawItems = storefront_cart_session_items();
    $products = storefront_fetch_products_by_ids(array_map(
        static fn(array $item): int => (int) ($item['product_id'] ?? 0),
        array_values(array_filter($rawItems, 'is_array'))
    ));
    $items = [];
    $selectedItems = [];
    $subtotal = 0.0;
    $selectedSubtotal = 0.0;
    $count = 0;
    $selectedCount = 0;
    $dirty = false;

    foreach ($rawItems as $key => $item) {
        if (!is_array($item)) {
            $dirty = true;
            continue;
        }

        $productId = (int) ($item['product_id'] ?? 0);
        $product = $products[$productId] ?? null;

        if ($product === null) {
            unset($rawItems[$key]);
            $dirty = true;
            continue;
        }

        $selectedFlavor = trim((string) ($item['flavor'] ?? ''));
        $availableFlavors = storefront_product_available_flavor_entries($product);

        if ($availableFlavors !== [] && $selectedFlavor === '' && count($availableFlavors) === 1) {
            $selectedFlavor = (string) $availableFlavors[0]['nome'];
            $rawItems[$key]['flavor'] = $selectedFlavor;
            $dirty = true;
        }

        $maxQuantity = storefront_resolve_product_stock($product, $selectedFlavor !== '' ? $selectedFlavor : null);
        $quantity = max(0, (int) ($item['quantity'] ?? 0));

        if ($maxQuantity <= 0 || $quantity <= 0) {
            unset($rawItems[$key]);
            $dirty = true;
            continue;
        }

        if ($quantity > $maxQuantity) {
            $quantity = $maxQuantity;
            $rawItems[$key]['quantity'] = $quantity;
            $dirty = true;
        }

        $selected = storefront_cart_item_selected($item['selected'] ?? null);

        if (!array_key_exists('selected', $item)) {
            $rawItems[$key]['selected'] = $selected ? 1 : 0;
            $dirty = true;
        }

        $unitPrice = product_final_price($product);
        $lineTotal = $unitPrice * $quantity;
        $subtotal += $lineTotal;
        $count += $quantity;

        $builtItem = [
            'key' => $key,
            'product' => $product,
            'quantity' => $quantity,
            'flavor' => $selectedFlavor !== '' ? $selectedFlavor : null,
            'unit_price' => $unitPrice,
            'unit_original_price' => product_original_price($product),
            'line_total' => $lineTotal,
            'max_quantity' => $maxQuantity,
            'selected' => $selected,
        ];
        $items[] = $builtItem;

        if ($selected) {
            $selectedItems[] = $builtItem;
            $selectedSubtotal += $lineTotal;
            $selectedCount += $quantity;
        }
    }

    if ($dirty) {
        storefront_cart_write($rawItems);
    }

    if ($items === []) {
        storefront_clear_coupon_code();
    }

    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'count' => $count,
        'selected_items' => $selectedItems,
        'selected_subtotal' => $selectedSubtotal,
        'selected_count' => $selectedCount,
        'selected_empty' => $selectedItems === [],
        'all_selected' => $items !== [] && count($selectedItems) === count($items),
    ];
}

function storefront_selected_cart(array $cart): array
{
    $selectedItems = array_values($cart['selected_items'] ?? []);

    return [
        'items' => $selectedItems,
        'subtotal' => (float) ($cart['selected_subtotal'] ?? 0),
        'count' => (int) ($cart['selected_count'] ?? 0),
        'selected_items' => $selectedItems,
        'selected_subtotal' => (float) ($cart['selected_subtotal'] ?? 0),
        'selected_count' => (int) ($cart['selected_count'] ?? 0),
        'selected_empty' => $selectedItems === [],
        'all_selected' => !empty($cart['all_selected']),
    ];
}

function storefront_customer_delivery_address(?array $currentCustomer): ?array
{
    if (!$currentCustomer || empty($currentCustomer['id'])) {
        return null;
    }

    $addresses = fetch_customer_addresses((int) $currentCustomer['id']);

    if ($addresses === []) {
        return null;
    }

    $deliverableAddresses = array_values(array_filter(
        $addresses,
        static fn(array $address): bool => storefront_address_supports_delivery($address)
    ));

    if ($deliverableAddresses !== []) {
        foreach ($deliverableAddresses as $address) {
            if ((int) ($address['principal'] ?? 0) === 1) {
                return $address;
            }
        }

        return $deliverableAddresses[0];
    }

    foreach ($addresses as $address) {
        if ((int) ($address['principal'] ?? 0) === 1) {
            return $address;
        }
    }

    return $addresses[0];
}

function storefront_address_supports_delivery(?array $address): bool
{
    return storefront_resolve_delivery_area($address) !== null;
}

function storefront_build_cart_checkout(
    array $storeSettings,
    ?array $currentCustomer,
    array $cart,
    ?string $fulfillmentMethod = null,
    ?string $paymentMethod = null,
    mixed $cashChangeFor = null,
    ?string $couponCode = null
): array {
    $selection = storefront_current_checkout_selection($currentCustomer, $fulfillmentMethod, $paymentMethod, $cashChangeFor);
    $deliveryAddress = storefront_customer_delivery_address($currentCustomer);
    $deliverySupported = storefront_address_supports_delivery($deliveryAddress);
    $isDelivery = $selection['fulfillment_method'] === 'delivery';
    $baseDeliveryFee = $isDelivery ? storefront_delivery_fee($deliveryAddress) : 0.0;
    $couponApplication = storefront_resolve_coupon_application(
        $cart,
        $selection['fulfillment_method'],
        $couponCode,
        $currentCustomer,
        $deliveryAddress
    );
    $couponDiscount = !empty($couponApplication['valid'])
        ? max(0.0, (float) ($couponApplication['discount_amount'] ?? 0))
        : 0.0;
    $shippingDiscount = !empty($couponApplication['valid']) && $isDelivery
        ? max(0.0, (float) ($couponApplication['shipping_discount'] ?? 0))
        : 0.0;
    $deliveryFee = max(0.0, $baseDeliveryFee - $shippingDiscount);
    $subtotalAfterCoupon = max(0.0, (float) $cart['subtotal'] - $couponDiscount);
    $total = $subtotalAfterCoupon + $deliveryFee;
    $requiresCustomer = !$currentCustomer;
    $requiresLogin = $isDelivery && !$currentCustomer;
    $requiresAddress = $isDelivery && $currentCustomer && !$deliveryAddress;
    $deliveryUnavailable = $isDelivery && $currentCustomer && $deliveryAddress && !$deliverySupported;
    $baseCanFinalize = $cart['items'] !== []
        && !$requiresCustomer
        && (!$isDelivery || (!$requiresLogin && !$requiresAddress && !$deliveryUnavailable));
    $paymentMethodValue = storefront_normalize_checkout_payment($selection['payment_method']);
    $paymentScope = storefront_checkout_payment_scope($paymentMethodValue);
    $onlinePaymentMethod = $paymentScope === 'online' && in_array($paymentMethodValue, ['pix', 'online_card'], true)
        ? $paymentMethodValue
        : '';
    $onDeliveryPaymentMethod = $paymentScope === 'on_delivery' && in_array($paymentMethodValue, ['card', 'cash'], true)
        ? $paymentMethodValue
        : '';
    $cashChangeChoice = $paymentMethodValue === 'cash'
        ? storefront_normalize_checkout_cash_change_choice($selection['cash_change_choice'] ?? null)
        : null;
    $cashChangeForValue = $paymentMethodValue === 'cash'
        ? storefront_normalize_checkout_cash_change_for($selection['cash_change_for'] ?? null)
        : null;
    $cashChangeDue = $cashChangeForValue !== null
        ? max(0, $cashChangeForValue - $total)
        : null;
    $cashChangeValid = $paymentMethodValue !== 'cash'
        || $cashChangeChoice === 'exact'
        || ($cashChangeForValue !== null && $cashChangeForValue >= $total);
    $onlineCardReady = false;
    $canFinalize = $baseCanFinalize
        && $paymentMethodValue !== ''
        && $cashChangeValid
        && !($paymentMethodValue === 'online_card' && !$onlineCardReady);

    return [
        'fulfillment_method' => $selection['fulfillment_method'],
        'fulfillment_label' => $isDelivery ? 'Receber em casa' : 'Buscar na loja',
        'payment_method' => $paymentMethodValue,
        'payment_scope' => $paymentScope,
        'online_payment_method' => $onlinePaymentMethod,
        'on_delivery_payment_method' => $onDeliveryPaymentMethod,
        'payment_label' => storefront_checkout_payment_display_label($paymentMethodValue, $selection['fulfillment_method']),
        'payment_options' => storefront_checkout_payment_options(),
        'show_payment_options' => true,
        'show_online_payment_options' => true,
        'show_delivery_payment_options' => true,
        'online_card_ready' => $onlineCardReady,
        'coupon_code' => (string) ($couponApplication['code'] ?? ''),
        'coupon_valid' => !empty($couponApplication['valid']),
        'coupon_error' => (string) ($couponApplication['error'] ?? ''),
        'coupon' => $couponApplication['coupon'] ?? null,
        'coupon_description' => (string) ($couponApplication['description'] ?? ''),
        'coupon_discount' => $couponDiscount,
        'coupon_discount_formatted' => format_currency($couponDiscount),
        'coupon_shipping_discount' => $shippingDiscount,
        'coupon_shipping_discount_formatted' => format_currency($shippingDiscount),
        'coupon_total_discount' => $couponDiscount + $shippingDiscount,
        'coupon_total_discount_formatted' => format_currency($couponDiscount + $shippingDiscount),
        'cash_requires_input' => $paymentMethodValue === 'cash' && $cashChangeChoice !== 'exact',
        'cash_change_choice' => $cashChangeChoice,
        'cash_change_for' => $cashChangeForValue,
        'cash_change_for_formatted' => $cashChangeForValue !== null ? number_format($cashChangeForValue, 2, ',', '.') : '',
        'cash_change_due' => $cashChangeDue,
        'cash_change_due_formatted' => $cashChangeDue !== null ? format_currency($cashChangeDue) : '',
        'cash_change_valid' => $cashChangeValid,
        'delivery_address' => $deliveryAddress,
        'delivery_supported' => $deliverySupported,
        'delivery_fee_base' => $baseDeliveryFee,
        'delivery_fee_base_formatted' => format_currency($baseDeliveryFee),
        'delivery_fee' => $deliveryFee,
        'delivery_fee_formatted' => format_currency($deliveryFee),
        'subtotal_formatted' => format_currency((float) $cart['subtotal']),
        'subtotal_after_coupon' => $subtotalAfterCoupon,
        'subtotal_after_coupon_formatted' => format_currency($subtotalAfterCoupon),
        'total' => $total,
        'total_formatted' => format_currency($total),
        'requires_customer' => $requiresCustomer,
        'requires_login' => $requiresLogin,
        'requires_address' => $requiresAddress,
        'delivery_unavailable' => $deliveryUnavailable,
        'base_can_finalize' => $baseCanFinalize,
        'can_finalize' => $canFinalize,
        'checkout_url' => '',
    ];
}

function storefront_cart_whatsapp_link(
    array $storeSettings,
    ?array $currentCustomer,
    array $cart,
    string $fulfillmentMethod = 'delivery',
    ?string $paymentMethod = null,
    ?array $deliveryAddress = null,
    mixed $cashChangeFor = null
): string
{
    $normalizedMethod = storefront_normalize_checkout_method($fulfillmentMethod);
    $normalizedPayment = storefront_normalize_checkout_payment($paymentMethod);
    $deliveryFee = $normalizedMethod === 'delivery' ? storefront_delivery_fee($deliveryAddress) : 0.0;
    $total = (float) $cart['subtotal'] + $deliveryFee;
    $cashChangeForValue = $normalizedPayment === 'cash'
        ? storefront_normalize_checkout_cash_change_for($cashChangeFor)
        : null;
    $lines = [];
    $lines[] = $currentCustomer
        ? 'Ola! Sou ' . $currentCustomer['nome'] . ' e quero fechar este pedido:'
        : 'Ola! Quero fechar este pedido:';
    $lines[] = '';

    foreach ($cart['items'] as $item) {
        $product = $item['product'];
        $line = '- ' . $item['quantity'] . 'x ' . $product['nome'];

        if (!empty($item['flavor'])) {
            $line .= ' | Tamanho: ' . $item['flavor'];
        }

        $line .= ' | ' . format_currency((float) $item['line_total']);
        $lines[] = $line;
    }

    $lines[] = '';
    $lines[] = 'Subtotal: ' . format_currency((float) $cart['subtotal']);
    $lines[] = 'Forma de recebimento: ' . ($normalizedMethod === 'delivery' ? 'Receber em casa' : 'Buscar na loja');
    $lines[] = 'Pagamento: ' . storefront_checkout_payment_display_label($normalizedPayment, $normalizedMethod);

    if ($normalizedMethod === 'delivery') {
        if ($normalizedPayment === 'cash' && $cashChangeForValue !== null) {
            $lines[] = 'Troco para: ' . format_currency($cashChangeForValue);

            if ($cashChangeForValue > $total) {
                $lines[] = 'Troco necessario: ' . format_currency($cashChangeForValue - $total);
            }
        }

        $deliveryArea = storefront_resolve_delivery_area($deliveryAddress);
        $deliveryAreaLabel = $deliveryArea
            ? storefront_delivery_area_label($deliveryArea)
            : storefront_delivery_supported_locations_text(' ou ');
        $lines[] = 'Taxa de entrega (' . $deliveryAreaLabel . '): ' . format_currency($deliveryFee);
        $lines[] = 'Total com entrega: ' . format_currency($total);
        $lines[] = storefront_delivery_eta_text();
    } else {
        if ($normalizedPayment === 'cash' && $cashChangeForValue !== null) {
            $lines[] = 'Troco para: ' . format_currency($cashChangeForValue);

            if ($cashChangeForValue > $total) {
                $lines[] = 'Troco necessario: ' . format_currency($cashChangeForValue - $total);
            }
        }

        $lines[] = 'Total: ' . format_currency($total);
        $lines[] = 'Retirada na loja.';
    }

    if ($normalizedMethod === 'delivery' && $deliveryAddress) {
        $lines[] = '';
        $lines[] = 'Entregar em: ' . build_customer_address_string(
            (string) ($deliveryAddress['rua'] ?? ''),
            (string) ($deliveryAddress['bairro'] ?? ''),
            (string) ($deliveryAddress['numero'] ?? ''),
            (string) ($deliveryAddress['complemento'] ?? ''),
            (string) ($deliveryAddress['cidade'] ?? ''),
            (string) ($deliveryAddress['uf'] ?? '')
        );
    }

    return whatsapp_link($storeSettings['telefone_whatsapp'] ?? '', implode("\n", $lines));
}

function storefront_featured_products(array $products): array
{
    return array_values(array_filter(
        $products,
        static fn(array $product): bool => (int) ($product['destaque'] ?? 0) === 1
    ));
}

function storefront_promotion_products(array $products): array
{
    return array_values(array_filter(
        $products,
        static fn(array $product): bool => (int) ($product['promocao'] ?? 0) === 1
    ));
}

function storefront_brand_display_name(array $product): string
{
    return trim((string) ($product['marca_nome'] ?? '')) !== ''
        ? (string) $product['marca_nome']
        : 'Sem marca';
}

function storefront_brand_media(array $brand, array $products = []): string
{
    $brandImage = trim((string) ($brand['imagem'] ?? ''));

    if ($brandImage !== '') {
        return $brandImage;
    }

    foreach ($products as $product) {
        $productImage = trim((string) ($product['imagem'] ?? ''));

        if ($productImage !== '') {
            return $productImage;
        }
    }

    return 'assets/img/default-logo.svg';
}

function storefront_build_context(): array
{
    $storeSettings = fetch_store_settings();
    $currentCustomer = current_customer();
    $categories = storefront_fetch_active_categories();
    $brands = storefront_fetch_active_brands();
    $products = storefront_fetch_active_products();
    $productsByCategory = storefront_group_products_by_category($products);
    $productsByBrand = storefront_sort_brand_groups(
        $brands,
        storefront_group_products_by_brand($products)
    );
    $visibleCategories = storefront_filter_visible_categories($categories, $productsByCategory);
    $visibleBrands = storefront_filter_visible_brands($brands, $productsByBrand);
    $featuredProducts = storefront_featured_products($products);
    $promotionProducts = storefront_promotion_products($products);
    $featuredProductsByBrand = storefront_sort_brand_groups(
        $brands,
        storefront_group_products_by_brand($featuredProducts)
    );
    $featuredBrands = storefront_filter_visible_brands($brands, $featuredProductsByBrand);
    $cart = storefront_customer_can_use_cart($currentCustomer)
        ? storefront_build_cart()
        : [
            'items' => [],
            'count' => 0,
            'subtotal' => 0.0,
            'coupon_discount' => 0.0,
            'coupon_code' => '',
            'total' => 0.0,
        ];
    $favoriteCount = $currentCustomer
        ? customer_favorite_count((int) ($currentCustomer['id'] ?? 0))
        : 0;
    $customerNotifications = $currentCustomer
        ? fetch_customer_notifications((int) ($currentCustomer['id'] ?? 0), 6)
        : [];
    $customerNotificationUnreadCount = $currentCustomer
        ? count_customer_unread_notifications((int) ($currentCustomer['id'] ?? 0))
        : 0;

    return [
        'storeSettings' => $storeSettings,
        'currentCustomer' => $currentCustomer,
        'categories' => $categories,
        'brands' => $brands,
        'products' => $products,
        'productsByCategory' => $productsByCategory,
        'productsByBrand' => $productsByBrand,
        'visibleCategories' => $visibleCategories,
        'visibleBrands' => $visibleBrands,
        'featuredProducts' => $featuredProducts,
        'promotionProducts' => $promotionProducts,
        'featuredBrands' => $featuredBrands,
        'featuredProductsByBrand' => $featuredProductsByBrand,
        'heroProduct' => $featuredProducts[0] ?? null,
        'cartCount' => $cart['count'],
        'cartSummary' => $cart,
        'favoriteCount' => $favoriteCount,
        'customerNotifications' => $customerNotifications,
        'customerNotificationUnreadCount' => $customerNotificationUnreadCount,
        'totalFeatured' => count(array_filter(
            $products,
            static fn(array $product): bool => (int) ($product['destaque'] ?? 0) === 1
        )),
        'totalPromotions' => count(array_filter(
            $products,
            static fn(array $product): bool => (int) ($product['promocao'] ?? 0) === 1
        )),
        'announcementText' => storefront_announcement_text($storeSettings),
        'storeWhatsApp' => storefront_store_whatsapp_link($storeSettings, $currentCustomer),
        'cartUrl' => app_url('carrinho.php'),
    ];
}
