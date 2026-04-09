<?php
declare(strict_types=1);

function asaas_is_enabled(): bool
{
    return ASAAS_ENABLED
        && trim((string) ASAAS_API_KEY) !== '';
}

function asaas_environment(): string
{
    return strtolower(trim((string) ASAAS_ENVIRONMENT)) === 'production'
        ? 'production'
        : 'sandbox';
}

function asaas_api_base_url(): string
{
    return asaas_environment() === 'production'
        ? 'https://api.asaas.com/v3'
        : 'https://api-sandbox.asaas.com/v3';
}

function asaas_public_base_url(): string
{
    $configured = rtrim(trim((string) ASAAS_PUBLIC_BASE_URL), '/');

    if ($configured !== '') {
        return $configured;
    }

    return rtrim(absolute_app_url(), '/');
}

function asaas_public_url(string $path = ''): string
{
    $base = asaas_public_base_url();

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function asaas_webhook_url(): string
{
    $configured = trim((string) ASAAS_WEBHOOK_URL);

    if ($configured !== '') {
        return $configured;
    }

    return asaas_public_url('asaas-webhook.php');
}

function asaas_checkout_base_url(): string
{
    $configured = rtrim(trim((string) ASAAS_CHECKOUT_BASE_URL), '/');

    return $configured !== ''
        ? $configured
        : 'https://asaas.com';
}

function asaas_checkout_url(string $checkoutId): string
{
    return asaas_checkout_base_url() . '/checkoutSession/show?id=' . rawurlencode($checkoutId);
}

function asaas_user_agent(): string
{
    $appName = trim((string) (defined('APP_NAME') ? APP_NAME : 'Moda Tropical'));
    $baseUrl = trim((string) asaas_public_base_url());

    if ($appName === '') {
        $appName = 'Moda Tropical';
    }

    if ($baseUrl !== '') {
        return $appName . '/1.0 (+'. $baseUrl .')';
    }

    return $appName . '/1.0';
}

function asaas_request(string $method, string $path, ?array $payload = null, array $headers = []): array
{
    if (!asaas_is_enabled()) {
        throw new RuntimeException('Asaas nao esta configurado.');
    }

    $url = str_starts_with($path, 'http')
        ? $path
        : rtrim(asaas_api_base_url(), '/') . '/' . ltrim($path, '/');

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('Nao foi possivel inicializar a conexao com o Asaas.');
    }

    $requestHeaders = array_merge([
        'access_token: ' . trim((string) ASAAS_API_KEY),
        'Accept: application/json',
        'User-Agent: ' . asaas_user_agent(),
    ], $headers);

    $options = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => max(5, (int) ASAAS_TIMEOUT),
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_USERAGENT => asaas_user_agent(),
    ];

    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            throw new RuntimeException('Nao foi possivel montar a requisicao do Asaas.');
        }

        $options[CURLOPT_POSTFIELDS] = $json;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);

    if ($response === false) {
        $message = curl_error($curl) ?: 'Falha na comunicacao com o Asaas.';
        curl_close($curl);
        throw new RuntimeException($message);
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    $rawHeaders = substr($response, 0, $headerSize);
    $rawBody = substr($response, $headerSize);
    $decoded = json_decode($rawBody, true);

    if ($statusCode >= 400) {
        $message = 'Erro ao comunicar com o Asaas (HTTP ' . $statusCode . ').';

        if (is_array($decoded)) {
            $firstError = $decoded['errors'][0]['description']
                ?? $decoded['errors'][0]['code']
                ?? $decoded['message']
                ?? null;

            if (is_string($firstError) && trim($firstError) !== '') {
                $message = trim($firstError);
            }
        } else {
            $rawBodyPreview = trim((string) $rawBody);

            if ($rawBodyPreview !== '') {
                $message .= ' Resposta: ' . substr($rawBodyPreview, 0, 220);
            }
        }

        error_log('Asaas API error [' . strtoupper($method) . ' ' . $url . '] HTTP ' . $statusCode . ' | body: ' . trim((string) $rawBody));
        throw new RuntimeException($message);
    }

    return [
        'status' => $statusCode,
        'headers' => $rawHeaders,
        'raw_body' => $rawBody,
        'body' => is_array($decoded) ? $decoded : [],
    ];
}

function asaas_checkout_billing_types(): array
{
    $raw = explode(',', (string) ASAAS_CHECKOUT_BILLING_TYPES);
    $allowed = ['PIX', 'CREDIT_CARD'];
    $billingTypes = [];

    foreach ($raw as $value) {
        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, $allowed, true) && !in_array($normalized, $billingTypes, true)) {
            $billingTypes[] = $normalized;
        }
    }

    if ($billingTypes === []) {
        $billingTypes = ['PIX', 'CREDIT_CARD'];
    }

    return $billingTypes;
}

