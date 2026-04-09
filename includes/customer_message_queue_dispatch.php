<?php
declare(strict_types=1);

require_once __DIR__ . '/customer_messages.php';

function customer_message_queue_email_header_domain(): string
{
    $ehlo = trim((string) MAIL_SMTP_EHLO);
    if ($ehlo !== '' && preg_match('/^[a-z0-9.-]+$/i', $ehlo) === 1) {
        return strtolower($ehlo);
    }

    $fromAddress = trim((string) MAIL_FROM_ADDRESS);
    if ($fromAddress !== '' && str_contains($fromAddress, '@')) {
        $domain = trim((string) substr(strrchr($fromAddress, '@') ?: '', 1));
        if ($domain !== '') {
            return strtolower($domain);
        }
    }

    return 'localhost.localdomain';
}

function customer_message_queue_email_dispatch_metadata(array $job): array
{
    $jobPublicId = trim((string) ($job['public_id'] ?? '')) ?: ('job-' . (int) ($job['id'] ?? 0));
    $batchPublicId = trim((string) ($job['batch_public_id'] ?? ''));

    if ($batchPublicId === '' && !empty($job['batch_id'])) {
        $batch = message_queue_fetch_batch((int) $job['batch_id']);
        $batchPublicId = trim((string) ($batch['public_id'] ?? ''));
    }

    $attemptNumber = max(1, (int) ($job['attempts'] ?? 0));
    $dispatchKey = hash('sha256', implode('|', [
        $jobPublicId,
        (string) $attemptNumber,
        trim((string) ($job['customer_email'] ?? '')),
    ]));
    $messageId = sprintf(
        '<mt.%s.a%d@%s>',
        preg_replace('/[^a-zA-Z0-9._-]/', '', $jobPublicId) ?: ('job' . (int) ($job['id'] ?? 0)),
        $attemptNumber,
        customer_message_queue_email_header_domain()
    );

    return [
        'dispatch_key' => $dispatchKey,
        'message_id' => $messageId,
        'attempt_number' => $attemptNumber,
        'headers' => [
            'Message-ID' => $messageId,
            'X-MT-Batch-ID' => $batchPublicId !== '' ? $batchPublicId : (string) ((int) ($job['batch_id'] ?? 0)),
            'X-MT-Job-ID' => $jobPublicId,
            'X-MT-Attempt' => (string) $attemptNumber,
            'X-MT-Dispatch-Key' => $dispatchKey,
        ],
    ];
}

function customer_message_queue_decode_job_snapshots(array $job): array
{
    $customerSnapshot = message_queue_decode_json_array((string) ($job['customer_snapshot_json'] ?? ''));
    $tokenSnapshot = message_queue_decode_json_array((string) ($job['token_snapshot_json'] ?? ''));
    $contentSnapshot = message_queue_decode_json_array((string) ($job['content_snapshot_json'] ?? ''));
    $prepared = is_array($contentSnapshot['prepared'] ?? null) ? $contentSnapshot['prepared'] : [];
    $context = is_array($contentSnapshot['context'] ?? null) ? $contentSnapshot['context'] : [];
    $payload = is_array($contentSnapshot['payload'] ?? null) ? $contentSnapshot['payload'] : [];
    $type = trim((string) ($contentSnapshot['type'] ?? 'mensagem'));
    $kind = trim((string) ($contentSnapshot['kind'] ?? $payload['message_kind'] ?? 'manual'));

    if ($prepared === []) {
        throw new RuntimeException('Job da fila sem snapshot preparado de conteudo.');
    }

    if (trim((string) ($prepared['title'] ?? '')) === '' && trim((string) ($job['subject_snapshot'] ?? '')) === '') {
        throw new RuntimeException('Job da fila sem assunto/titulo congelado.');
    }

    return [
        'customer_snapshot' => $customerSnapshot,
        'token_snapshot' => $tokenSnapshot,
        'content_snapshot' => $contentSnapshot,
        'prepared' => $prepared,
        'context' => $context,
        'payload' => $payload,
        'type' => $type !== '' ? $type : 'mensagem',
        'kind' => $kind !== '' ? $kind : 'manual',
    ];
}

function customer_message_queue_notification_payload(array $job, array $snapshots): array
{
    $batchPublicId = trim((string) ($job['batch_public_id'] ?? ''));

    if ($batchPublicId === '' && !empty($job['batch_id'])) {
        $batch = message_queue_fetch_batch((int) $job['batch_id']);
        $batchPublicId = trim((string) ($batch['public_id'] ?? ''));
    }

    $payload = (array) ($snapshots['payload'] ?? []);
    $payload['queue'] = [
        'batch_id' => (int) ($job['batch_id'] ?? 0),
        'job_id' => (int) ($job['id'] ?? 0),
        'batch_public_id' => $batchPublicId,
        'job_public_id' => (string) ($job['public_id'] ?? ''),
    ];

    return $payload;
}

