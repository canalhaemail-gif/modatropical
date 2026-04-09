<?php
declare(strict_types=1);

function facebook_login_enabled(): bool
{
    return FACEBOOK_LOGIN_ENABLED
        && trim((string) FACEBOOK_APP_ID) !== ''
        && trim((string) FACEBOOK_APP_SECRET) !== '';
}

function apple_private_key_contents(): string
{
    $inlineKey = trim((string) APPLE_PRIVATE_KEY);

    if ($inlineKey !== '') {
        return $inlineKey;
    }

    if (!is_file(APPLE_PRIVATE_KEY_PATH)) {
        return '';
    }

    return trim((string) file_get_contents(APPLE_PRIVATE_KEY_PATH));
}

function apple_login_enabled(): bool
{
    return APPLE_LOGIN_ENABLED
        && trim((string) APPLE_TEAM_ID) !== ''
        && trim((string) APPLE_CLIENT_ID) !== ''
        && trim((string) APPLE_KEY_ID) !== ''
        && apple_private_key_contents() !== '';
}

function tiktok_login_enabled(): bool
{
    return TIKTOK_LOGIN_ENABLED
        && trim((string) TIKTOK_CLIENT_KEY) !== ''
        && trim((string) TIKTOK_CLIENT_SECRET) !== '';
}

function social_provider_catalog(): array
{
    return [
        'google' => [
            'label' => 'Google',
            'enabled' => google_login_enabled(),
        ],
        'facebook' => [
            'label' => 'Facebook',
            'enabled' => facebook_login_enabled(),
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'enabled' => tiktok_login_enabled(),
        ],
    ];
}

function social_provider_exists(string $provider): bool
{
    return isset(social_provider_catalog()[trim(strtolower($provider))]);
}

function social_provider_enabled(string $provider): bool
{
    $catalog = social_provider_catalog();
    $provider = trim(strtolower($provider));

    return (bool) ($catalog[$provider]['enabled'] ?? false);
}

function social_provider_label(string $provider): string
{
    $catalog = social_provider_catalog();
    $provider = trim(strtolower($provider));

    return (string) ($catalog[$provider]['label'] ?? ucfirst($provider));
}

function social_enabled_providers(): array
{
    return array_keys(array_filter(
        social_provider_catalog(),
        static fn(array $provider): bool => !empty($provider['enabled'])
    ));
}

function social_auth_options_available(): bool
{
    return social_enabled_providers() !== [];
}

function social_auth_mode(string $mode): string
{
    return trim(strtolower($mode)) === 'link' ? 'link' : 'login';
}

function social_auth_store_last_provider(string $provider): void
{
    $_SESSION['social_auth_last_provider'] = trim(strtolower($provider));
}

function social_auth_pull_last_provider(): string
{
    $value = pull_session_value('social_auth_last_provider', '');

    return is_string($value) ? trim(strtolower($value)) : '';
}

function social_provider_start_url(string $provider, string $mode = 'login'): string
{
    $provider = trim(strtolower($provider));
    $mode = social_auth_mode($mode);

    if (!social_provider_exists($provider) || $provider === 'google') {
        return '#';
    }

    return app_url('oauth/social_start.php?provider=' . rawurlencode($provider) . '&mode=' . rawurlencode($mode));
}

