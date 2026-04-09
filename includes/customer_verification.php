<?php
declare(strict_types=1);

require_once __DIR__ . '/customer_messages.php';

function send_customer_email_verification_email(array $customer, string $code, string $token): array
{
    $storeName = store_setting('nome_estabelecimento', APP_NAME);
    $customerName = $customer['nome'] ?? 'cliente';
    $confirmationUrl = absolute_app_url('verificar-email.php?token=' . urlencode($token));
    $subject = 'Confirme seu email';

    $htmlBody = '
        <div style="font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;padding:24px;">
            <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:20px;padding:32px;">
                <p style="margin:0 0 12px;color:#4d137a;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">confirmacao de email</p>
                <h1 style="margin:0 0 16px;color:#1e1a25;font-size:28px;">Ola, ' . e((string) $customerName) . '</h1>
                <p style="margin:0 0 18px;color:#5f586a;line-height:1.6;">Use o codigo abaixo para confirmar seu email em ' . e($storeName) . ' ou clique no botao para validar automaticamente.</p>
                <div style="margin:24px 0;padding:18px 20px;border-radius:16px;background:#f6f1ff;color:#4d137a;font-size:32px;font-weight:700;letter-spacing:0.28em;text-align:center;">' . e($code) . '</div>
                <div style="margin:24px 0 20px;text-align:center;">
                    <a href="' . e($confirmationUrl) . '" style="display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 24px;border-radius:999px;background:#4d137a;color:#ffffff;text-decoration:none;font-weight:700;">Confirmar email</a>
                </div>
                <p style="margin:0 0 10px;color:#5f586a;line-height:1.6;">Esse codigo e esse link expiram em 30 minutos.</p>
                <p style="margin:0;color:#5f586a;line-height:1.6;">Se voce nao criou essa conta, ignore este email.</p>
            </div>
        </div>
    ';

    $textBody = "Ola, {$customerName}\n\n";
    $textBody .= "Seu codigo para confirmar o email em {$storeName} e: {$code}\n";
    $textBody .= "Ou abra este link: {$confirmationUrl}\n";
    $textBody .= "Esse codigo expira em 30 minutos.\n";

    return send_email_message(
        (string) $customer['email'],
        (string) $customerName,
        $subject,
        $htmlBody,
        $textBody
    );
}

function request_customer_email_verification(string $email): array
{
    $statement = db()->prepare(
        'SELECT id, nome, email, ativo, email_verificado_em
         FROM clientes
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => normalize_email($email)]);
    $customer = $statement->fetch();

    db()->prepare(
        'DELETE FROM cliente_email_verificacoes
         WHERE usado_em IS NOT NULL
            OR expira_em < NOW()'
    )->execute();

    if (!$customer || (int) $customer['ativo'] !== 1) {
        return [
            'success' => true,
            'delivered' => false,
            'logged' => false,
            'log_path' => null,
            'already_verified' => false,
        ];
    }

    if (!empty($customer['email_verificado_em'])) {
        return [
            'success' => true,
            'delivered' => false,
            'logged' => false,
            'log_path' => null,
            'already_verified' => true,
        ];
    }

    $customerId = (int) $customer['id'];
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $token = bin2hex(random_bytes(24));
    $expiresAt = db_future_datetime(1800);

    db()->prepare('DELETE FROM cliente_email_verificacoes WHERE cliente_id = :cliente_id')->execute([
        'cliente_id' => $customerId,
    ]);

    $insert = db()->prepare(
        'INSERT INTO cliente_email_verificacoes (
            cliente_id, codigo_hash, token_hash, expira_em
         ) VALUES (
            :cliente_id, :codigo_hash, :token_hash, :expira_em
         )'
    );
    $insert->execute([
        'cliente_id' => $customerId,
        'codigo_hash' => hash('sha256', $code),
        'token_hash' => hash('sha256', $token),
        'expira_em' => $expiresAt,
    ]);

    $sendResult = send_customer_email_verification_email($customer, $code, $token);

    if (!$sendResult['success']) {
        db()->prepare('DELETE FROM cliente_email_verificacoes WHERE cliente_id = :cliente_id')->execute([
            'cliente_id' => $customerId,
        ]);
    }

    return $sendResult + ['already_verified' => false];
}

