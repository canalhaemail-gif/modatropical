<?php
declare(strict_types=1);

function customer_password_reset_hash_column(): string
{
    static $column = null;

    if (is_string($column)) {
        return $column;
    }

    $statement = db()->query("SHOW COLUMNS FROM cliente_password_resets LIKE 'codigo_hash'");
    $column = $statement->fetch() ? 'codigo_hash' : 'token_hash';

    return $column;
}

function customer_password_reset_has_token_column(): bool
{
    static $hasColumn = null;

    if (is_bool($hasColumn)) {
        return $hasColumn;
    }

    $statement = db()->query("SHOW COLUMNS FROM cliente_password_resets LIKE 'token_hash'");
    $hasColumn = (bool) $statement->fetch();

    return $hasColumn;
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function is_customer_logged_in(): bool
{
    return !empty($_SESSION['customer_id']);
}

function current_admin(): ?array
{
    static $admin = null;

    if ($admin !== null) {
        return $admin;
    }

    if (!is_admin_logged_in()) {
        return null;
    }

    $statement = db()->prepare('SELECT id, nome, email, criado_em FROM admins WHERE id = :id LIMIT 1');
    $statement->execute(['id' => (int) $_SESSION['admin_id']]);
    $admin = $statement->fetch() ?: null;

    return $admin;
}

function current_customer(): ?array
{
    static $customer = null;

    if ($customer !== null) {
        return $customer;
    }

    if (!is_customer_logged_in()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, nome, email, telefone, cep, endereco, cpf, data_nascimento, email_verificado_em, ativo, criado_em
         FROM clientes
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => (int) $_SESSION['customer_id']]);
    $customer = $statement->fetch() ?: null;

    return $customer;
}

function find_customer_by_email(string $email): ?array
{
    $statement = db()->prepare('SELECT * FROM clientes WHERE email = :email LIMIT 1');
    $statement->execute(['email' => normalize_email($email)]);
    $customer = $statement->fetch();

    return $customer ?: null;
}

function find_customer(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM clientes WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $customer = $statement->fetch();

    return $customer ?: null;
}

function require_admin_auth(): void
{
    if (!is_admin_logged_in()) {
        set_flash('error', 'Faca login para acessar o painel.');
        redirect('admin/login.php');
    }

    if (current_admin() === null) {
        logout_admin();
        set_flash('error', 'Faca login para acessar o painel.');
        redirect('admin/login.php');
    }
}

function require_admin_guest(): void
{
    if (!is_admin_logged_in()) {
        return;
    }

    if (current_admin() !== null) {
        redirect('admin/index.php');
    }

    logout_admin();
}

function require_customer_auth(): void
{
    if (!is_customer_logged_in()) {
        set_flash('error', 'Entre na sua conta para continuar.');
        redirect('entrar.php');
    }

    $customer = current_customer();

    if ($customer === null || (int) $customer['ativo'] !== 1) {
        logout_customer();
        set_flash('error', 'Sua sessao expirou. Entre novamente.');
        redirect('entrar.php');
    }

    if (empty($customer['email_verificado_em'])) {
        $email = (string) ($customer['email'] ?? '');
        logout_customer();

        if ($email !== '') {
            $_SESSION['pending_verification_email'] = $email;
        }

        set_flash('error', 'Confirme seu email para acessar a conta.');
        redirect('verificar-email.php' . ($email !== '' ? '?email=' . rawurlencode($email) : ''));
    }
}

function require_customer_guest(): void
{
    if (!is_customer_logged_in()) {
        return;
    }

    $customer = current_customer();

    if (
        $customer !== null
        && (int) $customer['ativo'] === 1
        && !empty($customer['email_verificado_em'])
    ) {
        redirect('');
    }

    logout_customer();
}

function login_admin_session(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) ($admin['id'] ?? 0);
}

function admin_remember_tokens_table_ready(): bool
{
    static $ready = null;

    if (is_bool($ready)) {
        return $ready;
    }

    $ready = table_exists('admin_remember_tokens');

    return $ready;
}

