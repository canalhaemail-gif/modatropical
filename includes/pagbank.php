<?php
declare(strict_types=1);

function pagbank_api_token(?string $environment = null): string
{
    $resolvedEnvironment = $environment !== null && $environment !== ''
        ? strtolower(trim($environment))
        : pagbank_environment();

    if ($resolvedEnvironment === 'sandbox') {
        return trim((string) (defined('PAGBANK_SANDBOX_API_TOKEN') ? PAGBANK_SANDBOX_API_TOKEN : ''));
    }

    return trim((string) PAGBANK_API_TOKEN);
}

function pagbank_is_enabled(?string $environment = null): bool
{
    return defined('PAGBANK_ENABLED')
        && PAGBANK_ENABLED
        && pagbank_api_token($environment) !== '';
}

function pagbank_environment(): string
{
    return strtolower(trim((string) PAGBANK_ENVIRONMENT)) === 'sandbox'
        ? 'sandbox'
        : 'production';
}

function pagbank_public_base_url(): string
{
    $configured = rtrim(trim((string) PAGBANK_PUBLIC_BASE_URL), '/');

    if ($configured !== '') {
        return $configured;
    }

    return rtrim(absolute_app_url(), '/');
}

function pagbank_public_url(string $path = ''): string
{
    $base = pagbank_public_base_url();

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function pagbank_webhook_url(): string
{
    $configured = trim((string) PAGBANK_WEBHOOK_URL);

    if ($configured !== '') {
        return $configured;
    }

    return pagbank_public_url('pagbank-webhook.php');
}

function pagbank_redirect_url(): string
{
    $configured = trim((string) PAGBANK_REDIRECT_URL);

    if ($configured !== '') {
        return $configured;
    }

    return pagbank_public_url('meus-pedidos.php');
}

function pagbank_api_base_url(?string $environment = null): string
{
    $resolvedEnvironment = $environment !== null && $environment !== ''
        ? strtolower(trim($environment))
        : pagbank_environment();

    return $resolvedEnvironment === 'sandbox'
        ? 'https://sandbox.api.pagseguro.com'
        : 'https://api.pagseguro.com';
}

function pagbank_timeout(): int
{
    return max(5, (int) (defined('PAGBANK_TIMEOUT') ? PAGBANK_TIMEOUT : 20));
}

function pagbank_user_agent(): string
{
    $appName = trim((string) (defined('APP_NAME') ? APP_NAME : 'Moda Tropical'));
    $baseUrl = trim((string) pagbank_public_base_url());

    if ($appName === '') {
        $appName = 'Moda Tropical';
    }

    return $baseUrl !== ''
        ? $appName . '/1.0 (+'. $baseUrl .')'
        : $appName . '/1.0';
}

function pagbank_request_capture(string $method, string $path, ?array $payload = null, array $headers = [], ?string $environment = null): array
{
    $resolvedEnvironment = $environment !== null && $environment !== ''
        ? strtolower(trim($environment))
        : pagbank_environment();
    $apiToken = pagbank_api_token($resolvedEnvironment);

    if (!pagbank_is_enabled($resolvedEnvironment)) {
        throw new RuntimeException('PagBank nao esta configurado.');
    }

    $url = str_starts_with($path, 'http')
        ? $path
        : rtrim(pagbank_api_base_url($resolvedEnvironment), '/') . '/' . ltrim($path, '/');

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('Nao foi possivel inicializar a conexao com o PagBank.');
    }

    $requestHeaders = array_merge([
        'Authorization: Bearer ' . $apiToken,
        'Accept: application/json',
        'User-Agent: ' . pagbank_user_agent(),
    ], $headers);

    $options = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => pagbank_timeout(),
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_USERAGENT => pagbank_user_agent(),
    ];

    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            throw new RuntimeException('Nao foi possivel montar a requisicao do PagBank.');
        }

        $options[CURLOPT_POSTFIELDS] = $json;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);

    if ($response === false) {
        $message = curl_error($curl) ?: 'Falha na comunicacao com o PagBank.';
        curl_close($curl);
        return [
            'ok' => false,
            'status' => 0,
            'headers' => '',
            'raw_body' => '',
            'body' => [],
            'url' => $url,
            'error_message' => $message,
        ];
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    $rawHeaders = substr($response, 0, $headerSize);
    $rawBody = substr($response, $headerSize);
    $decoded = json_decode($rawBody, true);

    $message = '';

    if ($statusCode >= 400) {
        $message = 'Erro ao comunicar com o PagBank (HTTP ' . $statusCode . ').';

        if (is_array($decoded)) {
            $detail = trim((string) (
                $decoded['error_messages'][0]['description']
                ?? $decoded['error_messages'][0]['message']
                ?? $decoded['message']
                ?? $decoded['error']
                ?? ''
            ));

            if ($detail !== '') {
                $message = $detail;
            }
        }

        error_log('PagBank API error [' . strtoupper($method) . ' ' . $url . '] HTTP ' . $statusCode . ' | body: ' . trim((string) $rawBody));
    }

    return [
        'ok' => $statusCode > 0 && $statusCode < 400,
        'status' => $statusCode,
        'headers' => $rawHeaders,
        'raw_body' => $rawBody,
        'body' => is_array($decoded) ? $decoded : [],
        'url' => $url,
        'error_message' => $message,
    ];
}

