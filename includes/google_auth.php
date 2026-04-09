<?php
declare(strict_types=1);

function google_login_enabled(): bool
{
    return GOOGLE_LOGIN_ENABLED && trim((string) GOOGLE_CLIENT_ID) !== '';
}

function customer_identities_table_available(): bool
{
    static $available = null;

    if (is_bool($available)) {
        return $available;
    }

    $available = table_exists('cliente_identities');

    return $available;
}

function customer_table_supports_social_registration(): bool
{
    static $supported = null;

    if (is_bool($supported)) {
        return $supported;
    }

    $requiredNullableFields = ['cep', 'endereco', 'cpf', 'data_nascimento', 'senha_hash'];
    $statement = db()->query('SHOW COLUMNS FROM clientes');
    $columns = $statement->fetchAll();
    $nullableMap = [];

    foreach ($columns as $column) {
        $field = (string) ($column['Field'] ?? '');

        if ($field === '') {
            continue;
        }

        $nullableMap[$field] = strtoupper((string) ($column['Null'] ?? 'NO')) === 'YES';
    }

    foreach ($requiredNullableFields as $field) {
        if (($nullableMap[$field] ?? false) !== true) {
            $supported = false;

            return $supported;
        }
    }

    $supported = true;

    return $supported;
}

function google_certificates_cache_directory(): string
{
    return dirname(GOOGLE_CERTS_CACHE_FILE);
}

function google_certificates_cache_file(): string
{
    return GOOGLE_CERTS_CACHE_FILE;
}

