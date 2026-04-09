<?php
declare(strict_types=1);

function admin_message_queue_form_token(): string
{
    $sessionKey = 'admin_message_enqueue_form_token';
    $token = trim((string) ($_SESSION[$sessionKey] ?? ''));

    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        $_SESSION[$sessionKey] = $token;
    }

    return $token;
}

function admin_message_queue_rotate_form_token(): string
{
    $_SESSION['admin_message_enqueue_form_token'] = bin2hex(random_bytes(16));

    return (string) $_SESSION['admin_message_enqueue_form_token'];
}

function admin_message_queue_scene_version(): string
{
    return 'admin_message_scene_v1';
}

function admin_message_queue_normalizer_version(): string
{
    return CUSTOMER_MESSAGE_RENDER_CACHE_NORMALIZER_VERSION;
}

function admin_message_queue_scene_snapshot(string $sceneJson): array
{
    $decoded = json_decode(trim($sceneJson), true);

    return is_array($decoded)
        ? customer_message_scene_normalize($decoded)
        : customer_message_scene_defaults();
}

function admin_message_queue_editor_layers_snapshot(string $editorLayersJson): array
{
    return customer_message_editor_layers([
        'email_editor_layers' => $editorLayersJson,
        'editor_layers_json' => $editorLayersJson,
    ]);
}

function admin_message_queue_scene_hash(string $sceneJson): string
{
    return message_queue_hash_payload(admin_message_queue_scene_snapshot($sceneJson));
}

function admin_message_queue_editor_options(array $draft, string $buttonLabel): array
{
    return [
        'show_title' => ($draft['show_title'] ?? '0') === '1' ? 1 : 0,
        'show_body' => ($draft['show_body'] ?? '0') === '1' ? 1 : 0,
        'show_button' => ($draft['show_button'] ?? '0') === '1' ? 1 : 0,
        'show_image_hotspot' => ($draft['show_image_hotspot'] ?? '0') === '1' ? 1 : 0,
        'title_x' => (int) ($draft['title_x'] ?? 8),
        'title_y' => (int) ($draft['title_y'] ?? 10),
        'title_width' => (int) ($draft['title_width'] ?? 72),
        'body_x' => (int) ($draft['body_x'] ?? 8),
        'body_y' => (int) ($draft['body_y'] ?? 30),
        'body_width' => (int) ($draft['body_width'] ?? 72),
        'title_size' => (int) ($draft['title_size'] ?? 54),
        'body_size' => (int) ($draft['body_size'] ?? 18),
        'title_line_height' => (int) ($draft['title_line_height'] ?? 104),
        'body_line_height' => (int) ($draft['body_line_height'] ?? 175),
        'title_align' => (string) ($draft['title_align'] ?? 'left'),
        'body_align' => (string) ($draft['body_align'] ?? 'left'),
        'title_bold' => ($draft['title_bold'] ?? '0') === '1' ? 1 : 0,
        'body_bold' => ($draft['body_bold'] ?? '0') === '1' ? 1 : 0,
        'title_italic' => ($draft['title_italic'] ?? '0') === '1' ? 1 : 0,
        'body_italic' => ($draft['body_italic'] ?? '0') === '1' ? 1 : 0,
        'title_uppercase' => ($draft['title_uppercase'] ?? '0') === '1' ? 1 : 0,
        'body_uppercase' => ($draft['body_uppercase'] ?? '0') === '1' ? 1 : 0,
        'title_shadow' => (string) ($draft['title_shadow'] ?? 'strong'),
        'body_shadow' => (string) ($draft['body_shadow'] ?? 'soft'),
        'title_color' => (string) ($draft['title_color'] ?? '#fff7f0'),
        'body_color' => (string) ($draft['body_color'] ?? '#2c1917'),
        'button_x' => (int) ($draft['button_x'] ?? 24),
        'button_y' => (int) ($draft['button_y'] ?? 82),
        'button_width' => (int) ($draft['button_width'] ?? 26),
        'button_height' => (int) ($draft['button_height'] ?? 11),
        'image_hotspot_x' => (int) ($draft['image_hotspot_x'] ?? 24),
        'image_hotspot_y' => (int) ($draft['image_hotspot_y'] ?? 78),
        'image_hotspot_width' => (int) ($draft['image_hotspot_width'] ?? 26),
        'image_hotspot_height' => (int) ($draft['image_hotspot_height'] ?? 10),
        'image_link_url' => admin_message_normalize_link((string) ($draft['image_link_url'] ?? '')),
        'button_label' => $buttonLabel,
    ];
}