function customer_message_queue_dispatch_notification(array $job): array
{
    $snapshots = customer_message_queue_decode_job_snapshots($job);
    $customerId = (int) ($job['customer_id'] ?? ($snapshots['customer_snapshot']['id'] ?? 0));
    $jobId = (int) ($job['id'] ?? 0);
    $batchId = (int) ($job['batch_id'] ?? 0);

    if ($customerId <= 0) {
        throw new RuntimeException('Job de notificacao sem customer_id valido.');
    }

    if ($jobId > 0) {
        $existing = find_customer_notification_by_queue_job($customerId, $jobId, $batchId > 0 ? $batchId : null);

        if (is_array($existing)) {
            return [
                'type' => (string) ($existing['tipo'] ?? ($snapshots['type'] ?? 'mensagem')),
                'title' => trim((string) ($existing['titulo'] ?? 'Mensagem')),
                'customer_id' => $customerId,
                'notification_id' => (int) ($existing['id'] ?? 0),
                'created' => false,
                'deduplicated' => true,
                'sent_at' => message_queue_now(),
            ];
        }
    }

    $prepared = (array) $snapshots['prepared'];
    $payload = customer_message_queue_notification_payload($job, $snapshots);
    create_customer_notification(
        $customerId,
        (string) $snapshots['type'],
        trim((string) ($prepared['title'] ?? 'Mensagem')),
        trim((string) ($prepared['message'] ?? '')),
        customer_message_normalize_link((string) ($prepared['link_url'] ?? '')),
        $payload
    );

    $createdNotification = $jobId > 0
        ? find_customer_notification_by_queue_job($customerId, $jobId, $batchId > 0 ? $batchId : null)
        : null;

    return [
        'type' => (string) $snapshots['type'],
        'title' => trim((string) ($prepared['title'] ?? 'Mensagem')),
        'customer_id' => $customerId,
        'notification_id' => (int) ($createdNotification['id'] ?? 0),
        'created' => true,
        'deduplicated' => false,
        'sent_at' => message_queue_now(),
    ];
}

function customer_message_queue_build_email_payload(array $job): array
{
    $snapshots = customer_message_queue_decode_job_snapshots($job);
    $prepared = (array) $snapshots['prepared'];
    $customerSnapshot = (array) $snapshots['customer_snapshot'];
    $tokenSnapshot = (array) $snapshots['token_snapshot'];
    $email = trim((string) ($job['customer_email'] ?? ($customerSnapshot['email'] ?? '')));

    if ($email === '') {
        throw new RuntimeException('Job de email sem endereco congelado.');
    }

    $title = trim((string) ($prepared['title'] ?? ($job['subject_snapshot'] ?? 'Mensagem')));
    $message = trim((string) ($prepared['message'] ?? ''));
    $emailLinkUrl = customer_message_normalize_link((string) ($prepared['email_link_url'] ?? $prepared['link_url'] ?? ''));
    $htmlOptions = [
        'store_name' => customer_message_store_name(),
        'eyebrow' => (string) ($prepared['eyebrow'] ?? customer_message_store_name()),
        'cta_label' => (string) ($prepared['cta_label'] ?? 'Abrir mensagem'),
        'kind' => (string) ($snapshots['kind'] ?? 'manual'),
        'theme' => is_array($prepared['theme'] ?? null) ? $prepared['theme'] : customer_message_theme((string) ($snapshots['kind'] ?? 'manual')),
        'logo_url' => customer_message_store_logo_url(),
        'hero_image_path' => (string) ($prepared['hero_image_path'] ?? ''),
        'hero_image_url' => (string) ($prepared['hero_image_url'] ?? ''),
        'hero_text_position' => (string) ($prepared['hero_text_position'] ?? 'bottom-left'),
        'layout' => (string) ($prepared['layout'] ?? 'default'),
        'editor' => is_array($prepared['editor'] ?? null) ? $prepared['editor'] : [],
        'email_editor_layers' => is_array($prepared['editor_layers'] ?? null) ? $prepared['editor_layers'] : [],
        'scene' => is_array($prepared['scene'] ?? null) ? $prepared['scene'] : [],
        'scene_json' => (string) ($prepared['scene_json'] ?? ''),
        'scene_version' => (string) ($job['scene_version'] ?? ''),
        'normalizer_version' => (string) ($job['normalizer_version'] ?? ''),
        'token_values' => $tokenSnapshot,
    ];

    $htmlBody = customer_message_build_email_html(
        $title,
        $message,
        $emailLinkUrl,
        $htmlOptions
    );
    $textBody = customer_message_build_email_text(
        $title,
        $message,
        $emailLinkUrl,
        [
            'store_name' => customer_message_store_name(),
            'eyebrow' => (string) ($prepared['eyebrow'] ?? customer_message_store_name()),
            'cta_label' => (string) ($prepared['cta_label'] ?? 'Abrir mensagem'),
        ]
    );
    $dispatch = customer_message_queue_email_dispatch_metadata($job);

    return [
        'email' => $email,
        'name' => trim((string) ($customerSnapshot['nome'] ?? 'Cliente')),
        'subject' => $title !== '' ? $title : trim((string) ($job['subject_snapshot'] ?? 'Mensagem')),
        'html_body' => $htmlBody,
        'text_body' => $textBody,
        'delivery' => $dispatch,
        'render_trace' => is_array($htmlOptions['_debug_editor_render'] ?? null)
            ? $htmlOptions['_debug_editor_render']
            : [],
    ];
}

function customer_message_queue_dispatch_email(array $job, &$smtpSession = null): array
{
    $payload = customer_message_queue_build_email_payload($job);
    $mailResult = send_email_message(
        (string) $payload['email'],
        (string) $payload['name'],
        (string) $payload['subject'],
        (string) $payload['html_body'],
        (string) $payload['text_body'],
        (array) ($payload['delivery'] ?? []),
        $smtpSession
    );

    return [
        'mail_result' => $mailResult,
        'delivery' => (array) ($payload['delivery'] ?? []),
        'render_trace' => (array) ($payload['render_trace'] ?? []),
        'sent_at' => message_queue_now(),
    ];
}