function verify_customer_email_with_code(string $email, string $code): bool
{
    $statement = db()->prepare(
        'SELECT v.id, v.cliente_id
         FROM cliente_email_verificacoes v
         INNER JOIN clientes c ON c.id = v.cliente_id
         WHERE c.email = :email
           AND c.ativo = 1
           AND c.email_verificado_em IS NULL
           AND v.codigo_hash = :codigo_hash
           AND v.usado_em IS NULL
           AND v.expira_em >= NOW()
         LIMIT 1'
    );
    $statement->execute([
        'email' => normalize_email($email),
        'codigo_hash' => hash('sha256', trim($code)),
    ]);
    $verification = $statement->fetch();

    if (!$verification) {
        return false;
    }

    db()->prepare(
        'UPDATE clientes
         SET email_verificado_em = NOW()
         WHERE id = :id'
    )->execute([
        'id' => (int) $verification['cliente_id'],
    ]);

    db()->prepare(
        'UPDATE cliente_email_verificacoes
         SET usado_em = NOW()
         WHERE cliente_id = :cliente_id'
    )->execute([
        'cliente_id' => (int) $verification['cliente_id'],
    ]);

    $customerStatement = db()->prepare(
        'SELECT id, nome, email
         FROM clientes
         WHERE id = :id
         LIMIT 1'
    );
    $customerStatement->execute(['id' => (int) $verification['cliente_id']]);
    $customer = $customerStatement->fetch();

    if ($customer) {
        customer_send_welcome_message($customer);
    }

    return true;
}

function verify_customer_email_with_token(string $token): bool
{
    $statement = db()->prepare(
        'SELECT v.id, v.cliente_id
         FROM cliente_email_verificacoes v
         INNER JOIN clientes c ON c.id = v.cliente_id
         WHERE c.ativo = 1
           AND c.email_verificado_em IS NULL
           AND v.token_hash = :token_hash
           AND v.usado_em IS NULL
           AND v.expira_em >= NOW()
         LIMIT 1'
    );
    $statement->execute([
        'token_hash' => hash('sha256', trim($token)),
    ]);
    $verification = $statement->fetch();

    if (!$verification) {
        return false;
    }

    db()->prepare(
        'UPDATE clientes
         SET email_verificado_em = NOW()
         WHERE id = :id'
    )->execute([
        'id' => (int) $verification['cliente_id'],
    ]);

    db()->prepare(
        'UPDATE cliente_email_verificacoes
         SET usado_em = NOW()
         WHERE cliente_id = :cliente_id'
    )->execute([
        'cliente_id' => (int) $verification['cliente_id'],
    ]);

    $customerStatement = db()->prepare(
        'SELECT id, nome, email
         FROM clientes
         WHERE id = :id
         LIMIT 1'
    );
    $customerStatement->execute(['id' => (int) $verification['cliente_id']]);
    $customer = $customerStatement->fetch();

    if ($customer) {
        customer_send_welcome_message($customer);
    }

    return true;
}

function send_customer_email_change_code_email(array $customer, string $code, string $newEmail): array
{
    $storeName = store_setting('nome_estabelecimento', APP_NAME);
    $customerName = $customer['nome'] ?? 'cliente';
    $subject = 'Codigo para alterar seu email';

    $htmlBody = '
        <div style="font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;padding:24px;">
            <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:20px;padding:32px;">
                <p style="margin:0 0 12px;color:#4d137a;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">alteracao de email</p>
                <h1 style="margin:0 0 16px;color:#1e1a25;font-size:28px;">Ola, ' . e((string) $customerName) . '</h1>
                <p style="margin:0 0 18px;color:#5f586a;line-height:1.6;">Recebemos uma solicitacao para trocar o email da sua conta em ' . e($storeName) . ' para <strong>' . e($newEmail) . '</strong>.</p>
                <p style="margin:0 0 18px;color:#5f586a;line-height:1.6;">Use este codigo para autorizar a mudanca:</p>
                <div style="margin:24px 0;padding:18px 20px;border-radius:16px;background:#f6f1ff;color:#4d137a;font-size:32px;font-weight:700;letter-spacing:0.28em;text-align:center;">' . e($code) . '</div>
                <p style="margin:0 0 10px;color:#5f586a;line-height:1.6;">Esse codigo expira em 15 minutos.</p>
                <p style="margin:0;color:#5f586a;line-height:1.6;">Se voce nao pediu essa alteracao, ignore este email e mantenha sua conta como esta.</p>
            </div>
        </div>
    ';

    $textBody = "Ola, {$customerName}\n\n";
    $textBody .= "Recebemos um pedido para trocar o email da sua conta em {$storeName} para {$newEmail}.\n";
    $textBody .= "Codigo de autorizacao: {$code}\n";
    $textBody .= "Esse codigo expira em 15 minutos.\n";

    return send_email_message(
        (string) $customer['email'],
        (string) $customerName,
        $subject,
        $htmlBody,
        $textBody
    );
}

