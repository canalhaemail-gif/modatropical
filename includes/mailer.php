<?php
declare(strict_types=1);

function smtp_write($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function smtp_read_response($socket): string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);

        if ($line === false) {
            break;
        }

        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $expectedCodes): string
{
    $response = smtp_read_response($socket);
    $statusCode = (int) substr($response, 0, 3);

    if (!in_array($statusCode, $expectedCodes, true)) {
        throw new RuntimeException('SMTP inesperado: ' . trim($response));
    }

    return $response;
}

function smtp_encode_header_value(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_format_address(string $email, string $name = ''): string
{
    $email = trim($email);
    $name = trim($name);

    if ($name === '') {
        return $email;
    }

    return smtp_encode_header_value($name) . ' <' . $email . '>';
}

function smtp_normalize_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = preg_replace('/^\./m', '..', $body) ?? $body;

    return str_replace("\n", "\r\n", $body);
}

function smtp_resolve_connect_host(string $host): string
{
    $ipv4List = @gethostbynamel($host);

    if (is_array($ipv4List) && $ipv4List !== []) {
        return (string) $ipv4List[0];
    }

    return $host;
}

function smtp_normalize_extra_headers(array $headers = []): array
{
    $normalized = [];

    foreach ($headers as $name => $value) {
        $headerName = trim(is_string($name) ? $name : '');
        $headerValue = trim(is_scalar($value) ? (string) $value : '');

        if ($headerName === '' || $headerValue === '') {
            continue;
        }

        $normalized[] = $headerName . ': ' . $headerValue;
    }

    return $normalized;
}

function build_multipart_email(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody,
    array $extraHeaders = []
): string {
    $boundary = 'bnd_' . bin2hex(random_bytes(12));
    $textBody = trim($textBody) !== '' ? $textBody : trim(strip_tags($htmlBody));
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . smtp_format_address(MAIL_FROM_ADDRESS, MAIL_FROM_NAME),
        'To: ' . smtp_format_address($toEmail, $toName),
        'Reply-To: ' . smtp_format_address(MAIL_REPLY_TO_ADDRESS, MAIL_REPLY_TO_NAME),
        'Subject: ' . smtp_encode_header_value($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion(),
    ];
    $headers = array_merge($headers, smtp_normalize_extra_headers($extraHeaders));

    $message = implode("\r\n", $headers) . "\r\n\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= smtp_normalize_body($textBody) . "\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= smtp_normalize_body($htmlBody) . "\r\n";
    $message .= '--' . $boundary . "--\r\n";

    return $message;
}

require_once __DIR__ . '/mailer_smtp_session.php';

function smtp_deliver_message(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = '',
    array $deliveryOptions = [],
    &$smtpSession = null
): array {
    $temporarySession = !is_array($smtpSession);

    if ($temporarySession) {
        $smtpSession = smtp_session_create([
            'persistent' => false,
            'label' => 'unitary',
        ]);
    }

    try {
        $smtpMeta = smtp_session_send_message(
            $smtpSession,
            $toEmail,
            $toName,
            $subject,
            $htmlBody,
            $textBody,
            $deliveryOptions
        );
    } catch (Throwable $exception) {
        if (is_array($smtpSession)) {
            smtp_session_close($smtpSession, 'delivery_error');
        }

        if ($temporarySession) {
            $smtpSession = null;
        }

        throw $exception;
    }

    if ($temporarySession) {
        smtp_session_close($smtpSession, 'unitary_send_complete');
        $smtpSession = null;
    }

    return [
        'delivered' => true,
        'smtp' => $smtpMeta + [
            'temporary_session' => $temporarySession,
            'closed' => $temporarySession,
        ],
    ];
}

function send_via_smtp(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = '',
    array $deliveryOptions = [],
    &$smtpSession = null
): bool {
    $delivery = smtp_deliver_message($toEmail, $toName, $subject, $htmlBody, $textBody, $deliveryOptions, $smtpSession);

    return !empty($delivery['delivered']);
}

function ensure_mail_directory(): void
{
    if (!is_dir(MAIL_LOG_DIRECTORY)) {
        if (!mkdir(MAIL_LOG_DIRECTORY, 0775, true) && !is_dir(MAIL_LOG_DIRECTORY)) {
            throw new RuntimeException('Nao foi possivel criar o diretorio de log de email.');
        }
    }

    if (!is_writable(MAIL_LOG_DIRECTORY)) {
        throw new RuntimeException('Diretorio de log de email sem permissao de escrita: ' . MAIL_LOG_DIRECTORY);
    }
}

function build_email_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function log_email_copy(string $toEmail, string $subject, string $htmlBody, string $textBody): string
{
    ensure_mail_directory();

    $fileName = 'mail-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.html';
    $filePath = MAIL_LOG_DIRECTORY . '/' . $fileName;

    $content = "<h2>Para: " . e($toEmail) . "</h2>\n";
    $content .= "<h3>Assunto: " . e($subject) . "</h3>\n";
    $content .= "<hr>\n";
    $content .= $htmlBody;
    $content .= "\n<hr>\n<pre>" . e($textBody) . "</pre>\n";

    $bytesWritten = file_put_contents($filePath, $content);

    if ($bytesWritten === false) {
        throw new RuntimeException('Nao foi possivel gravar o log de email em ' . $filePath);
    }

    return $filePath;
}

function send_email_message(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = '',
    array $deliveryOptions = [],
    &$smtpSession = null
): array {
    $loggedPath = null;
    $delivered = false;
    $error = null;
    $driver = strtolower(MAIL_DRIVER);
    $smtpMeta = null;

    try {
        if ($driver === 'smtp') {
            $delivery = smtp_deliver_message($toEmail, $toName, $subject, $htmlBody, $textBody, $deliveryOptions, $smtpSession);
            $delivered = !empty($delivery['delivered']);
            $smtpMeta = is_array($delivery['smtp'] ?? null) ? $delivery['smtp'] : null;
        } elseif ($driver === 'mail') {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
                'Reply-To: ' . MAIL_REPLY_TO_ADDRESS,
                'X-Mailer: PHP/' . phpversion(),
            ];
            $headers = array_merge($headers, smtp_normalize_extra_headers((array) ($deliveryOptions['headers'] ?? [])));

            $delivered = @mail(
                $toEmail,
                build_email_subject($subject),
                $htmlBody,
                implode("\r\n", $headers)
            );
        } else {
            throw new RuntimeException('MAIL_DRIVER invalido: ' . $driver);
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }

    if (!$delivered && MAIL_FALLBACK_TO_LOG) {
        try {
            $loggedPath = log_email_copy($toEmail, $subject, $htmlBody, $textBody);

            return [
                'success' => true,
                'delivered' => false,
                'logged' => true,
                'log_path' => $loggedPath,
                'error' => $error,
                'smtp' => $smtpMeta,
            ];
        } catch (Throwable $loggingException) {
            $error = $error !== null && $error !== ''
                ? $error . ' | Fallback log: ' . $loggingException->getMessage()
                : $loggingException->getMessage();
        }

        return [
            'success' => false,
            'delivered' => false,
            'logged' => false,
            'log_path' => $loggedPath,
            'error' => $error,
            'smtp' => $smtpMeta,
        ];
    }

    return [
        'success' => $delivered,
        'delivered' => $delivered,
        'logged' => false,
        'log_path' => $loggedPath,
        'error' => $error,
        'smtp' => $smtpMeta,
    ];
}