function social_provider_icon_markup(string $provider): string
{
    $provider = trim(strtolower($provider));

    if ($provider === 'facebook') {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.5 22v-8h2.7l.5-3h-3.2V9.1c0-.9.3-1.6 1.7-1.6H17V4.8c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.4V11H7.5v3h2.8v8h3.2Z"></path></svg>';
    }

    if ($provider === 'apple') {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16.7 12.7c0-2.2 1.8-3.3 1.9-3.4-1-1.5-2.6-1.7-3.1-1.7-1.3-.1-2.5.8-3.1.8-.6 0-1.6-.8-2.6-.8-1.4 0-2.6.8-3.3 2-.9 1.6-.2 4 1 5.7.6.8 1.3 1.8 2.2 1.8.9 0 1.2-.6 2.3-.6 1 0 1.3.6 2.3.6 1 0 1.6-.8 2.2-1.6.7-.9 1-1.8 1-1.8-.1 0-2.1-.8-2.1-3Zm-2-6.4c.5-.6.9-1.4.8-2.2-.8 0-1.7.5-2.3 1.1-.5.5-.9 1.4-.8 2.2.9.1 1.8-.4 2.3-1.1Z"></path></svg>';
    }

    if ($provider === 'instagram') {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2Zm0 1.8A3.95 3.95 0 0 0 3.8 7.75v8.5a3.95 3.95 0 0 0 3.95 3.95h8.5a3.95 3.95 0 0 0 3.95-3.95v-8.5a3.95 3.95 0 0 0-3.95-3.95h-8.5Zm8.95 1.35a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3ZM12 6.85A5.15 5.15 0 1 1 6.85 12 5.16 5.16 0 0 1 12 6.85Zm0 1.8A3.35 3.35 0 1 0 15.35 12 3.35 3.35 0 0 0 12 8.65Z"></path></svg>';
    }

    if ($provider === 'tiktok') {
        return '<svg viewBox="0 0 448 512" aria-hidden="true" focusable="false"><path d="M448,209.9a210.1,210.1,0,0,1-122.8-39.3v178.7A133.8,133.8,0,1,1,192.2,215.5v67.1a67.7,67.7,0,1,0,66.9,67.7V0h66.1a121.2,121.2,0,0,0,1,13.6,122.2,122.2,0,0,0,54.9,80.8A121.4,121.4,0,0,0,448,104Z"></path></svg>';
    }

    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.35 11.1h-9.17v2.92h5.26c-.23 1.5-1.75 4.4-5.26 4.4-3.17 0-5.74-2.62-5.74-5.85s2.57-5.85 5.74-5.85c1.8 0 3 .76 3.69 1.41l2.52-2.44C16.77 4.18 14.69 3 12.18 3 7.21 3 3.18 7.03 3.18 12s4.03 9 9 9c5.2 0 8.65-3.65 8.65-8.8 0-.59-.06-1.04-.14-1.1Z"></path></svg>';
}

function fetch_customer_identities(int $customerId): array
{
    if ($customerId <= 0 || !customer_identities_table_available()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT id, cliente_id, provider, provider_user_id, provider_email, criado_em, atualizado_em
         FROM cliente_identities
         WHERE cliente_id = :cliente_id
         ORDER BY atualizado_em DESC, criado_em DESC'
    );
    $statement->execute(['cliente_id' => $customerId]);

    return $statement->fetchAll() ?: [];
}

function fetch_customer_identities_indexed(int $customerId): array
{
    $indexed = [];

    foreach (fetch_customer_identities($customerId) as $identity) {
        $provider = trim(strtolower((string) ($identity['provider'] ?? '')));

        if ($provider === '' || isset($indexed[$provider])) {
            continue;
        }

        $indexed[$provider] = $identity;
    }

    return $indexed;
}

function find_customer_identity_record(string $provider, string $providerUserId): ?array
{
    if (!customer_identities_table_available()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, cliente_id, provider, provider_user_id, provider_email, criado_em, atualizado_em
         FROM cliente_identities
         WHERE provider = :provider
           AND provider_user_id = :provider_user_id
         LIMIT 1'
    );
    $statement->execute([
        'provider' => trim(strtolower($provider)),
        'provider_user_id' => trim($providerUserId),
    ]);

    $identity = $statement->fetch();

    return $identity ?: null;
}

function find_customer_identity_for_customer(int $customerId, string $provider): ?array
{
    if ($customerId <= 0 || !customer_identities_table_available()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, cliente_id, provider, provider_user_id, provider_email, criado_em, atualizado_em
         FROM cliente_identities
         WHERE cliente_id = :cliente_id
           AND provider = :provider
         ORDER BY atualizado_em DESC, criado_em DESC
         LIMIT 1'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'provider' => trim(strtolower($provider)),
    ]);

    $identity = $statement->fetch();

    return $identity ?: null;
}

function customer_has_password_login_by_id(int $customerId): bool
{
    $customer = find_customer($customerId);

    return $customer ? customer_can_use_password_login($customer) : false;
}

function customer_login_method_count(int $customerId): int
{
    $count = customer_has_password_login_by_id($customerId) ? 1 : 0;

    return $count + count(fetch_customer_identities_indexed($customerId));
}

function customer_can_disconnect_social_identity(int $customerId, string $provider): bool
{
    if (!find_customer_identity_for_customer($customerId, $provider)) {
        return false;
    }

    return customer_login_method_count($customerId) > 1;
}