function find_customer_email_change_request(int $customerId): ?array
{
    db()->prepare(
        'DELETE FROM cliente_email_alteracoes
         WHERE usado_em IS NOT NULL
            OR expira_em < NOW()'
    )->execute();

    $statement = db()->prepare(
        'SELECT *
         FROM cliente_email_alteracoes
         WHERE cliente_id = :cliente_id
           AND usado_em IS NULL
           AND expira_em >= NOW()
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute(['cliente_id' => $customerId]);
    $change = $statement->fetch();

    return $change ?: null;
}

function request_customer_email_change_code(array $customer, string $newEmail): array
{
    $customerId = (int) ($customer['id'] ?? 0);
    $newEmail = normalize_email($newEmail);

    db()->prepare('DELETE FROM cliente_email_alteracoes WHERE cliente_id = :cliente_id')->execute([
        'cliente_id' => $customerId,
    ]);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = db_future_datetime(900);

    $insert = db()->prepare(
        'INSERT INTO cliente_email_alteracoes (
            cliente_id, novo_email, codigo_hash, expira_em
         ) VALUES (
            :cliente_id, :novo_email, :codigo_hash, :expira_em
         )'
    );
    $insert->execute([
        'cliente_id' => $customerId,
        'novo_email' => $newEmail,
        'codigo_hash' => hash('sha256', $code),
        'expira_em' => $expiresAt,
    ]);

    $sendResult = send_customer_email_change_code_email($customer, $code, $newEmail);

    if (!$sendResult['success']) {
        db()->prepare('DELETE FROM cliente_email_alteracoes WHERE cliente_id = :cliente_id')->execute([
            'cliente_id' => $customerId,
        ]);
    }

    return $sendResult + ['new_email' => $newEmail];
}

function confirm_customer_email_change_with_code(int $customerId, string $code): array
{
    $statement = db()->prepare(
        'SELECT *
         FROM cliente_email_alteracoes
         WHERE cliente_id = :cliente_id
           AND codigo_hash = :codigo_hash
           AND usado_em IS NULL
           AND expira_em >= NOW()
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'codigo_hash' => hash('sha256', trim($code)),
    ]);
    $change = $statement->fetch();

    if (!$change) {
        return ['success' => false, 'reason' => 'invalid'];
    }

    $newEmail = normalize_email((string) ($change['novo_email'] ?? ''));

    if ($newEmail === '') {
        return ['success' => false, 'reason' => 'invalid'];
    }

    if (customer_email_exists($newEmail, $customerId)) {
        return ['success' => false, 'reason' => 'email_taken'];
    }

    db()->prepare(
        'UPDATE clientes
         SET email = :email,
             email_verificado_em = NULL
         WHERE id = :id'
    )->execute([
        'email' => $newEmail,
        'id' => $customerId,
    ]);

    db()->prepare(
        'UPDATE cliente_email_alteracoes
         SET usado_em = NOW()
         WHERE cliente_id = :cliente_id'
    )->execute([
        'cliente_id' => $customerId,
    ]);

    $verificationResult = request_customer_email_verification($newEmail);

    return [
        'success' => true,
        'new_email' => $newEmail,
        'verification' => $verificationResult,
    ];
}