function pagbank_request_with_environment(string $environment, string $method, string $path, ?array $payload = null, array $headers = []): array
{
    $response = pagbank_request_capture($method, $path, $payload, $headers, $environment);

    if (!empty($response['ok'])) {
        return $response;
    }

    throw new RuntimeException((string) ($response['error_message'] ?? 'Erro ao comunicar com o PagBank.'));
}

function pagbank_request(string $method, string $path, ?array $payload = null, array $headers = []): array
{
    return pagbank_request_with_environment(pagbank_environment(), $method, $path, $payload, $headers);
}

function pagbank_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function pagbank_money_to_cents(float $amount): int
{
    return max(0, (int) round($amount * 100));
}

function pagbank_limit_text(string $value, int $limit): string
{
    $value = trim($value);

    if ($value === '' || $limit < 1) {
        return '';
    }

    $slice = function_exists('mb_substr')
        ? mb_substr($value, 0, $limit, 'UTF-8')
        : substr($value, 0, $limit);

    return trim((string) $slice);
}

function pagbank_checkout_soft_descriptor(): string
{
    $descriptor = trim((string) (defined('PAGBANK_CHECKOUT_SOFT_DESCRIPTOR') ? PAGBANK_CHECKOUT_SOFT_DESCRIPTOR : 'MODATROPICAL'));
    $descriptor = strtoupper(preg_replace('/[^A-Z0-9]/', '', $descriptor) ?? '');

    if ($descriptor === '') {
        $descriptor = 'MODATROPICAL';
    }

    return substr($descriptor, 0, 13);
}

function pagbank_pix_expiration_date(): string
{
    $expiresAt = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $expiresAt = $expiresAt->modify('+30 minutes');

    return $expiresAt->format('Y-m-d\TH:i:sP');
}

function pagbank_checkout_expiration_date(): string
{
    $expiresAt = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $expiresAt = $expiresAt->modify('+1 day');

    return $expiresAt->format('Y-m-d\TH:i:sP');
}

function pagbank_pick_customer_address(?array $currentCustomer, array $checkout = []): ?array
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