function asaas_checkout_city_code(string $city, string $state): ?int
{
    $normalizedCity = function_exists('mb_strtolower')
        ? mb_strtolower(trim($city), 'UTF-8')
        : strtolower(trim($city));
    $normalizedState = strtoupper(trim($state));

    $codes = [
        'volta redonda|RJ' => 3306305,
        'barra mansa|RJ' => 3300407,
    ];

    $key = $normalizedCity . '|' . $normalizedState;

    return $codes[$key] ?? null;
}

function asaas_pick_customer_address(?array $currentCustomer, array $checkout = []): ?array
{
    $deliveryAddress = $checkout['delivery_address'] ?? null;

    if (is_array($deliveryAddress) && $deliveryAddress !== []) {
        return $deliveryAddress;
    }

    if (!$currentCustomer || empty($currentCustomer['id'])) {
        return null;
    }

    if (function_exists('fetch_customer_primary_address')) {
        $primaryAddress = fetch_customer_primary_address((int) $currentCustomer['id']);

        if (is_array($primaryAddress) && $primaryAddress !== []) {
            return $primaryAddress;
        }
    }

    if (function_exists('fetch_customer_addresses')) {
        $addresses = fetch_customer_addresses((int) $currentCustomer['id']);

        if (isset($addresses[0]) && is_array($addresses[0])) {
            return $addresses[0];
        }
    }

    return null;
}

function asaas_build_checkout_customer_data(?array $currentCustomer, array $checkout = []): array
{
    if (!$currentCustomer) {
        return [];
    }

    $address = asaas_pick_customer_address($currentCustomer, $checkout);
    $name = normalize_person_name((string) ($currentCustomer['nome'] ?? 'Cliente Moda Tropical'));
    $email = normalize_email((string) ($currentCustomer['email'] ?? ''));
    $phone = digits_only((string) ($currentCustomer['telefone'] ?? ''));
    $cpfCnpj = normalize_cpf((string) ($currentCustomer['cpf'] ?? ''));

    if (
        $name === ''
        || $email === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !in_array(strlen($phone), [10, 11], true)
        || !in_array(strlen($cpfCnpj), [11, 14], true)
        || !is_array($address)
    ) {
        return [];
    }

    $street = trim((string) ($address['rua'] ?? ''));
    $number = trim((string) ($address['numero'] ?? ''));
    $complement = trim((string) ($address['complemento'] ?? ''));
    $postalCode = digits_only((string) ($address['cep'] ?? ''));
    $province = trim((string) ($address['bairro'] ?? ''));
    $cityName = trim((string) ($address['cidade'] ?? ''));
    $state = strtoupper(trim((string) ($address['uf'] ?? '')));
    $cityCode = asaas_checkout_city_code($cityName, $state);

    if (
        $street === ''
        || $number === ''
        || strlen($postalCode) !== 8
        || $province === ''
        || $cityCode === null
    ) {
        return [];
    }

    $customerData = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'cpfCnpj' => $cpfCnpj,
        'address' => $street,
        'addressNumber' => $number,
        'postalCode' => $postalCode,
        'province' => $province,
        'city' => $cityCode,
    ];

    if ($complement !== '') {
        $customerData['complement'] = $complement;
    }

    return $customerData;
}

function asaas_limit_text(string $value, int $limit): string
{
    $value = trim($value);

    if ($value === '' || $limit < 1) {
        return '';
    }

    $truncated = function_exists('mb_substr')
        ? mb_substr($value, 0, $limit, 'UTF-8')
        : substr($value, 0, $limit);

    return trim((string) $truncated);
}

function asaas_build_checkout_items(array $cart, array $checkout): array
{
    $items = [];

    foreach (($cart['items'] ?? []) as $index => $item) {
        $product = is_array($item['product'] ?? null) ? $item['product'] : [];
        $quantity = max(1, (int) ($item['quantity'] ?? 0));
        $unitPrice = isset($item['unit_price'])
            ? (float) $item['unit_price']
            : (float) ($product['preco'] ?? 0);
        $name = trim((string) ($product['nome'] ?? 'Produto'));
        $flavor = trim((string) ($item['flavor'] ?? ''));
        $itemName = asaas_limit_text($name !== '' ? $name : 'Produto', 30);
        $description = $flavor !== ''
            ? 'Produto Moda Tropical | Tamanho: ' . $flavor
            : 'Produto Moda Tropical';

        $items[] = [
            'name' => $itemName !== '' ? $itemName : 'Produto',
            'description' => asaas_limit_text($description, 200),
            'quantity' => $quantity,
            'value' => max(0.01, round($unitPrice, 2)),
        ];
    }

    $deliveryFee = (float) ($checkout['delivery_fee'] ?? 0);
    if ($deliveryFee > 0) {
        $items[] = [
            'name' => 'Taxa de entrega',
            'description' => 'Entrega do pedido Moda Tropical',
            'quantity' => 1,
            'value' => round($deliveryFee, 2),
        ];
    }

    return $items;
}