function admin_remember_cookie_name(): string
{
    return 'mt_admin_remember';
}

function admin_remember_cookie_lifetime(): int
{
    return 60 * 60 * 24 * 30;
}

function admin_remember_cookie_options(int $expiresAt): array
{
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    return [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function set_admin_remember_cookie(string $token, int $expiresAt): void
{
    setcookie(admin_remember_cookie_name(), $token, admin_remember_cookie_options($expiresAt));
}

function clear_admin_remember_cookie(): void
{
    setcookie(admin_remember_cookie_name(), '', admin_remember_cookie_options(time() - 3600));
}

function create_admin_remember_token(int $adminId): array
{
    $token = bin2hex(random_bytes(32));
    $expiresAt = time() + admin_remember_cookie_lifetime();
    $expiresAtSql = date('Y-m-d H:i:s', $expiresAt);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);

    $statement = db()->prepare(
        'INSERT INTO admin_remember_tokens (admin_id, token_hash, criado_em, expira_em, user_agent, ip)
         VALUES (:admin_id, :token_hash, NOW(), :expira_em, :user_agent, :ip)'
    );
    $statement->execute([
        'admin_id' => $adminId,
        'token_hash' => hash('sha256', $token),
        'expira_em' => $expiresAtSql,
        'user_agent' => $userAgent,
        'ip' => $ipAddress,
    ]);

    return ['token' => $token, 'expires_at' => $expiresAt];
}

function issue_admin_remember_token(int $adminId): void
{
    if (!admin_remember_tokens_table_ready()) {
        clear_admin_remember_cookie();
        return;
    }

    $payload = create_admin_remember_token($adminId);
    set_admin_remember_cookie($payload['token'], (int) $payload['expires_at']);
}

function find_admin_remember_token(string $token): ?array
{
    if (!admin_remember_tokens_table_ready()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, admin_id, expira_em
         FROM admin_remember_tokens
         WHERE token_hash = :token_hash
         LIMIT 1'
    );
    $statement->execute(['token_hash' => hash('sha256', $token)]);

    return $statement->fetch() ?: null;
}

function delete_admin_remember_token_by_id(int $tokenId): void
{
    if ($tokenId <= 0 || !admin_remember_tokens_table_ready()) {
        return;
    }

    $statement = db()->prepare('DELETE FROM admin_remember_tokens WHERE id = :id');
    $statement->execute(['id' => $tokenId]);
}

function delete_admin_remember_tokens_by_admin(int $adminId): void
{
    if ($adminId <= 0 || !admin_remember_tokens_table_ready()) {
        return;
    }

    $statement = db()->prepare('DELETE FROM admin_remember_tokens WHERE admin_id = :admin_id');
    $statement->execute(['admin_id' => $adminId]);
}

function attempt_admin_remember_login(): void
{
    if (is_admin_logged_in()) {
        return;
    }

    $cookie = trim((string) ($_COOKIE[admin_remember_cookie_name()] ?? ''));

    if ($cookie === '') {
        return;
    }

    if (!admin_remember_tokens_table_ready()) {
        clear_admin_remember_cookie();
        return;
    }

    $remember = find_admin_remember_token($cookie);

    if (!$remember) {
        clear_admin_remember_cookie();
        return;
    }

    $expiresAt = strtotime((string) $remember['expira_em']);

    if ($expiresAt !== false && $expiresAt < time()) {
        delete_admin_remember_token_by_id((int) $remember['id']);
        clear_admin_remember_cookie();
        return;
    }

    $statement = db()->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
    $statement->execute(['id' => (int) $remember['admin_id']]);
    $admin = $statement->fetch();

    if (!$admin) {
        delete_admin_remember_token_by_id((int) $remember['id']);
        clear_admin_remember_cookie();
        return;
    }

    login_admin_session($admin);
    delete_admin_remember_token_by_id((int) $remember['id']);
    issue_admin_remember_token((int) $admin['id']);
}

function attempt_admin_login_result(string $email, string $password, bool $remember = false): array
{
    $statement = db()->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
    $statement->execute(['email' => normalize_email($email)]);
    $admin = $statement->fetch();

    if (!$admin || !password_verify($password, $admin['senha_hash'])) {
        return ['success' => false, 'reason' => 'invalid', 'admin' => null];
    }

    login_admin_session($admin);

    if ($remember) {
        issue_admin_remember_token((int) $admin['id']);
    } else {
        clear_admin_remember_cookie();
    }

    return ['success' => true, 'reason' => 'ok', 'admin' => $admin];
}

function attempt_admin_login(string $email, string $password, bool $remember = false): bool
{
    return (bool) (attempt_admin_login_result($email, $password, $remember)['success'] ?? false);
}

function customer_can_use_password_login(array $customer): bool
{
    $passwordHash = trim((string) ($customer['senha_hash'] ?? ''));

    return $passwordHash !== '';
}

function social_placeholder_email_domain(): string
{
    return 'social.modatropical.local';
}

function social_placeholder_email(string $provider, string $providerUserId): string
{
    $provider = trim(strtolower($provider));
    $providerUserId = trim($providerUserId);
    $hash = substr(hash('sha256', $provider . '|' . $providerUserId), 0, 24);

    return $provider . '-' . $hash . '@' . social_placeholder_email_domain();
}

function customer_email_requires_completion(?string $email): bool
{
    $email = normalize_email($email);

    return $email === '' || str_ends_with($email, '@' . social_placeholder_email_domain());
}

function login_customer_session(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) ($customer['id'] ?? 0);
    unset($_SESSION['storefront_checkout'], $_SESSION['storefront_coupon_code']);
}