function admin_message_queue_context(
    array $draft,
    string $heroImagePath,
    string $buttonLabel,
    string $sceneJson,
    string $editorLayersJson
): array {
    return [
        'email_layout' => 'editor',
        'hero_image_path' => $heroImagePath,
        'cta_label' => $buttonLabel,
        'scene_json' => $sceneJson,
        'email_editor' => admin_message_queue_editor_options($draft, $buttonLabel),
        'email_editor_layers' => $editorLayersJson,
    ];
}

function admin_message_queue_build_batch_payload_snapshot(
    array $draft,
    ?string $linkUrl,
    string $buttonLabel,
    string $heroImagePath,
    string $sceneJson,
    string $editorLayersJson,
    array $context,
    array $targets
): array {
    return [
        'kind' => 'admin_message',
        'project_id' => trim((string) ($draft['project_id'] ?? '')),
        'project_name' => trim((string) ($draft['project_name'] ?? '')),
        'recipient_mode' => trim((string) ($draft['recipient_mode'] ?? 'all')),
        'customer_id' => (int) ($draft['customer_id'] ?? 0),
        'message_kind' => trim((string) ($draft['message_kind'] ?? 'manual')),
        'title' => trim((string) ($draft['title'] ?? '')),
        'message' => trim((string) ($draft['message'] ?? '')),
        'link_url' => $linkUrl,
        'button_label' => $buttonLabel,
        'hero_image_path' => $heroImagePath,
        'scene_json' => $sceneJson,
        'editor_layers_json' => $editorLayersJson,
        'send_notification' => ($draft['send_notification'] ?? '0') === '1',
        'send_email' => ($draft['send_email'] ?? '0') === '1',
        'context' => $context,
        'targets_total' => count($targets),
    ];
}

function admin_message_queue_targets_idempotency_snapshot(array $targets): array
{
    $snapshot = [];

    foreach ($targets as $target) {
        if (!is_array($target)) {
            continue;
        }

        $snapshot[] = [
            'id' => (int) ($target['id'] ?? 0),
            'email' => strtolower(trim((string) ($target['email'] ?? ''))),
            'nome' => trim((string) ($target['nome'] ?? '')),
        ];
    }

    usort($snapshot, static function (array $left, array $right): int {
        $leftKey = sprintf(
            '%020d|%s|%s',
            (int) ($left['id'] ?? 0),
            (string) ($left['email'] ?? ''),
            (string) ($left['nome'] ?? '')
        );
        $rightKey = sprintf(
            '%020d|%s|%s',
            (int) ($right['id'] ?? 0),
            (string) ($right['email'] ?? ''),
            (string) ($right['nome'] ?? '')
        );

        return $leftKey <=> $rightKey;
    });

    return $snapshot;
}

function admin_message_queue_enqueue_nonce(array $draft): string
{
    $enqueueToken = trim((string) ($draft['message_enqueue_token'] ?? ''));
    if ($enqueueToken !== '') {
        return $enqueueToken;
    }

    $debugRequestId = trim((string) ($draft['message_debug_request_id'] ?? ''));
    if ($debugRequestId !== '') {
        return $debugRequestId;
    }

    return bin2hex(random_bytes(16));
}