function google_base64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;

    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function google_fetch_certificates_from_remote(): array
{
    $headers = [];
    $body = false;
    $error = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => GOOGLE_CERTS_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$headers): int {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);

                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents(GOOGLE_CERTS_URL, false, $context);

        foreach (($http_response_header ?? []) as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                continue;
            }

            $parts = explode(':', $headerLine, 2);

            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        if ($body === false) {
            $error = 'Extensao cURL indisponivel e file_get_contents nao conseguiu consultar o Google.';
        }
    }

    if (!is_string($body) || $body === '') {
        throw new RuntimeException('Nao foi possivel consultar os certificados do Google.' . ($error !== '' ? ' ' . $error : ''));
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('Google respondeu com status inesperado ao consultar os certificados.');
    }

    $certificates = json_decode($body, true);

    if (!is_array($certificates) || $certificates === []) {
        throw new RuntimeException('Os certificados do Google vieram em um formato invalido.');
    }

    $ttl = GOOGLE_CERTS_CACHE_TTL;
    $cacheControl = strtolower((string) ($headers['cache-control'] ?? ''));

    if (preg_match('/max-age=(\d+)/', $cacheControl, $matches) === 1) {
        $ttl = max(60, (int) $matches[1]);
    }

    $directory = google_certificates_cache_directory();

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    @file_put_contents(
        google_certificates_cache_file(),
        json_encode([
            'expires_at' => time() + $ttl,
            'certificates' => $certificates,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    return $certificates;
}

function google_public_certificates(bool $forceRefresh = false): array
{
    $cacheFile = google_certificates_cache_file();

    if (!$forceRefresh && is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);

        if (
            is_array($cached)
            && (int) ($cached['expires_at'] ?? 0) > time()
            && !empty($cached['certificates'])
            && is_array($cached['certificates'])
        ) {
            return $cached['certificates'];
        }
    }

    return google_fetch_certificates_from_remote();
}

function verify_google_identity_token(string $idToken): array
{
    if (!google_login_enabled()) {
        return ['success' => false, 'error' => 'Google Login nao configurado.'];
    }

    if (!function_exists('openssl_verify')) {
        return ['success' => false, 'error' => 'A extensao OpenSSL nao esta disponivel no servidor para validar o Google Login.'];
    }

    $idToken = trim($idToken);

    if ($idToken === '') {
        return ['success' => false, 'error' => 'Token do Google ausente.'];
    }

    $parts = explode('.', $idToken);

    if (count($parts) !== 3) {
        return ['success' => false, 'error' => 'Token do Google em formato invalido.'];
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
    $headerJson = google_base64url_decode($encodedHeader);
    $payloadJson = google_base64url_decode($encodedPayload);
    $signature = google_base64url_decode($encodedSignature);

    if ($headerJson === false || $payloadJson === false || $signature === false) {
        return ['success' => false, 'error' => 'Token do Google nao pode ser decodificado.'];
    }

    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);

    if (!is_array($header) || !is_array($payload)) {
        return ['success' => false, 'error' => 'Token do Google veio com conteudo invalido.'];
    }

    if (($header['alg'] ?? '') !== 'RS256' || trim((string) ($header['kid'] ?? '')) === '') {
        return ['success' => false, 'error' => 'Assinatura do token do Google nao e suportada.'];
    }

    try {
        $certificates = google_public_certificates();
    } catch (Throwable $exception) {
        return ['success' => false, 'error' => $exception->getMessage()];
    }

    $kid = (string) $header['kid'];
    $certificate = $certificates[$kid] ?? null;

    if (!is_string($certificate) || trim($certificate) === '') {
        try {
            $certificates = google_public_certificates(true);
            $certificate = $certificates[$kid] ?? null;
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    if (!is_string($certificate) || trim($certificate) === '') {
        return ['success' => false, 'error' => 'Nao foi possivel encontrar a chave publica do Google para validar o login.'];
    }

    $verification = openssl_verify(
        $encodedHeader . '.' . $encodedPayload,
        $signature,
        $certificate,
        OPENSSL_ALGO_SHA256
    );

    if ($verification !== 1) {
        return ['success' => false, 'error' => 'Falha ao verificar a assinatura do token do Google.'];
    }

    $issuedAt = (int) ($payload['iat'] ?? 0);
    $notBefore = (int) ($payload['nbf'] ?? 0);
    $expiresAt = (int) ($payload['exp'] ?? 0);
    $now = time();
    $issuer = (string) ($payload['iss'] ?? '');
    $audience = $payload['aud'] ?? '';
    $audiences = is_array($audience) ? $audience : [$audience];

    if (!in_array(GOOGLE_CLIENT_ID, $audiences, true)) {
        return ['success' => false, 'error' => 'O token do Google nao foi emitido para esta aplicacao.'];
    }

    if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
        return ['success' => false, 'error' => 'O emissor do token do Google e invalido.'];
    }

    if ($expiresAt <= ($now - 60)) {
        return ['success' => false, 'error' => 'O token do Google expirou.'];
    }

    if ($notBefore > ($now + 60) || $issuedAt > ($now + 60)) {
        return ['success' => false, 'error' => 'O token do Google ainda nao e valido.'];
    }

    if (trim((string) ($payload['sub'] ?? '')) === '') {
        return ['success' => false, 'error' => 'O token do Google nao trouxe um identificador de usuario.'];
    }

    $email = normalize_email((string) ($payload['email'] ?? ''));
    $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['success' => false, 'error' => 'O Google nao retornou um email valido para esta conta.'];
    }

    if (!$emailVerified) {
        return ['success' => false, 'error' => 'O email retornado pelo Google ainda nao foi verificado.'];
    }

    return ['success' => true, 'claims' => $payload];
}

function find_customer_by_identity(string $provider, string $providerUserId): ?array
{
    if (!customer_identities_table_available()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT c.*
         FROM cliente_identities i
         INNER JOIN clientes c ON c.id = i.cliente_id
         WHERE i.provider = :provider
           AND i.provider_user_id = :provider_user_id
         LIMIT 1'
    );
    $statement->execute([
        'provider' => trim(strtolower($provider)),
        'provider_user_id' => trim($providerUserId),
    ]);
    $customer = $statement->fetch();

    return $customer ?: null;
}

function mark_customer_email_as_verified(int $customerId): void
{
    db()->prepare(
        'UPDATE clientes
         SET email_verificado_em = NOW()
         WHERE id = :id'
    )->execute(['id' => $customerId]);
}

function update_customer_name_if_blank(int $customerId, string $name): void
{
    $name = normalize_person_name($name);

    if ($name === '') {
        return;
    }

    db()->prepare(
        'UPDATE clientes
         SET nome = :nome
         WHERE id = :id
           AND (nome IS NULL OR nome = \'\')'
    )->execute([
        'nome' => $name,
        'id' => $customerId,
    ]);
}

function register_social_customer(string $name, string $email): int
{
    $name = normalize_person_name($name);
    $email = normalize_email($email);

    if ($name === '') {
        $name = ucfirst(strtok($email, '@') ?: 'Cliente');
    }

    $statement = db()->prepare(
        'INSERT INTO clientes (
            nome, email, telefone, cep, endereco, cpf, data_nascimento, senha_hash, email_verificado_em, ativo
         ) VALUES (
            :nome, :email, NULL, NULL, NULL, NULL, NULL, NULL, NOW(), 1
         )'
    );
    $statement->execute([
        'nome' => $name,
        'email' => $email,
    ]);

    return (int) db()->lastInsertId();
}

function upsert_customer_identity(int $customerId, string $provider, string $providerUserId, string $providerEmail): void
{
    if (!customer_identities_table_available()) {
        throw new RuntimeException('A tabela de identidades sociais nao esta disponivel.');
    }

    $statement = db()->prepare(
        'INSERT INTO cliente_identities (
            cliente_id, provider, provider_user_id, provider_email
         ) VALUES (
            :cliente_id, :provider, :provider_user_id, :provider_email
         )
         ON DUPLICATE KEY UPDATE
            cliente_id = VALUES(cliente_id),
            provider_email = VALUES(provider_email),
            atualizado_em = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'provider' => trim(strtolower($provider)),
        'provider_user_id' => trim($providerUserId),
        'provider_email' => normalize_email($providerEmail),
    ]);
}

function authenticate_customer_with_google(string $idToken): array
{
    try {
        $verification = verify_google_identity_token($idToken);

        if (!$verification['success']) {
            return ['success' => false, 'reason' => 'invalid_google_token', 'error' => (string) ($verification['error'] ?? 'Falha ao validar o login Google.')];
        }

        $claims = $verification['claims'];
        return social_auth_complete_login(
            'google',
            trim((string) ($claims['sub'] ?? '')),
            normalize_email((string) ($claims['email'] ?? '')),
            normalize_person_name((string) ($claims['name'] ?? ''))
        );
    } catch (Throwable $exception) {
        error_log('Google Login failure: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

        return [
            'success' => false,
            'reason' => 'exception',
            'error' => 'Nao foi possivel concluir o login com Google agora.',
        ];
    }
}