function customer_remember_cookie_name(): string
{
    return 'mt_remember';
}

function customer_remember_cookie_lifetime(): int
{
    return 60 * 60 * 24 * 30;
}

function customer_remember_cookie_options(int $expiresAt): array
{
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    return [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function set_customer_remember_cookie(string $token, int $expiresAt): void
{
    setcookie(customer_remember_cookie_name(), $token, customer_remember_cookie_options($expiresAt));
}

function clear_customer_remember_cookie(): void
{
    setcookie(customer_remember_cookie_name(), '', customer_remember_cookie_options(time() - 3600));
}

function create_customer_remember_token(int $customerId): array
{
    $token = bin2hex(random_bytes(32));
    $expiresAt = time() + customer_remember_cookie_lifetime();
    $expiresAtSql = date('Y-m-d H:i:s', $expiresAt);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);

    $statement = db()->prepare(
        'INSERT INTO cliente_remember_tokens (cliente_id, token_hash, criado_em, expira_em, user_agent, ip)
         VALUES (:cliente_id, :token_hash, NOW(), :expira_em, :user_agent, :ip)'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'token_hash' => hash('sha256', $token),
        'expira_em' => $expiresAtSql,
        'user_agent' => $userAgent,
        'ip' => $ipAddress,
    ]);

    return ['token' => $token, 'expires_at' => $expiresAt];
}

function issue_customer_remember_token(int $customerId): void
{
    $payload = create_customer_remember_token($customerId);
    set_customer_remember_cookie($payload['token'], (int) $payload['expires_at']);
}

function find_customer_remember_token(string $token): ?array
{
    $statement = db()->prepare(
        'SELECT id, cliente_id, expira_em
         FROM cliente_remember_tokens
         WHERE token_hash = :token_hash
         LIMIT 1'
    );
    $statement->execute(['token_hash' => hash('sha256', $token)]);

    return $statement->fetch() ?: null;
}

function delete_customer_remember_token_by_id(int $tokenId): void
{
    $statement = db()->prepare('DELETE FROM cliente_remember_tokens WHERE id = :id');
    $statement->execute(['id' => $tokenId]);
}

function delete_customer_remember_tokens_by_customer(int $customerId): void
{
    $statement = db()->prepare('DELETE FROM cliente_remember_tokens WHERE cliente_id = :cliente_id');
    $statement->execute(['cliente_id' => $customerId]);
}