function admin_message_queue_idempotency_key(
    array $draft,
    array $batchPayloadSnapshot,
    array $targets,
    ?array $admin = null
): string {
    $nonce = admin_message_queue_enqueue_nonce($draft);
    $payloadHash = message_queue_hash_payload($batchPayloadSnapshot);
    $targetsHash = message_queue_hash_payload(admin_message_queue_targets_idempotency_snapshot($targets));

    return hash(
        'sha256',
        'admin_message|'
        . (int) ($admin['id'] ?? 0)
        . '|'
        . $nonce
        . '|'
        . $payloadHash
        . '|'
        . $targetsHash
    );
}

function admin_message_queue_enqueue_campaign(
    array $draft,
    array $targets,
    ?array $admin = null,
    ?array &$debug = null
): array {
    if (!message_queue_tables_ready()) {
        throw new RuntimeException('A fila de mensagens ainda nao esta pronta no banco. Aplique primeiro database/update_message_queue.sql.');
    }

    $title = trim((string) ($draft['title'] ?? ''));
    $message = trim((string) ($draft['message'] ?? ''));
    $messageKind = trim((string) ($draft['message_kind'] ?? 'manual'));
    $linkUrl = admin_message_normalize_link((string) ($draft['link_url'] ?? ''));
    $buttonLabel = trim((string) ($draft['button_label'] ?? ''));
    $heroImagePath = trim((string) ($draft['hero_image_path'] ?? ''));
    $sceneJson = trim((string) ($draft['scene_json'] ?? ''));
    $editorLayersJson = trim((string) ($draft['editor_layers_json'] ?? '[]'));
    $sendNotificationRequested = ($draft['send_notification'] ?? '0') === '1';
    $sendEmailRequested = ($draft['send_email'] ?? '0') === '1';
    $queueContext = admin_message_queue_context($draft, $heroImagePath, $buttonLabel, $sceneJson, $editorLayersJson);
    $sceneSnapshot = admin_message_queue_scene_snapshot($sceneJson);
    $editorLayersSnapshot = admin_message_queue_editor_layers_snapshot($editorLayersJson);
    $sceneHash = admin_message_queue_scene_hash($sceneJson);
    $sceneVersion = admin_message_queue_scene_version();
    $normalizerVersion = admin_message_queue_normalizer_version();
    $batchPayloadSnapshot = admin_message_queue_build_batch_payload_snapshot(
        $draft,
        $linkUrl,
        $buttonLabel,
        $heroImagePath,
        $sceneJson,
        $editorLayersJson,
        $queueContext,
        $targets
    );
    $tokenContextBase = [
        'promotions_url' => absolute_app_url('promocoes.php'),
        'store_url' => absolute_app_url('index.php'),
    ] + $queueContext;
    $jobs = [];
    $emailSkippedCount = 0;
    $notificationJobCount = 0;
    $emailJobCount = 0;

    foreach ($targets as $target) {
        $targetId = (int) ($target['id'] ?? 0);
        $targetName = trim((string) ($target['nome'] ?? 'Cliente'));
        $targetEmail = trim((string) ($target['email'] ?? ''));
        $customerSnapshot = [
            'id' => $targetId,
            'nome' => $targetName,
            'email' => $targetEmail,
        ];
        $jobSendNotification = $sendNotificationRequested;
        $jobSendEmail = $sendEmailRequested && $targetEmail !== '';

        if ($sendEmailRequested && $targetEmail === '') {
            $emailSkippedCount++;
        }

        if (!$jobSendNotification && !$jobSendEmail) {
            if ($debug !== null) {
                $debug['server']['targets'][] = [
                    'id' => $targetId,
                    'name' => $targetName,
                    'email' => $targetEmail,
                    'job_created' => false,
                    'reason' => 'no_active_channel_for_target',
                ];
            }
            continue;
        }

        $prepared = customer_message_prepare_content(
            $title,
            $message,
            $linkUrl,
            $customerSnapshot,
            $messageKind,
            $queueContext
        );
        $tokenSnapshot = customer_message_tokens($customerSnapshot, $tokenContextBase);

        $jobs[] = [
            'customer_id' => $targetId > 0 ? $targetId : null,
            'customer_email' => $targetEmail,
            'send_notification' => $jobSendNotification,
            'send_email' => $jobSendEmail,
            'subject_snapshot' => (string) ($prepared['title'] ?? 'Mensagem'),
            'customer_snapshot' => $customerSnapshot,
            'token_snapshot' => $tokenSnapshot,
            'content_snapshot' => [
                'type' => 'mensagem',
                'kind' => $messageKind,
                'payload' => [
                    'kind' => 'admin_message',
                    'message_kind' => $messageKind,
                ],
                'prepared' => $prepared,
                'context' => $queueContext,
            ],
            'scene_hash' => $sceneHash,
            'scene_version' => $sceneVersion,
            'normalizer_version' => $normalizerVersion,
        ];

        if ($jobSendNotification) {
            $notificationJobCount++;
        }
        if ($jobSendEmail) {
            $emailJobCount++;
        }

        if ($debug !== null) {
            $debug['server']['targets'][] = [
                'id' => $targetId,
                'name' => $targetName,
                'email' => $targetEmail,
                'job_created' => true,
                'job_send_notification' => $jobSendNotification,
                'job_send_email' => $jobSendEmail,
                'subject_snapshot' => (string) ($prepared['title'] ?? 'Mensagem'),
            ];
        }
    }

    if ($jobs === []) {
        throw new RuntimeException('Nenhum destinatario elegivel gerou job na fila.');
    }

    $batchData = [
        'idempotency_key' => admin_message_queue_idempotency_key($draft, $batchPayloadSnapshot, $targets, $admin),
        'project_id' => trim((string) ($draft['project_id'] ?? '')),
        'project_name' => trim((string) ($draft['project_name'] ?? '')),
        'recipient_mode' => trim((string) ($draft['recipient_mode'] ?? 'all')),
        'message_kind' => $messageKind,
        'send_notification' => $sendNotificationRequested,
        'send_email' => $sendEmailRequested,
        'payload_snapshot' => $batchPayloadSnapshot,
        'scene_snapshot' => $sceneSnapshot,
        'editor_layers_snapshot' => $editorLayersSnapshot,
        'smtp_profile_snapshot' => message_queue_current_smtp_profile_snapshot(),
        'payload_hash' => message_queue_hash_payload($batchPayloadSnapshot),
        'scene_hash' => $sceneHash,
        'scene_version' => $sceneVersion,
        'normalizer_version' => $normalizerVersion,
        'created_by' => isset($admin['id']) ? (int) $admin['id'] : null,
        'queued_at' => message_queue_now(),
    ];
    $creation = message_queue_create_batch($batchData, $jobs);

    if (!empty($creation['batch']['public_id'])) {
        $_SESSION['admin_message_last_batch_public_id'] = (string) $creation['batch']['public_id'];
    }

    if ($debug !== null) {
        admin_message_debug_event($debug, 'queue_batch_created', [
            'created' => !empty($creation['created']),
            'duplicate' => !empty($creation['duplicate']),
            'batch_public_id' => (string) ($creation['batch']['public_id'] ?? ''),
            'jobs_count' => count($jobs),
            'notification_jobs' => $notificationJobCount,
            'email_jobs' => $emailJobCount,
            'email_skipped_count' => $emailSkippedCount,
        ]);
    }

    return [
        'created' => !empty($creation['created']),
        'duplicate' => !empty($creation['duplicate']),
        'batch' => (array) ($creation['batch'] ?? []),
        'jobs_count' => count($jobs),
        'targets_total' => count($targets),
        'notification_jobs' => $notificationJobCount,
        'email_jobs' => $emailJobCount,
        'email_skipped_count' => $emailSkippedCount,
    ];
}
