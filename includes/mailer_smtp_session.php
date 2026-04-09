<?php
declare(strict_types=1);

const SMTP_SESSION_DEFAULT_MAX_MESSAGES = 30;
const SMTP_SESSION_DEFAULT_MAX_IDLE_SECONDS = 90;
const SMTP_SESSION_DEFAULT_NOOP_AFTER_SECONDS = 15;

function smtp_session_create(array $options = []): array
{
    return [
        'persistent' => array_key_exists('persistent', $options) ? (bool) $options['persistent'] : true,
        'label' => trim((string) ($options['label'] ?? 'default')) ?: 'default',
        'session_id' => trim((string) ($options['session_id'] ?? '')) ?: 'smtp_' . bin2hex(random_bytes(6)),
        'socket' => null,
        'max_messages' => max(1, (int) ($options['max_messages'] ?? SMTP_SESSION_DEFAULT_MAX_MESSAGES)),
        'max_idle_seconds' => max(15, (int) ($options['max_idle_seconds'] ?? SMTP_SESSION_DEFAULT_MAX_IDLE_SECONDS)),
        'noop_after_seconds' => max(0, (int) ($options['noop_after_seconds'] ?? SMTP_SESSION_DEFAULT_NOOP_AFTER_SECONDS)),
        'connection_count' => 0,
        'reconnect_count' => 0,
        'messages_sent_total' => 0,
        'connection_messages_sent' => 0,
        'connected_at' => null,
        'last_used_at' => null,
    ];
}

function smtp_session_is_active(array $session): bool
{
    return isset($session['socket']) && is_resource($session['socket']);
}

function smtp_session_log(string $event, array $context = []): void
{
    $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log('[smtp-session][' . $event . '] ' . ($encoded !== false ? $encoded : '{}'));
}

function smtp_session_meta(array $session, array $extra = []): array
{
    return $extra + [
        'session_id' => (string) ($session['session_id'] ?? ''),
        'label' => (string) ($session['label'] ?? 'default'),
        'persistent' => !empty($session['persistent']),
        'connection_count' => (int) ($session['connection_count'] ?? 0),
        'reconnect_count' => (int) ($session['reconnect_count'] ?? 0),
        'messages_sent_total' => (int) ($session['messages_sent_total'] ?? 0),
        'connection_messages_sent' => (int) ($session['connection_messages_sent'] ?? 0),
    ];
}

function smtp_session_config(): array
{
    $host = trim(MAIL_SMTP_HOST);

    if ($host === '') {
        throw new RuntimeException('MAIL_SMTP_HOST nao configurado.');
    }

    return [
        'host' => $host,
        'port' => (int) MAIL_SMTP_PORT,
        'security' => strtolower(trim((string) MAIL_SMTP_SECURITY)),
        'timeout' => max(5, (int) MAIL_SMTP_TIMEOUT),
        'ehlo' => trim((string) MAIL_SMTP_EHLO),
        'username' => (string) MAIL_SMTP_USERNAME,
        'password' => (string) MAIL_SMTP_PASSWORD,
        'from_address' => trim((string) MAIL_FROM_ADDRESS),
    ];
}

function smtp_session_open_socket(array $config)
{
    $connectHost = smtp_resolve_connect_host((string) $config['host']);
    $transport = (string) $config['security'] === 'ssl'
        ? 'ssl://' . $connectHost
        : 'tcp://' . $connectHost;
    $contextOptions = [];

    if (in_array((string) $config['security'], ['ssl', 'tls'], true)) {
        $contextOptions['ssl'] = [
            'peer_name' => (string) $config['host'],
            'SNI_enabled' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ];
    }

    $socket = @stream_socket_client(
        $transport . ':' . (int) $config['port'],
        $errorNumber,
        $errorMessage,
        (int) $config['timeout'],
        STREAM_CLIENT_CONNECT,
        stream_context_create($contextOptions)
    );

    if (!is_resource($socket)) {
        throw new RuntimeException('Falha ao conectar no SMTP: ' . $errorMessage . ' (' . $errorNumber . ')');
    }

    stream_set_timeout($socket, (int) $config['timeout']);

    return $socket;
}

function smtp_session_handshake($socket, array $config): void
{
    smtp_expect($socket, [220]);

    smtp_write($socket, 'EHLO ' . (string) $config['ehlo']);
    smtp_expect($socket, [250]);

    if ((string) $config['security'] === 'tls') {
        smtp_write($socket, 'STARTTLS');
        smtp_expect($socket, [220]);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Falha ao iniciar TLS no SMTP.');
        }

        smtp_write($socket, 'EHLO ' . (string) $config['ehlo']);
        smtp_expect($socket, [250]);
    }

    if ((string) $config['username'] !== '') {
        smtp_write($socket, 'AUTH LOGIN');
        smtp_expect($socket, [334]);
        smtp_write($socket, base64_encode((string) $config['username']));
        smtp_expect($socket, [334]);
        smtp_write($socket, base64_encode((string) $config['password']));
        smtp_expect($socket, [235]);
    }
}

function smtp_session_open(array &$session, string $reason = 'open', bool $isReconnect = false): void
{
    if ($session === []) {
        $session = smtp_session_create();
    }

    $config = smtp_session_config();
    $socket = smtp_session_open_socket($config);

    try {
        smtp_session_handshake($socket, $config);
    } catch (Throwable $exception) {
        fclose($socket);
        throw $exception;
    }

    $session['socket'] = $socket;
    $session['connection_count'] = ((int) ($session['connection_count'] ?? 0)) + 1;
    $session['connection_messages_sent'] = 0;
    $session['connected_at'] = time();
    $session['last_used_at'] = time();

    smtp_session_log($isReconnect ? 'reconnect' : 'open', smtp_session_meta($session, [
        'reason' => $reason,
    ]));
}