function asaas_build_checkout_payload(
    array $storeSettings,
    ?array $currentCustomer,
    array $cart,
    array $checkout,
    string $trackingCode
): array {
    $trackingUrl = asaas_public_url('rastreio.php?codigo=' . rawurlencode($trackingCode) . '&asaas_return=1');
    $items = asaas_build_checkout_items($cart, $checkout);

    if ($items === []) {
        throw new RuntimeException('Nao foi possivel montar os itens do checkout do Asaas.');
    }

    $payload = [
        'billingTypes' => asaas_checkout_billing_types(),
        'chargeTypes' => ['DETACHED'],
        'minutesToExpire' => max(5, (int) ASAAS_CHECKOUT_MINUTES_TO_EXPIRE),
        'callback' => [
            'cancelUrl' => $trackingUrl . '&status=cancelled',
            'expiredUrl' => $trackingUrl . '&status=expired',
            'successUrl' => $trackingUrl . '&status=paid',
        ],
        'items' => $items,
    ];

    $customerData = asaas_build_checkout_customer_data($currentCustomer, $checkout);
    if ($customerData !== []) {
        $payload['customerData'] = $customerData;
    }

    return $payload;
}

function asaas_parse_datetime(?string $value): ?string
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return null;
    }

    $timestamp = strtotime($raw);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function asaas_normalize_payment_status(?string $event = null, ?string $status = null): string
{
    $event = strtoupper(trim((string) $event));
    $status = strtoupper(trim((string) $status));

    return match (true) {
        $event === 'CHECKOUT_PAID', $status === 'PAID' => 'paid',
        $event === 'CHECKOUT_CANCELED', $status === 'CANCELED', $status === 'CANCELLED' => 'cancelled',
        $event === 'CHECKOUT_EXPIRED', $status === 'EXPIRED' => 'cancelled',
        default => 'waiting',
    };
}

function asaas_create_checkout(
    array $storeSettings,
    ?array $currentCustomer,
    array $cart,
    array $checkout,
    string $trackingCode
): array {
    $payload = asaas_build_checkout_payload($storeSettings, $currentCustomer, $cart, $checkout, $trackingCode);
    $response = asaas_request('POST', '/checkouts', $payload);
    $body = is_array($response['body']) ? $response['body'] : [];
    $checkoutId = trim((string) ($body['id'] ?? ''));
    $checkoutUrl = trim((string) ($body['link'] ?? ''));

    if ($checkoutUrl === '' && $checkoutId !== '') {
        $checkoutUrl = asaas_checkout_url($checkoutId);
    }

    return [
        'external_order_id' => $checkoutId,
        'payment_status' => asaas_normalize_payment_status(null, (string) ($body['status'] ?? 'ACTIVE')),
        'payload_json' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'pay_url' => $checkoutUrl,
    ];
}

function asaas_extract_checkout_payment_data(array $payload): array
{
    $checkout = isset($payload['checkout']) && is_array($payload['checkout'])
        ? $payload['checkout']
        : $payload;
    $event = strtoupper(trim((string) ($payload['event'] ?? '')));

    return [
        'external_order_id' => trim((string) ($checkout['id'] ?? '')),
        'payment_status' => asaas_normalize_payment_status($event, (string) ($checkout['status'] ?? '')),
        'payment_paid_at' => $event === 'CHECKOUT_PAID'
            ? asaas_parse_datetime((string) ($payload['dateCreated'] ?? ''))
            : null,
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function asaas_validate_webhook_auth(array $server): bool
{
    $token = trim((string) ASAAS_WEBHOOK_TOKEN);

    if ($token === '') {
        return false;
    }

    $received = trim((string) (
        $server['HTTP_ASAAS_ACCESS_TOKEN']
        ?? $server['REDIRECT_HTTP_ASAAS_ACCESS_TOKEN']
        ?? ''
    ));

    if ($received === '') {
        return false;
    }

    return hash_equals($token, $received);
}

function online_payment_resolve_provider(): ?string
{
    if (function_exists('pagbank_is_enabled') && pagbank_is_enabled()) {
        return 'pagbank';
    }

    if (asaas_is_enabled()) {
        return 'asaas';
    }

    return null;
}

function online_payment_is_available(): bool
{
    return online_payment_resolve_provider() !== null;
}

function online_payment_provider_public_name(?string $provider = null): string
{
    $provider = trim((string) ($provider ?? online_payment_resolve_provider() ?? ''));

    return match ($provider) {
        'pagbank' => 'PagBank',
        'asaas' => 'Asaas',
        default => 'pagamento online',
    };
}

function online_payment_redirect_description(?string $provider = null): string
{
    $provider = trim((string) ($provider ?? online_payment_resolve_provider() ?? ''));

    return match ($provider) {
        'pagbank' => 'O pagamento online sera concluido dentro do site por Pix e, em breve, por cartao.',
        'asaas' => 'Voce sera redirecionado para o Asaas e podera concluir por Pix ou cartao de credito.',
        default => 'Voce sera redirecionado para um ambiente seguro para concluir o pagamento online.',
    };
}