function pagbank_build_customer(?array $currentCustomer, array $checkout = []): array
{
    if (!$currentCustomer) {
        return [];
    }

    $name = normalize_person_name((string) ($currentCustomer['nome'] ?? 'Cliente Moda Tropical'));
    $email = normalize_email((string) ($currentCustomer['email'] ?? ''));
    $cpf = normalize_cpf((string) ($currentCustomer['cpf'] ?? ''));
    $phoneDigits = pagbank_digits((string) ($currentCustomer['telefone'] ?? ''));

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [];
    }

    $customer = [
        'name' => pagbank_limit_text($name, 80),
        'email' => $email,
    ];

    if (strlen($cpf) === 11) {
        $customer['tax_id'] = $cpf;
    }

    if (strlen($phoneDigits) === 10 || strlen($phoneDigits) === 11) {
        $customer['phones'] = [[
            'country' => '55',
            'area' => substr($phoneDigits, 0, 2),
            'number' => substr($phoneDigits, 2),
            'type' => strlen($phoneDigits) === 11 ? 'MOBILE' : 'HOME',
        ]];
    }

    return $customer;
}

function pagbank_build_items(array $cart): array
{
    $items = [];

    foreach ($cart['items'] as $item) {
        $product = is_array($item['product'] ?? null) ? $item['product'] : [];
        $productId = (int) ($product['id'] ?? 0);
        $name = trim((string) ($product['nome'] ?? 'Produto Moda Tropical'));
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitPrice = isset($item['unit_price'])
            ? (float) $item['unit_price']
            : product_final_price($product);

        $entry = [
            'reference_id' => $productId > 0 ? ('PROD-' . $productId) : ('ITEM-' . (count($items) + 1)),
            'name' => pagbank_limit_text($name, 120),
            'quantity' => $quantity,
            'unit_amount' => pagbank_money_to_cents($unitPrice),
        ];

        $image = trim((string) ($product['imagem'] ?? ''));

        if ($image !== '') {
            $entry['image_url'] = app_url($image);
        }

        $items[] = $entry;
    }

    return $items;
}

function pagbank_checkout_payment_methods(): array
{
    return [
        ['type' => 'CREDIT_CARD'],
        ['type' => 'DEBIT_CARD'],
        ['type' => 'PIX'],
    ];
}

function pagbank_build_checkout_payload(
    array $customer,
    array $items,
    string $referenceId,
    ?string $redirectUrl = null,
    ?string $expirationDate = null
): array {
    $payload = [
        'reference_id' => $referenceId,
        'expiration_date' => $expirationDate !== null && trim($expirationDate) !== ''
            ? $expirationDate
            : pagbank_checkout_expiration_date(),
        'payment_methods' => pagbank_checkout_payment_methods(),
        'soft_descriptor' => pagbank_checkout_soft_descriptor(),
        'redirect_url' => $redirectUrl !== null && trim($redirectUrl) !== ''
            ? trim($redirectUrl)
            : pagbank_redirect_url(),
        'items' => $items,
    ];

    if ($customer !== []) {
        $payload['customer'] = $customer;
    }

    return $payload;
}

function pagbank_homologation_log_directory(): string
{
    return BASE_PATH . '/storage/logs';
}

function pagbank_homologation_reference_id(): string
{
    return 'HOMOLOG-' . date('YmdHis');
}

function pagbank_homologation_customer(): array
{
    return [
        'name' => 'Joao teste',
        'email' => 'joao@teste.com',
        'tax_id' => '12345678909',
    ];
}

function pagbank_homologation_items(string $referenceId): array
{
    return [
        [
            'reference_id' => $referenceId,
            'name' => 'Moda Tropical Homologacao',
            'quantity' => 1,
            'unit_amount' => 5000,
        ],
    ];
}

function pagbank_homologation_checkout_payload(): array
{
    $referenceId = pagbank_homologation_reference_id();

    return pagbank_build_checkout_payload(
        pagbank_homologation_customer(),
        pagbank_homologation_items($referenceId),
        $referenceId,
        pagbank_redirect_url()
    );
}

function pagbank_checkout_pay_link(array $payload): string
{
    $links = is_array($payload['links'] ?? null) ? $payload['links'] : [];

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        if (strtoupper(trim((string) ($link['rel'] ?? ''))) !== 'PAY') {
            continue;
        }

        $href = trim((string) ($link['href'] ?? ''));

        if ($href !== '') {
            return $href;
        }
    }

    return '';
}