function attempt_customer_remember_login(): void
{
    if (is_customer_logged_in()) {
        return;
    }

    $cookie = trim((string) ($_COOKIE[customer_remember_cookie_name()] ?? ''));

    if ($cookie === '') {
        return;
    }

    $remember = find_customer_remember_token($cookie);

    if (!$remember) {
        clear_customer_remember_cookie();
        return;
    }

    $expiresAt = strtotime((string) $remember['expira_em']);

    if ($expiresAt !== false && $expiresAt < time()) {
        delete_customer_remember_token_by_id((int) $remember['id']);
        clear_customer_remember_cookie();
        return;
    }

    $customer = find_customer((int) $remember['cliente_id']);

    if (!$customer || (int) ($customer['ativo'] ?? 0) !== 1 || empty($customer['email_verificado_em'])) {
        delete_customer_remember_token_by_id((int) $remember['id']);
        clear_customer_remember_cookie();
        return;
    }

    login_customer_session($customer);
    delete_customer_remember_token_by_id((int) $remember['id']);
    issue_customer_remember_token((int) $customer['id']);
}

function finalize_customer_login_result(array $customer, bool $remember = false): array
{
    if ((int) ($customer['ativo'] ?? 0) !== 1) {
        return ['success' => false, 'reason' => 'inactive', 'customer' => $customer];
    }

    if (empty($customer['email_verificado_em'])) {
        return ['success' => false, 'reason' => 'unverified', 'customer' => $customer];
    }

    login_customer_session($customer);

    if ($remember) {
        issue_customer_remember_token((int) $customer['id']);
    }

    return ['success' => true, 'reason' => 'ok', 'customer' => $customer];
}