function smtp_session_close(&$session, string $reason = 'close'): void
{
    if (!is_array($session) || !smtp_session_is_active($session)) {
        return;
    }

    try {
        smtp_write($session['socket'], 'QUIT');
        smtp_expect($session['socket'], [221]);
    } catch (Throwable $exception) {
        // Fechamento conservador: se o servidor ja estiver indisponivel, seguimos com o fclose.
    }

    fclose($session['socket']);
    smtp_session_log('close', smtp_session_meta($session, [
        'reason' => $reason,
    ]));

    $session['socket'] = null;
    $session['connected_at'] = null;
    $session['last_used_at'] = time();
    $session['connection_messages_sent'] = 0;
}

function smtp_session_idle_seconds(array $session): int
{
    $lastUsedAt = (int) ($session['last_used_at'] ?? 0);

    if ($lastUsedAt <= 0) {
        return 0;
    }

    return max(0, time() - $lastUsedAt);
}

function smtp_session_reconnect(array &$session, string $reason): void
{
    $session['reconnect_count'] = ((int) ($session['reconnect_count'] ?? 0)) + 1;
    smtp_session_close($session, 'reconnect_prepare:' . $reason);
    smtp_session_open($session, $reason, true);
}

function smtp_session_prepare(array &$session): array
{
    if ($session === []) {
        $session = smtp_session_create();
    }

    if (!smtp_session_is_active($session)) {
        smtp_session_open($session, 'initial_connect');

        return smtp_session_meta($session, [
            'opened' => true,
            'reused' => false,
            'reconnected' => false,
        ]);
    }

    $idleSeconds = smtp_session_idle_seconds($session);
    $socketMeta = @stream_get_meta_data($session['socket']);
    $needsReconnect = null;

    if ((int) ($session['connection_messages_sent'] ?? 0) >= (int) ($session['max_messages'] ?? SMTP_SESSION_DEFAULT_MAX_MESSAGES)) {
        $needsReconnect = 'session_limit';
    } elseif (!empty($socketMeta['timed_out'])) {
        $needsReconnect = 'socket_timeout';
    } elseif (!empty($socketMeta['eof']) || @feof($session['socket'])) {
        $needsReconnect = 'socket_eof';
    } elseif ($idleSeconds >= (int) ($session['max_idle_seconds'] ?? SMTP_SESSION_DEFAULT_MAX_IDLE_SECONDS)) {
        $needsReconnect = 'idle_timeout';
    }

    if ($needsReconnect !== null) {
        smtp_session_reconnect($session, $needsReconnect);

        return smtp_session_meta($session, [
            'opened' => false,
            'reused' => false,
            'reconnected' => true,
        ]);
    }

    $noopAfterSeconds = (int) ($session['noop_after_seconds'] ?? SMTP_SESSION_DEFAULT_NOOP_AFTER_SECONDS);
    $reuseReason = 'warm_reuse';

    if ($noopAfterSeconds > 0 && $idleSeconds >= $noopAfterSeconds) {
        try {
            smtp_write($session['socket'], 'NOOP');
            smtp_expect($session['socket'], [250]);
            $session['last_used_at'] = time();
            $reuseReason = 'noop_ok';
        } catch (Throwable $exception) {
            smtp_session_reconnect($session, 'noop_failed');

            return smtp_session_meta($session, [
                'opened' => false,
                'reused' => false,
                'reconnected' => true,
            ]);
        }
    }

    smtp_session_log('reuse', smtp_session_meta($session, [
        'reason' => $reuseReason,
        'idle_seconds' => $idleSeconds,
    ]));

    return smtp_session_meta($session, [
        'opened' => false,
        'reused' => true,
        'reconnected' => false,
    ]);
}

function smtp_session_reset_envelope(array &$session): void
{
    if ((int) ($session['connection_messages_sent'] ?? 0) <= 0) {
        return;
    }

    try {
        smtp_write($session['socket'], 'RSET');
        smtp_expect($session['socket'], [250]);
        $session['last_used_at'] = time();
    } catch (Throwable $exception) {
        smtp_session_reconnect($session, 'rset_failed');
    }
}

function smtp_session_send_message(
    array &$session,
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = '',
    array $deliveryOptions = []
): array {
    $config = smtp_session_config();
    $meta = smtp_session_prepare($session);
    smtp_session_reset_envelope($session);

    try {
        smtp_write($session['socket'], 'MAIL FROM:<' . (string) $config['from_address'] . '>');
        smtp_expect($session['socket'], [250]);
        smtp_write($session['socket'], 'RCPT TO:<' . $toEmail . '>');
        smtp_expect($session['socket'], [250, 251]);
        smtp_write($session['socket'], 'DATA');
        smtp_expect($session['socket'], [354]);

        $message = build_multipart_email(
            $toEmail,
            $toName,
            $subject,
            $htmlBody,
            $textBody,
            (array) ($deliveryOptions['headers'] ?? [])
        );
        fwrite($session['socket'], $message . "\r\n.\r\n");
        smtp_expect($session['socket'], [250]);
    } catch (Throwable $exception) {
        smtp_session_close($session, 'send_exception');
        throw $exception;
    }

    $session['messages_sent_total'] = ((int) ($session['messages_sent_total'] ?? 0)) + 1;
    $session['connection_messages_sent'] = ((int) ($session['connection_messages_sent'] ?? 0)) + 1;
    $session['last_used_at'] = time();

    return smtp_session_meta($session, $meta + [
        'last_recipient' => $toEmail,
        'message_id' => trim((string) (($deliveryOptions['headers']['Message-ID'] ?? ''))) ?: null,
    ]);
}