function pagbank_homologation_response_payload(array $response): array
{
    if (is_array($response['body'] ?? null) && $response['body'] !== []) {
        return $response['body'];
    }

    return [
        'status' => (int) ($response['status'] ?? 0),
        'error' => (string) ($response['error_message'] ?? ''),
        'raw_body' => (string) ($response['raw_body'] ?? ''),
    ];
}

function pagbank_homologation_log_content(array $requestPayload, array $responsePayload): string
{
    $requestJson = json_encode($requestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $responseJson = json_encode($responsePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($requestJson) || !is_string($responseJson)) {
        throw new RuntimeException('Nao foi possivel serializar o log da homologacao.');
    }

    return "Request\n\n"
        . $requestJson
        . "\n\nResponse\n\n"
        . $responseJson
        . "\n";
}

function pagbank_homologation_log_name(): string
{
    return 'pagbank-checkout-homologacao-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt';
}

function pagbank_homologation_log_absolute_path(string $fileName): string
{
    return rtrim(pagbank_homologation_log_directory(), '/\\') . '/' . ltrim($fileName, '/\\');
}

function pagbank_homologation_log_is_valid_name(string $fileName): bool
{
    return preg_match('/^pagbank-checkout-homologacao-\d{8}-\d{6}-[a-f0-9]{6}\.txt$/', $fileName) === 1;
}

function pagbank_run_checkout_homologation(): array
{
    if (!pagbank_is_enabled('sandbox')) {
        throw new RuntimeException('PagBank Sandbox nao esta configurado.');
    }

    $requestPayload = pagbank_homologation_checkout_payload();
    $referenceId = trim((string) ($requestPayload['reference_id'] ?? 'HOMOLOG'));

    $response = pagbank_request_capture(
        'POST',
        '/checkouts',
        $requestPayload,
        [
            'x-idempotency-key: ' . md5($referenceId . '|checkout|sandbox'),
        ],
        'sandbox'
    );

    $responsePayload = pagbank_homologation_response_payload($response);
    $logDirectory = pagbank_homologation_log_directory();

    if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
        throw new RuntimeException('Nao foi possivel criar o diretorio de logs da homologacao.');
    }

    $logName = pagbank_homologation_log_name();
    $logFile = pagbank_homologation_log_absolute_path($logName);
    $logContent = pagbank_homologation_log_content($requestPayload, $responsePayload);

    if (file_put_contents($logFile, $logContent) === false) {
        $lastError = error_get_last();
        $message = trim((string) ($lastError['message'] ?? ''));

        if ($message === '') {
            $message = 'Falha desconhecida ao gravar o log.';
        }

        throw new RuntimeException('Nao foi possivel gravar o arquivo de homologacao. ' . $message);
    }

    return [
        'ok' => !empty($response['ok']),
        'status' => (int) ($response['status'] ?? 0),
        'request_payload' => $requestPayload,
        'response_payload' => $responsePayload,
        'response' => $response,
        'reference_id' => $referenceId,
        'checkout_id' => trim((string) ($responsePayload['id'] ?? '')),
        'pay_url' => pagbank_checkout_pay_link($responsePayload),
        'log_name' => $logName,
        'log_file' => $logFile,
    ];
}

function pagbank_build_shipping(?array $currentCustomer, array $checkout = []): array
{
    $address = pagbank_pick_customer_address($currentCustomer, $checkout);

    if (!is_array($address) || $address === []) {
        return [];
    }

    $street = trim((string) ($address['rua'] ?? ''));
    $number = trim((string) ($address['numero'] ?? ''));
    $locality = trim((string) ($address['bairro'] ?? ''));
    $city = trim((string) ($address['cidade'] ?? ''));
    $postalCode = normalize_cep((string) ($address['cep'] ?? ''));
    $regionCode = strtoupper(trim((string) ($address['uf'] ?? '')));

    if ($street === '' || $number === '' || $locality === '' || $city === '' || $postalCode === '' || $regionCode === '') {
        return [];
    }

    $shipping = [
        'address' => [
            'street' => $street,
            'number' => $number,
            'locality' => $locality,
            'city' => $city,
            'region_code' => $regionCode,
            'country' => 'BRA',
            'postal_code' => $postalCode,
        ],
    ];

    $complement = trim((string) ($address['complemento'] ?? ''));

    if ($complement !== '') {
        $shipping['address']['complement'] = $complement;
    }

    return $shipping;
}