function disconnect_customer_social_identity(int $customerId, string $provider): bool
{
    if (!customer_identities_table_available()) {
        return false;
    }

    $statement = db()->prepare(
        'DELETE FROM cliente_identities
         WHERE cliente_id = :cliente_id
           AND provider = :provider'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'provider' => trim(strtolower($provider)),
    ]);

    return $statement->rowCount() > 0;
}

function detect_primary_social_provider(int $customerId): string
{
    $identities = fetch_customer_identities_indexed($customerId);

    return $identities === [] ? '' : (string) array_key_first($identities);
}

function social_auth_error_result(string $error, string $reason = 'invalid'): array
{
    return [
        'success' => false,
        'reason' => $reason,
        'error' => $error,
    ];
}

function social_auth_complete_login(string $provider, string $providerUserId, string $email, string $name): array
{
    try {
        if (!customer_identities_table_available()) {
            return social_auth_error_result('A integracao social ainda nao foi instalada no banco.', 'setup');
        }

        $provider = trim(strtolower($provider));
        $providerUserId = trim($providerUserId);
        $email = normalize_email($email);
        $name = normalize_person_name($name);
        $emailNeedsCompletion = false;

        if ($provider === '' || $providerUserId === '') {
            return social_auth_error_result('Nao foi possivel validar sua conta social.', 'invalid_profile');
        }

        if ($provider === 'tiktok' && ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $email = social_placeholder_email($provider, $providerUserId);
            $emailNeedsCompletion = true;
        } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return social_auth_error_result('O provedor nao retornou um email valido para esta conta.', 'missing_email');
        }

        $identity = find_customer_identity_record($provider, $providerUserId);
        $customer = $identity ? find_customer((int) ($identity['cliente_id'] ?? 0)) : null;
        $created = false;

        if (!$customer && !$emailNeedsCompletion) {
            $customer = find_customer_by_email($email);
        }

        if (!$customer) {
            if (!customer_table_supports_social_registration()) {
                return social_auth_error_result('O banco ainda nao recebeu a estrutura do login social. Rode a migracao update_google_login.sql.', 'setup');
            }

            $customerId = register_social_customer($name, $email);
            $customer = find_customer($customerId);
            $created = true;
        }

        if (!$customer) {
            return social_auth_error_result('Nao foi possivel preparar sua conta para o login social.', 'customer_create_failed');
        }

        if ((int) ($customer['ativo'] ?? 0) !== 1) {
            return social_auth_error_result('Sua conta esta inativa no momento.', 'inactive');
        }

        $customerId = (int) $customer['id'];
        update_customer_name_if_blank($customerId, $name);

        if (empty($customer['email_verificado_em'])) {
            mark_customer_email_as_verified($customerId);
        }

        upsert_customer_identity($customerId, $provider, $providerUserId, $email);
        $customer = find_customer($customerId);

        if (!$customer) {
            return social_auth_error_result('Nao foi possivel recuperar os dados da conta apos autenticar.', 'customer_missing');
        }

        login_customer_session($customer);
        social_auth_store_last_provider($provider);

        return [
            'success' => true,
            'reason' => 'ok',
            'customer' => $customer,
            'created' => $created,
            'profile_incomplete' => !customer_profile_is_complete($customer),
            'email_needs_completion' => $emailNeedsCompletion || customer_email_requires_completion((string) ($customer['email'] ?? '')),
        ];
    } catch (Throwable $exception) {
        error_log('Social login failure: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

        return social_auth_error_result('Nao foi possivel concluir o login social agora.', 'exception');
    }
}