function customer_email_exists(string $email, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM clientes WHERE email = :email';
    $params = ['email' => normalize_email($email)];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function customer_cpf_exists(string $cpf, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM clientes WHERE cpf = :cpf';
    $params = ['cpf' => normalize_cpf($cpf)];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function register_customer(
    string $name,
    string $email,
    string $phone,
    string $cep,
    string $address,
    string $cpf,
    string $birthDate,
    string $password
): int
{
    $statement = db()->prepare(
        'INSERT INTO clientes (
            nome, email, telefone, cep, endereco, cpf, data_nascimento, senha_hash, email_verificado_em, ativo
         ) VALUES (
            :nome, :email, :telefone, :cep, :endereco, :cpf, :data_nascimento, :senha_hash, NULL, 1
         )'
    );

    $statement->execute([
        'nome' => normalize_person_name($name),
        'email' => normalize_email($email),
        'telefone' => digits_only($phone),
        'cep' => normalize_cep($cep),
        'endereco' => trim($address),
        'cpf' => normalize_cpf($cpf),
        'data_nascimento' => trim($birthDate),
        'senha_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return (int) db()->lastInsertId();
}

function attempt_customer_login_result(string $email, string $password, bool $remember = false): array
{
    $customer = find_customer_by_email($email);

    if (
        !$customer
        || !customer_can_use_password_login($customer)
        || !password_verify($password, (string) $customer['senha_hash'])
    ) {
        return ['success' => false, 'reason' => 'invalid', 'customer' => null];
    }

    return finalize_customer_login_result($customer, $remember);
}

function attempt_customer_login(string $email, string $password): bool
{
    return (bool) (attempt_customer_login_result($email, $password)['success'] ?? false);
}

function send_customer_password_reset_code_email(array $customer, string $code, ?string $token = null): array
{
    $subject = 'Codigo para redefinir sua senha';
    $storeName = store_setting('nome_estabelecimento', APP_NAME);
    $customerName = $customer['nome'] ?? 'cliente';
    $resetUrl = $token !== null && $token !== ''
        ? absolute_app_url('redefinir-senha.php?email=' . urlencode((string) ($customer['email'] ?? '')) . '&token=' . urlencode($token))
        : '';
    $resetButton = $resetUrl !== ''
        ? '
                <div style="margin:24px 0 20px;text-align:center;">
                    <a href="' . e($resetUrl) . '" style="display:inline-block;padding:14px 26px;border-radius:999px;background:#D97A6C;color:#ffffff;text-decoration:none;font-weight:700;">Redefinir senha agora</a>
                </div>
                <p style="margin:0 0 18px;color:#5f586a;line-height:1.6;">Se preferir, voce tambem pode digitar o codigo manualmente na tela de redefinicao.</p>
        '
        : '';
    $resetText = $resetUrl !== ''
        ? "Ou acesse este link para redefinir direto:\n{$resetUrl}\n\n"
        : '';

    $htmlBody = '
        <div style="font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;padding:24px;">
            <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:20px;padding:32px;">
                <p style="margin:0 0 12px;color:#4d137a;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">recuperacao de senha</p>
                <h1 style="margin:0 0 16px;color:#1e1a25;font-size:28px;">Ola, ' . e((string) $customerName) . '</h1>
                <p style="margin:0 0 18px;color:#5f586a;line-height:1.6;">Use o codigo abaixo para redefinir a senha da sua conta em ' . e($storeName) . '.</p>
                <div style="margin:24px 0;padding:18px 20px;border-radius:16px;background:#f6f1ff;color:#4d137a;font-size:32px;font-weight:700;letter-spacing:0.28em;text-align:center;">' . e($code) . '</div>
                ' . $resetButton . '
                <p style="margin:0 0 10px;color:#5f586a;line-height:1.6;">Esse codigo expira em 15 minutos.</p>
                <p style="margin:0;color:#5f586a;line-height:1.6;">Se voce nao solicitou essa redefinicao, ignore este email.</p>
            </div>
        </div>
    ';

    $textBody = "Ola, {$customerName}\n\n";
    $textBody .= "Seu codigo para redefinir a senha em {$storeName} e: {$code}\n";
    $textBody .= $resetText;
    $textBody .= "Esse codigo expira em 15 minutos.\n";

    return send_email_message(
        (string) $customer['email'],
        (string) $customerName,
        $subject,
        $htmlBody,
        $textBody
    );
}

function request_customer_password_reset_code(string $email): array
{
    $hashColumn = customer_password_reset_hash_column();
    $hasTokenColumn = customer_password_reset_has_token_column();
    $statement = db()->prepare(
        'SELECT id, nome, email
         FROM clientes
         WHERE email = :email
           AND ativo = 1
         LIMIT 1'
    );
    $statement->execute(['email' => normalize_email($email)]);
    $customer = $statement->fetch();

    db()->prepare(
        'DELETE FROM cliente_password_resets
         WHERE usado_em IS NOT NULL
            OR expira_em < NOW()'
    )->execute();

    if (!$customer) {
        return [
            'success' => true,
            'delivered' => false,
            'logged' => false,
            'log_path' => null,
        ];
    }

    $customerId = (int) $customer['id'];
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $token = bin2hex(random_bytes(32));
    $codeHash = hash('sha256', $code);
    $expiresAt = db_future_datetime(900);

    db()->prepare('DELETE FROM cliente_password_resets WHERE cliente_id = :cliente_id')->execute([
        'cliente_id' => $customerId,
    ]);

    $columns = ['cliente_id', $hashColumn, 'expira_em'];
    $placeholders = [':cliente_id', ':hash_value', ':expira_em'];
    $params = [
        'cliente_id' => $customerId,
        'hash_value' => $codeHash,
        'expira_em' => $expiresAt,
    ];

    if ($hasTokenColumn) {
        $columns[] = 'token_hash';
        $placeholders[] = ':token_hash';
        $params['token_hash'] = hash('sha256', $token);
    }

    $insert = db()->prepare(
        'INSERT INTO cliente_password_resets (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $placeholders) . ')'
    );
    $insert->execute($params);

    $sendResult = send_customer_password_reset_code_email($customer, $code, $hasTokenColumn ? $token : null);

    if (!$sendResult['success']) {
        db()->prepare('DELETE FROM cliente_password_resets WHERE cliente_id = :cliente_id')->execute([
            'cliente_id' => $customerId,
        ]);
    }

    return $sendResult;
}

function find_customer_password_reset_by_code(string $email, string $code): ?array
{
    $hashColumn = customer_password_reset_hash_column();
    $statement = db()->prepare(
        'SELECT r.id, r.cliente_id, r.expira_em, c.nome, c.email
         FROM cliente_password_resets r
         INNER JOIN clientes c ON c.id = r.cliente_id
         WHERE c.email = :email
           AND r.' . $hashColumn . ' = :hash_value
           AND r.usado_em IS NULL
           AND r.expira_em >= NOW()
           AND c.ativo = 1
         LIMIT 1'
    );
    $statement->execute([
        'email' => normalize_email($email),
        'hash_value' => hash('sha256', trim($code)),
    ]);
    $reset = $statement->fetch();

    return $reset ?: null;
}

function find_customer_password_reset_by_token(string $email, string $token): ?array
{
    if (!customer_password_reset_has_token_column()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT r.id, r.cliente_id, r.expira_em, c.nome, c.email
         FROM cliente_password_resets r
         INNER JOIN clientes c ON c.id = r.cliente_id
         WHERE c.email = :email
           AND r.token_hash = :token_hash
           AND r.usado_em IS NULL
           AND r.expira_em >= NOW()
           AND c.ativo = 1
         LIMIT 1'
    );
    $statement->execute([
        'email' => normalize_email($email),
        'token_hash' => hash('sha256', trim($token)),
    ]);
    $reset = $statement->fetch();

    return $reset ?: null;
}

function complete_customer_password_reset(array $reset, string $password): bool
{
    $customerId = (int) ($reset['cliente_id'] ?? 0);

    if ($customerId <= 0) {
        return false;
    }

    $updateCustomer = db()->prepare(
        'UPDATE clientes
         SET senha_hash = :senha_hash
         WHERE id = :id'
    );
    $updateCustomer->execute([
        'senha_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $customerId,
    ]);

    db()->prepare(
        'UPDATE cliente_password_resets
         SET usado_em = NOW()
         WHERE cliente_id = :cliente_id'
    )->execute([
        'cliente_id' => $customerId,
    ]);

    return true;
}

function reset_customer_password_with_code(string $email, string $code, string $password): bool
{
    $reset = find_customer_password_reset_by_code($email, $code);

    if (!$reset) {
        return false;
    }

    return complete_customer_password_reset($reset, $password);
}

function reset_customer_password_with_token(string $email, string $token, string $password): bool
{
    $reset = find_customer_password_reset_by_token($email, $token);

    if (!$reset) {
        return false;
    }

    return complete_customer_password_reset($reset, $password);
}

function logout_admin(): void
{
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);

    if ($adminId > 0) {
        delete_admin_remember_tokens_by_admin($adminId);
    }

    clear_admin_remember_cookie();
}

function logout_customer(): void
{
    $customerId = (int) ($_SESSION['customer_id'] ?? 0);
    unset(
        $_SESSION['customer_id'],
        $_SESSION['storefront_cart'],
        $_SESSION['storefront_checkout'],
        $_SESSION['storefront_coupon_code']
    );
    session_regenerate_id(true);

    if ($customerId > 0) {
        delete_customer_remember_tokens_by_customer($customerId);
    }

    clear_customer_remember_cookie();
}

function customer_profile_is_complete(?array $customer = null): bool
{
    $customer = $customer ?? current_customer();

    if (!$customer) {
        return false;
    }

    $name = normalize_person_name((string) ($customer['nome'] ?? ''));
    $email = normalize_email((string) ($customer['email'] ?? ''));
    $phone = digits_only((string) ($customer['telefone'] ?? ''));
    $cpf = normalize_cpf((string) ($customer['cpf'] ?? ''));
    $birthDate = trim((string) ($customer['data_nascimento'] ?? ''));
    $cep = normalize_cep((string) ($customer['cep'] ?? ''));
    $address = trim((string) ($customer['endereco'] ?? ''));

    return $name !== ''
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && !customer_email_requires_completion($email)
        && strlen($phone) >= 10
        && is_valid_cpf($cpf)
        && is_valid_birth_date($birthDate)
        && is_valid_cep($cep)
        && $address !== '';
}