function pagbank_pick_qr_image_source(array $qrCode): string
{
    $links = is_array($qrCode['links'] ?? null) ? $qrCode['links'] : [];

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $rel = strtoupper(trim((string) ($link['rel'] ?? '')));
        $href = trim((string) ($link['href'] ?? ''));

        if ($href === '') {
            continue;
        }

        if (in_array($rel, ['QRCODE.PNG', 'QRCODE.IMAGE', 'SELF'], true)) {
            return $href;
        }
    }

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $href = trim((string) ($link['href'] ?? ''));

        if ($href !== '') {
            return $href;
        }
    }

    return '';
}

function pagbank_extract_order_payment_data(array $payload, string $rawBody = ''): array
{
    $qrCode = isset($payload['qr_codes'][0]) && is_array($payload['qr_codes'][0])
        ? $payload['qr_codes'][0]
        : [];

    return [
        'external_order_id' => trim((string) ($payload['id'] ?? $payload['reference_id'] ?? '')),
        'external_charge_id' => trim((string) ($payload['charges'][0]['id'] ?? '')),
        'external_qr_id' => trim((string) ($qrCode['id'] ?? '')),
        'payment_status' => 'waiting',
        'payment_paid_at' => null,
        'payment_pix_text' => trim((string) ($qrCode['text'] ?? '')),
        'payment_pix_image_base64' => pagbank_pick_qr_image_source($qrCode),
        'payload_json' => $rawBody !== ''
            ? $rawBody
            : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
    ];
}

function pagbank_build_pix_order_payload(
    array $storeSettings,
    ?array $currentCustomer,
    array $cart,
    array $checkout,
    string $trackingCode
): array {
    $payload = [
        'reference_id' => $trackingCode,
        'items' => pagbank_build_items($cart),
        'qr_codes' => [[
            'amount' => [
                'value' => pagbank_money_to_cents((float) ($checkout['total'] ?? 0)),
            ],
            'expiration_date' => pagbank_pix_expiration_date(),
        ]],
        'notification_urls' => [pagbank_webhook_url()],
    ];

    $customer = pagbank_build_customer($currentCustomer, $checkout);

    if ($customer !== []) {
        $payload['customer'] = $customer;
    }

    return $payload;
}

function pagbank_create_pix_order(
    array $storeSettings,
    ?array $currentCustomer,
    array $cart,
    array $checkout,
    string $trackingCode
): array {
    $payload = pagbank_build_pix_order_payload($storeSettings, $currentCustomer, $cart, $checkout, $trackingCode);
    $response = pagbank_request('POST', '/orders', $payload, [
        'x-idempotency-key: ' . md5($trackingCode . '|pix|' . (string) ($checkout['total'] ?? '0')),
    ]);

    $body = is_array($response['body'] ?? null) ? $response['body'] : [];
    $paymentData = pagbank_extract_order_payment_data($body, (string) ($response['raw_body'] ?? ''));

    if (trim((string) ($paymentData['external_order_id'] ?? '')) === '' || trim((string) ($paymentData['payment_pix_text'] ?? '')) === '') {
        throw new RuntimeException('Nao foi possivel gerar o Pix do PagBank agora. Tente novamente.');
    }

    return $paymentData;
}

function pagbank_signature_header(array $server): string
{
    $header = $server['HTTP_X_AUTHENTICITY_TOKEN']
        ?? $server['REDIRECT_HTTP_X_AUTHENTICITY_TOKEN']
        ?? '';

    return strtolower(trim((string) $header));
}