function social_auth_connect_identity(int $customerId, string $provider, string $providerUserId, string $email, string $name): array
{
    try {
        if (!customer_identities_table_available()) {
            return social_auth_error_result('A integracao social ainda nao foi instalada no banco.', 'setup');
        }

        $customer = find_customer($customerId);

        if (!$customer || (int) ($customer['ativo'] ?? 0) !== 1) {
            return social_auth_error_result('Sua sessao expirou. Entre novamente para vincular a conta.', 'session');
        }

        $provider = trim(strtolower($provider));
        $providerUserId = trim($providerUserId);
        $email = normalize_email($email);
        $name = normalize_person_name($name);

        if ($provider === '' || $providerUserId === '') {
            return social_auth_error_result('Nao foi possivel validar essa conta para vinculo.', 'invalid_profile');
        }

        if ($provider !== 'tiktok' && ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return social_auth_error_result('O provedor nao retornou um email valido para esta conta.', 'missing_email');
        }

        $currentIdentity = find_customer_identity_for_customer($customerId, $provider);

        if ($currentIdentity) {
            if (trim((string) ($currentIdentity['provider_user_id'] ?? '')) === $providerUserId) {
                social_auth_store_last_provider($provider);

                return [
                    'success' => true,
                    'reason' => 'already_linked',
                    'customer' => $customer,
                ];
            }

            return social_auth_error_result('Ja existe outra conta deste provedor vinculada ao seu perfil. Desvincule antes de conectar outra.', 'provider_already_linked_here');
        }

        $providerIdentity = find_customer_identity_record($provider, $providerUserId);

        if ($providerIdentity && (int) ($providerIdentity['cliente_id'] ?? 0) !== $customerId) {
            return social_auth_error_result('Essa conta ja esta vinculada a outro cliente.', 'identity_in_use');
        }

        $emailOwner = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            ? find_customer_by_email($email)
            : null;

        if ($emailOwner && (int) ($emailOwner['id'] ?? 0) !== $customerId) {
            return social_auth_error_result('Esse email social ja pertence a outra conta cadastrada.', 'email_in_use');
        }

        update_customer_name_if_blank($customerId, $name);
        upsert_customer_identity($customerId, $provider, $providerUserId, $email);
        social_auth_store_last_provider($provider);

        return [
            'success' => true,
            'reason' => 'linked',
            'customer' => find_customer($customerId) ?: $customer,
        ];
    } catch (Throwable $exception) {
        error_log('Social link failure: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

        return social_auth_error_result('Nao foi possivel vincular essa conta agora.', 'exception');
    }
}

function social_oauth_storage_key(): string
{
    return 'social_oauth_states';
}

function social_oauth_create_state(string $provider, string $mode, ?int $customerId = null): string
{
    $state = bin2hex(random_bytes(24));
    $storage = $_SESSION[social_oauth_storage_key()] ?? [];

    if (!is_array($storage)) {
        $storage = [];
    }

    $storage[$state] = [
        'provider' => trim(strtolower($provider)),
        'mode' => social_auth_mode($mode),
        'customer_id' => $customerId,
        'created_at' => time(),
    ];
    $_SESSION[social_oauth_storage_key()] = $storage;

    return $state;
}

function social_oauth_consume_state(string $state): ?array
{
    $state = trim($state);
    $storage = $_SESSION[social_oauth_storage_key()] ?? [];

    if ($state === '' || !is_array($storage) || !isset($storage[$state])) {
        return null;
    }

    $payload = $storage[$state];
    unset($storage[$state]);
    $_SESSION[social_oauth_storage_key()] = $storage;

    if ((int) ($payload['created_at'] ?? 0) < (time() - 900)) {
        return null;
    }

    return is_array($payload) ? $payload : null;
}

function social_http_request(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    $headers = array_merge(['Accept: application/json'], $headers);
    $method = strtoupper(trim($method));

    if (function_exists('curl_init')) {
        $responseHeaders = [];
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 18,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($responseBody === false) {
            throw new RuntimeException('Falha na chamada HTTP remota.' . ($error !== '' ? ' ' . $error : ''));
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => (string) $responseBody,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 18,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    $responseHeaders = [];

    foreach (($http_response_header ?? []) as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $line, $matches) === 1) {
            $status = (int) $matches[1];
            continue;
        }

        $parts = explode(':', $line, 2);

        if (count($parts) === 2) {
            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
    }

    if ($responseBody === false) {
        throw new RuntimeException('Falha na chamada HTTP remota.');
    }

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => (string) $responseBody,
    ];
}

function social_http_form_request(string $url, array $payload): array
{
    return social_http_request(
        $url,
        'POST',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query($payload)
    );
}

function facebook_auth_redirect_uri(): string
{
    return absolute_app_url('oauth/social_callback.php');
}

function tiktok_auth_redirect_uri(): string
{
    return absolute_app_url('oauth/social_callback.php');
}

function tiktok_authorize_url(string $state): string
{
    return TIKTOK_AUTHORIZE_URL . '?' . http_build_query([
        'client_key' => TIKTOK_CLIENT_KEY,
        'scope' => TIKTOK_SCOPE,
        'response_type' => 'code',
        'redirect_uri' => tiktok_auth_redirect_uri(),
        'state' => $state,
    ]);
}

function facebook_authorize_url(string $state): string
{
    return 'https://www.facebook.com/' . rawurlencode(FACEBOOK_GRAPH_VERSION) . '/dialog/oauth?' . http_build_query([
        'client_id' => FACEBOOK_APP_ID,
        'redirect_uri' => facebook_auth_redirect_uri(),
        'state' => $state,
        'scope' => 'email',
        'response_type' => 'code',
    ]);
}

function facebook_exchange_code_for_profile(string $code): array
{
    if (!facebook_login_enabled()) {
        return social_auth_error_result('Facebook Login nao esta disponivel no momento.', 'setup');
    }

    $code = trim($code);

    if ($code === '') {
        return social_auth_error_result('O Facebook nao retornou um codigo valido.', 'missing_code');
    }

    try {
        $tokenResponse = social_http_request(
            'https://graph.facebook.com/' . rawurlencode(FACEBOOK_GRAPH_VERSION) . '/oauth/access_token?' . http_build_query([
                'client_id' => FACEBOOK_APP_ID,
                'redirect_uri' => facebook_auth_redirect_uri(),
                'client_secret' => FACEBOOK_APP_SECRET,
                'code' => $code,
            ])
        );
        $tokenData = json_decode((string) $tokenResponse['body'], true);
        $accessToken = trim((string) ($tokenData['access_token'] ?? ''));

        if ((int) ($tokenResponse['status'] ?? 0) < 200 || (int) ($tokenResponse['status'] ?? 0) >= 300 || $accessToken === '') {
            return social_auth_error_result('Nao foi possivel obter a autorizacao do Facebook.', 'token_exchange_failed');
        }

        $profileResponse = social_http_request(
            'https://graph.facebook.com/' . rawurlencode(FACEBOOK_GRAPH_VERSION) . '/me?' . http_build_query([
                'fields' => 'id,name,email',
                'access_token' => $accessToken,
            ])
        );
        $profileData = json_decode((string) $profileResponse['body'], true);
        $providerUserId = trim((string) ($profileData['id'] ?? ''));
        $email = normalize_email((string) ($profileData['email'] ?? ''));
        $name = normalize_person_name((string) ($profileData['name'] ?? ''));

        if ((int) ($profileResponse['status'] ?? 0) < 200 || (int) ($profileResponse['status'] ?? 0) >= 300 || $providerUserId === '') {
            return social_auth_error_result('Nao foi possivel recuperar os dados da sua conta do Facebook.', 'profile_fetch_failed');
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return social_auth_error_result('O Facebook nao retornou um email valido para esta conta.', 'missing_email');
        }

        return [
            'success' => true,
            'provider' => 'facebook',
            'provider_user_id' => $providerUserId,
            'email' => $email,
            'name' => $name,
        ];
    } catch (Throwable $exception) {
        error_log('Facebook auth failure: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

        return social_auth_error_result('Nao foi possivel entrar com Facebook agora.', 'exception');
    }
}

function tiktok_exchange_code_for_profile(string $code): array
{
    if (!tiktok_login_enabled()) {
        return social_auth_error_result('Entrar com TikTok nao esta disponivel no momento.', 'setup');
    }

    $code = trim($code);

    if ($code === '') {
        return social_auth_error_result('O TikTok nao retornou um codigo valido.', 'missing_code');
    }

    try {
        $tokenResponse = social_http_form_request(TIKTOK_TOKEN_URL, [
            'client_key' => TIKTOK_CLIENT_KEY,
            'client_secret' => TIKTOK_CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => tiktok_auth_redirect_uri(),
        ]);
        $tokenData = json_decode((string) $tokenResponse['body'], true);
        $accessToken = trim((string) ($tokenData['access_token'] ?? ''));
        $providerUserId = trim((string) ($tokenData['open_id'] ?? ''));

        if ((int) ($tokenResponse['status'] ?? 0) < 200 || (int) ($tokenResponse['status'] ?? 0) >= 300 || $accessToken === '' || $providerUserId === '') {
            return social_auth_error_result('Nao foi possivel obter a autorizacao do TikTok.', 'token_exchange_failed');
        }

        $profileResponse = social_http_request(
            TIKTOK_USER_INFO_URL . '?' . http_build_query([
                'fields' => 'open_id,union_id,avatar_url,display_name',
            ]),
            'GET',
            ['Authorization: Bearer ' . $accessToken]
        );
        $profileData = json_decode((string) $profileResponse['body'], true);
        $user = is_array($profileData['data']['user'] ?? null) ? $profileData['data']['user'] : [];
        $error = is_array($profileData['error'] ?? null) ? $profileData['error'] : [];
        $providerUserId = trim((string) ($user['open_id'] ?? $providerUserId));
        $name = normalize_person_name((string) ($user['display_name'] ?? ''));

        if (
            (int) ($profileResponse['status'] ?? 0) < 200
            || (int) ($profileResponse['status'] ?? 0) >= 300
            || $providerUserId === ''
            || trim((string) ($error['code'] ?? 'ok')) !== 'ok'
        ) {
            return social_auth_error_result('Nao foi possivel recuperar os dados da sua conta do TikTok.', 'profile_fetch_failed');
        }

        return [
            'success' => true,
            'provider' => 'tiktok',
            'provider_user_id' => $providerUserId,
            'email' => '',
            'name' => $name,
        ];
    } catch (Throwable $exception) {
        error_log('TikTok auth failure: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

        return social_auth_error_result('Nao foi possivel entrar com TikTok agora.', 'exception');
    }
}

function apple_auth_redirect_uri(): string
{
    return absolute_app_url('oauth/social_callback.php');
}

function apple_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function apple_base64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;

    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function apple_authorize_url(string $state): string
{
    return 'https://appleid.apple.com/auth/authorize?' . http_build_query([
        'response_type' => 'code',
        'response_mode' => 'form_post',
        'client_id' => APPLE_CLIENT_ID,
        'redirect_uri' => apple_auth_redirect_uri(),
        'scope' => 'name email',
        'state' => $state,
    ]);
}

function apple_ecdsa_der_to_jose(string $signature, int $partLength = 32): string
{
    if ($signature === '' || ord($signature[0]) !== 0x30) {
        throw new RuntimeException('Assinatura Apple em formato inesperado.');
    }

    $offset = 1;
    $sequenceLength = ord($signature[$offset]);
    $offset += 1;

    if (($sequenceLength & 0x80) === 0x80) {
        $bytesToRead = $sequenceLength & 0x7f;
        $sequenceLength = 0;

        for ($index = 0; $index < $bytesToRead; $index += 1) {
            $sequenceLength = ($sequenceLength << 8) | ord($signature[$offset]);
            $offset += 1;
        }
    }

    if (ord($signature[$offset]) !== 0x02) {
        throw new RuntimeException('Assinatura Apple sem componente R.');
    }

    $offset += 1;
    $rLength = ord($signature[$offset]);
    $offset += 1;
    $r = substr($signature, $offset, $rLength);
    $offset += $rLength;

    if (ord($signature[$offset]) !== 0x02) {
        throw new RuntimeException('Assinatura Apple sem componente S.');
    }

    $offset += 1;
    $sLength = ord($signature[$offset]);
    $offset += 1;
    $s = substr($signature, $offset, $sLength);

    $r = str_pad(ltrim($r, "\x00"), $partLength, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), $partLength, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

function apple_generate_client_secret(): string
{
    $header = apple_base64url_encode(json_encode([
        'alg' => 'ES256',
        'kid' => APPLE_KEY_ID,
        'typ' => 'JWT',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $issuedAt = time();
    $payload = apple_base64url_encode(json_encode([
        'iss' => APPLE_TEAM_ID,
        'iat' => $issuedAt,
        'exp' => $issuedAt + 300,
        'aud' => 'https://appleid.apple.com',
        'sub' => APPLE_CLIENT_ID,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $unsignedToken = $header . '.' . $payload;
    $privateKey = openssl_pkey_get_private(apple_private_key_contents());

    if ($privateKey === false) {
        throw new RuntimeException('A chave privada da Apple nao pode ser carregada.');
    }

    $signature = '';

    if (!openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Nao foi possivel assinar o client secret da Apple.');
    }

    return $unsignedToken . '.' . apple_base64url_encode(apple_ecdsa_der_to_jose($signature));
}

function apple_keys_cache_file(): string
{
    return APPLE_KEYS_CACHE_FILE;
}

function apple_fetch_public_keys_from_remote(): array
{
    $response = social_http_request(APPLE_KEYS_URL);
    $data = json_decode((string) $response['body'], true);
    $keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];

    if ((int) ($response['status'] ?? 0) < 200 || (int) ($response['status'] ?? 0) >= 300 || $keys === []) {
        throw new RuntimeException('Nao foi possivel consultar as chaves publicas da Apple.');
    }

    @file_put_contents(
        apple_keys_cache_file(),
        json_encode([
            'expires_at' => time() + APPLE_KEYS_CACHE_TTL,
            'keys' => $keys,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    return $keys;
}

function apple_public_keys(bool $forceRefresh = false): array
{
    $cacheFile = apple_keys_cache_file();

    if (!$forceRefresh && is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);

        if (
            is_array($cached)
            && (int) ($cached['expires_at'] ?? 0) > time()
            && is_array($cached['keys'] ?? null)
            && $cached['keys'] !== []
        ) {
            return $cached['keys'];
        }
    }

    return apple_fetch_public_keys_from_remote();
}

function apple_asn1_length(string $value): string
{
    $length = strlen($value);

    if ($length <= 0x7f) {
        return chr($length);
    }

    $temp = '';

    while ($length > 0) {
        $temp = chr($length & 0xff) . $temp;
        $length >>= 8;
    }

    return chr(0x80 | strlen($temp)) . $temp;
}

function apple_asn1_integer(string $value): string
{
    $value = ltrim($value, "\x00");

    if ($value === '' || ord($value[0]) > 0x7f) {
        $value = "\x00" . $value;
    }

    return "\x02" . apple_asn1_length($value) . $value;
}

function apple_asn1_sequence(string $value): string
{
    return "\x30" . apple_asn1_length($value) . $value;
}

function apple_asn1_bitstring(string $value): string
{
    return "\x03" . apple_asn1_length("\x00" . $value) . "\x00" . $value;
}

function apple_jwk_to_pem(array $key): string
{
    $modulus = apple_base64url_decode((string) ($key['n'] ?? ''));
    $exponent = apple_base64url_decode((string) ($key['e'] ?? ''));

    if ($modulus === false || $exponent === false) {
        throw new RuntimeException('A chave publica da Apple veio em formato invalido.');
    }

    $rsaPublicKey = apple_asn1_sequence(
        apple_asn1_integer($modulus) . apple_asn1_integer($exponent)
    );
    $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');
    $subjectPublicKeyInfo = apple_asn1_sequence(
        $algorithmIdentifier . apple_asn1_bitstring($rsaPublicKey)
    );

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function verify_apple_identity_token(string $idToken): array
{
    $idToken = trim($idToken);

    if ($idToken === '') {
        return social_auth_error_result('Token da Apple ausente.', 'missing_token');
    }

    $parts = explode('.', $idToken);

    if (count($parts) !== 3) {
        return social_auth_error_result('Token da Apple em formato invalido.', 'invalid_token');
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
    $headerJson = apple_base64url_decode($encodedHeader);
    $payloadJson = apple_base64url_decode($encodedPayload);
    $signature = apple_base64url_decode($encodedSignature);

    if ($headerJson === false || $payloadJson === false || $signature === false) {
        return social_auth_error_result('Token da Apple nao pode ser decodificado.', 'invalid_token');
    }

    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);

    if (!is_array($header) || !is_array($payload)) {
        return social_auth_error_result('Token da Apple veio com conteudo invalido.', 'invalid_token');
    }

    $kid = trim((string) ($header['kid'] ?? ''));

    if (($header['alg'] ?? '') !== 'RS256' || $kid === '') {
        return social_auth_error_result('Assinatura da Apple nao e suportada.', 'invalid_signature');
    }

    try {
        $keys = apple_public_keys();
    } catch (Throwable $exception) {
        return social_auth_error_result($exception->getMessage(), 'keys');
    }

    $matchingKey = null;

    foreach ($keys as $key) {
        if (trim((string) ($key['kid'] ?? '')) === $kid) {
            $matchingKey = $key;
            break;
        }
    }

    if (!is_array($matchingKey)) {
        try {
            $keys = apple_public_keys(true);
        } catch (Throwable $exception) {
            return social_auth_error_result($exception->getMessage(), 'keys');
        }

        foreach ($keys as $key) {
            if (trim((string) ($key['kid'] ?? '')) === $kid) {
                $matchingKey = $key;
                break;
            }
        }
    }

    if (!is_array($matchingKey)) {
        return social_auth_error_result('Nao foi possivel encontrar a chave publica da Apple para validar o login.', 'keys');
    }

    try {
        $publicKey = apple_jwk_to_pem($matchingKey);
    } catch (Throwable $exception) {
        return social_auth_error_result($exception->getMessage(), 'keys');
    }

    $verified = openssl_verify(
        $encodedHeader . '.' . $encodedPayload,
        $signature,
        $publicKey,
        OPENSSL_ALGO_SHA256
    );

    if ($verified !== 1) {
        return social_auth_error_result('Falha ao verificar a assinatura do token da Apple.', 'invalid_signature');
    }

    $issuer = trim((string) ($payload['iss'] ?? ''));
    $audience = $payload['aud'] ?? '';
    $audiences = is_array($audience) ? $audience : [$audience];
    $expiresAt = (int) ($payload['exp'] ?? 0);
    $issuedAt = (int) ($payload['iat'] ?? 0);
    $now = time();

    if ($issuer !== 'https://appleid.apple.com') {
        return social_auth_error_result('O emissor do token da Apple e invalido.', 'invalid_issuer');
    }

    if (!in_array(APPLE_CLIENT_ID, $audiences, true)) {
        return social_auth_error_result('O token da Apple nao foi emitido para esta aplicacao.', 'invalid_audience');
    }

    if ($expiresAt <= ($now - 60) || $issuedAt > ($now + 60)) {
        return social_auth_error_result('O token da Apple expirou ou ainda nao e valido.', 'expired');
    }

    $email = normalize_email((string) ($payload['email'] ?? ''));
    $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return social_auth_error_result('A Apple nao retornou um email valido para esta conta.', 'missing_email');
    }

    if (!$emailVerified) {
        return social_auth_error_result('O email retornado pela Apple ainda nao foi verificado.', 'email_not_verified');
    }

    return [
        'success' => true,
        'claims' => $payload,
    ];
}

function apple_exchange_code_for_profile(string $code, string $userPayload = ''): array
{
    if (!apple_login_enabled()) {
        return social_auth_error_result('Entrar com Apple nao esta disponivel no momento.', 'setup');
    }

    $code = trim($code);

    if ($code === '') {
        return social_auth_error_result('A Apple nao retornou um codigo valido.', 'missing_code');
    }

    try {
        $tokenResponse = social_http_form_request('https://appleid.apple.com/auth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => apple_auth_redirect_uri(),
            'client_id' => APPLE_CLIENT_ID,
            'client_secret' => apple_generate_client_secret(),
        ]);
        $tokenData = json_decode((string) $tokenResponse['body'], true);
        $idToken = trim((string) ($tokenData['id_token'] ?? ''));

        if ((int) ($tokenResponse['status'] ?? 0) < 200 || (int) ($tokenResponse['status'] ?? 0) >= 300 || $idToken === '') {
            return social_auth_error_result('Nao foi possivel obter a autorizacao da Apple.', 'token_exchange_failed');
        }

        $verification = verify_apple_identity_token($idToken);

        if (!$verification['success']) {
            return $verification;
        }

        $claims = $verification['claims'];
        $name = '';

        if ($userPayload !== '') {
            $user = json_decode($userPayload, true);
            $firstName = trim((string) ($user['name']['firstName'] ?? ''));
            $lastName = trim((string) ($user['name']['lastName'] ?? ''));
            $name = normalize_person_name(trim($firstName . ' ' . $lastName));
        }

        if ($name === '') {
            $name = normalize_person_name((string) ($claims['name'] ?? ''));
        }

        return [
            'success' => true,
            'provider' => 'apple',
            'provider_user_id' => trim((string) ($claims['sub'] ?? '')),
            'email' => normalize_email((string) ($claims['email'] ?? '')),
            'name' => $name,
        ];
    } catch (Throwable $exception) {
        error_log('Apple auth failure: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

        return social_auth_error_result('Nao foi possivel entrar com Apple agora.', 'exception');
    }
}