function pagbank_validate_webhook_signature(string $rawBody, array $server): bool
{
    $signature = pagbank_signature_header($server);

    if ($signature === '') {
        return false;
    }

    $token = trim((string) PAGBANK_API_TOKEN);

    if ($token === '' || $rawBody === '') {
        return false;
    }

    $candidates = [
        hash('sha256', $rawBody . $token),
        hash('sha256', $token . $rawBody),
        hash('sha256', $token . '-' . $rawBody),
        hash('sha256', $rawBody . '-' . $token),
    ];

    foreach ($candidates as $candidate) {
        if (hash_equals($candidate, $signature)) {
            return true;
        }
    }

    return false;
}

function pagbank_log_webhook(string $message, array $context = []): void
{
    $suffix = $context !== []
        ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '';

    error_log('[pagbank-webhook] ' . $message . $suffix);
}

function pagbank_normalize_payment_status(?string $value): string
{
    $status = strtoupper(trim((string) $value));

    return match ($status) {
        'PAID' => 'paid',
        'AUTHORIZED' => 'authorized',
        'WAITING', 'PENDING', 'IN_ANALYSIS' => 'waiting',
        'DECLINED' => 'declined',
        'CANCELED', 'CANCELLED', 'EXPIRED' => 'cancelled',
        default => 'none',
    };
}

function pagbank_extract_webhook_payment_data(array $payload, string $rawBody = ''): array
{
    $charge = isset($payload['charges'][0]) && is_array($payload['charges'][0])
        ? $payload['charges'][0]
        : [];
    $metadata = isset($payload['metadata']) && is_array($payload['metadata'])
        ? $payload['metadata']
        : [];
    $qrCode = isset($charge['qr_codes'][0]) && is_array($charge['qr_codes'][0])
        ? $charge['qr_codes'][0]
        : (isset($payload['qr_codes'][0]) && is_array($payload['qr_codes'][0]) ? $payload['qr_codes'][0] : []);

    $externalOrderId = trim((string) (
        $payload['reference_id']
        ?? $payload['referenceId']
        ?? $metadata['reference_id']
        ?? $metadata['idComprador']
        ?? $charge['reference_id']
        ?? $charge['referenceId']
        ?? ''
    ));

    $chargeId = trim((string) ($charge['id'] ?? $payload['id'] ?? ''));
    $status = (string) ($charge['status'] ?? $payload['status'] ?? '');
    $paidAt = trim((string) (
        $charge['paid_at']
        ?? $charge['paidAt']
        ?? $payload['paid_at']
        ?? $payload['paidAt']
        ?? $payload['updated_at']
        ?? ''
    ));

    return [
        'external_order_id' => $externalOrderId,
        'external_charge_id' => $chargeId,
        'external_qr_id' => trim((string) ($qrCode['id'] ?? '')),
        'payment_status' => pagbank_normalize_payment_status($status),
        'payment_paid_at' => $paidAt !== '' ? date('Y-m-d H:i:s', strtotime($paidAt) ?: time()) : null,
        'payment_pix_text' => trim((string) ($qrCode['text'] ?? $payload['qr_code_text'] ?? '')),
        'payment_pix_image_base64' => pagbank_pick_qr_image_source($qrCode),
        'payload_json' => $rawBody !== ''
            ? $rawBody
            : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        'legacy_notification_code' => trim((string) ($payload['notificationCode'] ?? '')),
    ];
}

function pagbank_find_order_by_payment_reference(array $paymentData): ?array
{
    $candidates = array_values(array_unique(array_filter([
        trim((string) ($paymentData['external_order_id'] ?? '')),
        trim((string) ($paymentData['external_charge_id'] ?? '')),
    ])));

    if ($candidates === []) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM pedidos
         WHERE codigo_rastreio = :candidate
            OR payment_external_order_id = :candidate
            OR payment_external_charge_id = :candidate
         ORDER BY id DESC
         LIMIT 1'
    );

    foreach ($candidates as $candidate) {
        $statement->execute(['candidate' => $candidate]);
        $order = $statement->fetch();

        if ($order) {
            return $order;
        }
    }

    return null;
}
