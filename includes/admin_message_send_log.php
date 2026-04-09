<?php
declare(strict_types=1);

function admin_message_send_log_file_path(): string
{
    return BASE_PATH . '/mensagens_ultimo_envio.txt';
}

function admin_message_send_log_state_file_path(): string
{
    return BASE_PATH . '/.mensagens_ultimo_envio.state.json';
}

function admin_message_send_log_json_count(string $raw, string $mode = 'scene'): int
{
    $decoded = json_decode(trim($raw), true);

    if (!is_array($decoded)) {
        return 0;
    }

    if ($mode === 'layers') {
        return count($decoded);
    }

    return isset($decoded['layers']) && is_array($decoded['layers'])
        ? count($decoded['layers'])
        : 0;
}

function admin_message_send_log_preview(string $value, int $limit = 1200): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

    if ($value === '' || $limit <= 0 || strlen($value) <= $limit) {
        return $value;
    }

    return substr($value, 0, max(0, $limit - 3)) . '...';
}

function admin_message_send_log_context(?array $admin = null): array
{
    return [
        'admin_id' => isset($admin['id']) ? (int) $admin['id'] : null,
        'admin_name' => trim((string) ($admin['nome'] ?? '')),
        'admin_email' => trim((string) ($admin['email'] ?? '')),
        'request_method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
        'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'app_url' => app_url(),
        'base_path' => BASE_PATH,
    ];
}

function admin_message_send_log_draft_summary(array $draft): array
{
    return [
        'project_id' => trim((string) ($draft['project_id'] ?? '')),
        'project_name' => trim((string) ($draft['project_name'] ?? '')),
        'recipient_mode' => trim((string) ($draft['recipient_mode'] ?? 'all')),
        'customer_id' => (int) ($draft['customer_id'] ?? 0),
        'message_kind' => trim((string) ($draft['message_kind'] ?? 'manual')),
        'title' => trim((string) ($draft['title'] ?? '')),
        'message_preview' => admin_message_send_log_preview((string) ($draft['message'] ?? '')),
        'link_url' => trim((string) ($draft['link_url'] ?? '')),
        'image_link_url' => trim((string) ($draft['image_link_url'] ?? '')),
        'button_label' => trim((string) ($draft['button_label'] ?? '')),
        'hero_image_path' => trim((string) ($draft['hero_image_path'] ?? '')),
        'send_notification' => ($draft['send_notification'] ?? '0') === '1',
        'send_email' => ($draft['send_email'] ?? '0') === '1',
        'scene_json_bytes' => strlen((string) ($draft['scene_json'] ?? '')),
        'scene_layer_count' => admin_message_send_log_json_count((string) ($draft['scene_json'] ?? ''), 'scene'),
        'editor_layers_count' => admin_message_send_log_json_count((string) ($draft['editor_layers_json'] ?? ''), 'layers'),
    ];
}

function admin_message_send_log_targets_snapshot(array $targets): array
{
    $snapshot = [];

    foreach ($targets as $target) {
        if (!is_array($target)) {
            continue;
        }

        $snapshot[] = [
            'id' => (int) ($target['id'] ?? 0),
            'nome' => trim((string) ($target['nome'] ?? '')),
            'email' => trim((string) ($target['email'] ?? '')),
        ];
    }

    return $snapshot;
}

function admin_message_send_log_atomic_write(string $path, string $content): void
{
    $tempPath = $path . '.tmp';

    if (@file_put_contents($tempPath, $content, LOCK_EX) === false) {
        @file_put_contents($path, $content, LOCK_EX);
        return;
    }

    if (!@rename($tempPath, $path)) {
        @file_put_contents($path, $content, LOCK_EX);
        @unlink($tempPath);
    }
}

function admin_message_send_log_write_state(?string $batchPublicId, array $extra = []): void
{
    $payload = [
        'updated_at' => date('c'),
        'active_batch_public_id' => trim((string) $batchPublicId),
        'extra' => $extra,
    ];

    admin_message_send_log_atomic_write(
        admin_message_send_log_state_file_path(),
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
    );
}

function admin_message_send_log_read_state(): array
{
    $path = admin_message_send_log_state_file_path();

    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) @file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

function admin_message_send_log_active_batch_public_id(): string
{
    return trim((string) (admin_message_send_log_read_state()['active_batch_public_id'] ?? ''));
}

function admin_message_send_log_is_batch_active(string $batchPublicId): bool
{
    $batchPublicId = trim($batchPublicId);

    if ($batchPublicId === '') {
        return false;
    }

    return admin_message_send_log_active_batch_public_id() === $batchPublicId;
}

function admin_message_send_log_decode_json_field(?string $raw)
{
    $raw = trim((string) $raw);

    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
}

function admin_message_send_log_normalize_batch_row(array $row): array
{
    $normalized = $row;
    $normalized['payload_snapshot'] = admin_message_send_log_decode_json_field((string) ($row['payload_snapshot_json'] ?? ''));
    $normalized['scene_snapshot'] = admin_message_send_log_decode_json_field((string) ($row['scene_snapshot_json'] ?? ''));
    $normalized['editor_layers_snapshot'] = admin_message_send_log_decode_json_field((string) ($row['editor_layers_snapshot_json'] ?? ''));
    $normalized['smtp_profile_snapshot'] = admin_message_send_log_decode_json_field((string) ($row['smtp_profile_snapshot_json'] ?? ''));
    unset(
        $normalized['payload_snapshot_json'],
        $normalized['scene_snapshot_json'],
        $normalized['editor_layers_snapshot_json'],
        $normalized['smtp_profile_snapshot_json']
    );

    return $normalized;
}

function admin_message_send_log_fetch_batch_jobs(int $batchId): array
{
    if ($batchId <= 0 || !message_queue_tables_ready()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT *
         FROM message_batch_jobs
         WHERE batch_id = :batch_id
         ORDER BY id ASC'
    );
    $statement->execute(['batch_id' => $batchId]);
    $rows = $statement->fetchAll();
    $jobs = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $normalized = $row;
        $normalized['customer_snapshot'] = admin_message_send_log_decode_json_field((string) ($row['customer_snapshot_json'] ?? ''));
        $normalized['token_snapshot'] = admin_message_send_log_decode_json_field((string) ($row['token_snapshot_json'] ?? ''));
        $normalized['content_snapshot'] = admin_message_send_log_decode_json_field((string) ($row['content_snapshot_json'] ?? ''));
        $normalized['last_error_detail_decoded'] = admin_message_send_log_decode_json_field((string) ($row['last_error_detail'] ?? ''));
        unset(
            $normalized['customer_snapshot_json'],
            $normalized['token_snapshot_json'],
            $normalized['content_snapshot_json']
        );
        $jobs[] = $normalized;
    }

    return $jobs;
}

function admin_message_send_log_fetch_batch_events(int $batchId): array
{
    if ($batchId <= 0 || !message_queue_tables_ready()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT *
         FROM message_send_log
         WHERE batch_id = :batch_id
         ORDER BY id ASC'
    );
    $statement->execute(['batch_id' => $batchId]);
    $rows = $statement->fetchAll();
    $events = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $normalized = $row;
        $normalized['details'] = admin_message_send_log_decode_json_field((string) ($row['details_json'] ?? ''));
        unset($normalized['details_json']);
        $events[] = $normalized;
    }

    return $events;
}

function admin_message_send_log_batch_progress(int $batchId): array
{
    if ($batchId <= 0 || !message_queue_tables_ready()) {
        return [];
    }

    return message_queue_fetch_batch_progress($batchId);
}

function admin_message_send_log_image_dimensions(string $path): array
{
    $path = trim($path);

    if ($path === '') {
        return [
            'path' => '',
            'exists' => false,
            'width' => null,
            'height' => null,
        ];
    }

    $absolutePath = $path;
    if (!str_starts_with($absolutePath, '/')) {
        $absolutePath = BASE_PATH . '/' . ltrim($absolutePath, '/');
    }

    $size = is_file($absolutePath) ? @getimagesize($absolutePath) : false;

    return [
        'path' => $path,
        'exists' => is_file($absolutePath),
        'width' => $size[0] ?? null,
        'height' => $size[1] ?? null,
    ];
}

function admin_message_send_log_scene_layer_snapshot(array $layer, int $canvasWidth, int $canvasHeight): array
{
    $x = (int) ($layer['x'] ?? 0);
    $y = (int) ($layer['y'] ?? 0);
    $width = (int) ($layer['width'] ?? 0);
    $height = (int) ($layer['height'] ?? 0);

    return [
        'id' => trim((string) ($layer['id'] ?? '')),
        'type' => trim((string) ($layer['type'] ?? '')),
        'role' => trim((string) ($layer['role'] ?? '')),
        'text_preview' => admin_message_send_log_preview((string) ($layer['textRaw'] ?? ''), 180),
        'href' => trim((string) ($layer['hrefRaw'] ?? '')),
        'font_size' => isset($layer['fontSize']) ? (int) $layer['fontSize'] : null,
        'line_height' => isset($layer['lineHeight']) ? (float) $layer['lineHeight'] : null,
        'x_px' => $x,
        'y_px' => $y,
        'width_px' => $width,
        'height_px' => $height,
        'x_pct' => $canvasWidth > 0 ? round(($x / $canvasWidth) * 100, 2) : null,
        'y_pct' => $canvasHeight > 0 ? round(($y / $canvasHeight) * 100, 2) : null,
        'width_pct' => $canvasWidth > 0 ? round(($width / $canvasWidth) * 100, 2) : null,
        'height_pct' => $canvasHeight > 0 ? round(($height / $canvasHeight) * 100, 2) : null,
    ];
}

function admin_message_send_log_editor_layer_snapshot(array $layer, int $canvasWidth, int $canvasHeight): array
{
    $xPct = (float) ($layer['x'] ?? 0);
    $yPct = (float) ($layer['y'] ?? 0);
    $widthPct = (float) ($layer['width'] ?? 0);
    $heightPct = (float) ($layer['height'] ?? 0);

    return [
        'id' => trim((string) ($layer['id'] ?? '')),
        'type' => trim((string) ($layer['type'] ?? '')),
        'content_preview' => admin_message_send_log_preview((string) ($layer['content'] ?? ''), 180),
        'link_url' => trim((string) ($layer['link_url'] ?? '')),
        'font_size' => isset($layer['font_size']) ? (int) $layer['font_size'] : null,
        'line_height' => isset($layer['line_height']) ? (int) $layer['line_height'] : null,
        'x_pct' => round($xPct, 2),
        'y_pct' => round($yPct, 2),
        'width_pct' => round($widthPct, 2),
        'height_pct' => round($heightPct, 2),
        'x_px' => $canvasWidth > 0 ? (int) round(($xPct / 100) * $canvasWidth) : null,
        'y_px' => $canvasHeight > 0 ? (int) round(($yPct / 100) * $canvasHeight) : null,
        'width_px' => $canvasWidth > 0 ? (int) round(($widthPct / 100) * $canvasWidth) : null,
        'height_px' => $canvasHeight > 0 ? (int) round(($heightPct / 100) * $canvasHeight) : null,
    ];
}

function admin_message_send_log_render_event_index(array $events): array
{
    $index = [];

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $jobId = (int) ($event['job_id'] ?? 0);
        if ($jobId <= 0) {
            continue;
        }

        $details = is_array($event['details'] ?? null) ? (array) $event['details'] : [];
        $renderTrace = is_array($details['render_trace'] ?? null) ? (array) $details['render_trace'] : [];
        if ($renderTrace === []) {
            continue;
        }

        $index[$jobId] = [
            'event_id' => (int) ($event['id'] ?? 0),
            'stage' => trim((string) ($event['stage'] ?? '')),
            'status' => trim((string) ($event['status'] ?? '')),
            'render_trace' => $renderTrace,
        ];
    }

    return $index;
}

function admin_message_send_log_job_render_diagnostics(array $job, array $renderEventIndex): array
{
    $contentSnapshot = is_array($job['content_snapshot'] ?? null) ? (array) $job['content_snapshot'] : [];
    $prepared = is_array($contentSnapshot['prepared'] ?? null) ? (array) $contentSnapshot['prepared'] : [];
    $scene = is_array($prepared['scene'] ?? null) ? (array) $prepared['scene'] : [];
    $canvas = is_array($scene['canvas'] ?? null) ? (array) $scene['canvas'] : [];
    $canvasWidth = max(0, (int) ($canvas['width'] ?? 0));
    $canvasHeight = max(0, (int) ($canvas['height'] ?? 0));

    $sceneLayers = [];
    $sceneLayerIndex = [];
    foreach ((array) ($scene['layers'] ?? []) as $layer) {
        if (!is_array($layer)) {
            continue;
        }
        $snapshot = admin_message_send_log_scene_layer_snapshot($layer, $canvasWidth, $canvasHeight);
        $sceneLayers[] = $snapshot;
        $layerId = trim((string) ($snapshot['id'] ?? ''));
        if ($layerId !== '') {
            $sceneLayerIndex[$layerId] = $snapshot;
        }
    }

    $editorLayers = [];
    $editorLayerIndex = [];
    foreach ((array) ($prepared['editor_layers'] ?? []) as $layer) {
        if (!is_array($layer)) {
            continue;
        }
        $snapshot = admin_message_send_log_editor_layer_snapshot($layer, $canvasWidth, $canvasHeight);
        $editorLayers[] = $snapshot;
        $layerId = trim((string) ($snapshot['id'] ?? ''));
        if ($layerId !== '') {
            $editorLayerIndex[$layerId] = $snapshot;
        }
    }

    $layerComparisons = [];
    foreach ($sceneLayers as $sceneLayer) {
        $layerId = trim((string) ($sceneLayer['id'] ?? ''));
        $editorLayer = $layerId !== '' && isset($editorLayerIndex[$layerId]) ? $editorLayerIndex[$layerId] : null;

        $layerComparisons[] = [
            'id' => $layerId,
            'scene' => $sceneLayer,
            'editor' => $editorLayer,
            'delta_pct' => $editorLayer === null ? null : [
                'x' => round((float) ($sceneLayer['x_pct'] ?? 0) - (float) ($editorLayer['x_pct'] ?? 0), 2),
                'y' => round((float) ($sceneLayer['y_pct'] ?? 0) - (float) ($editorLayer['y_pct'] ?? 0), 2),
                'width' => round((float) ($sceneLayer['width_pct'] ?? 0) - (float) ($editorLayer['width_pct'] ?? 0), 2),
                'height' => round((float) ($sceneLayer['height_pct'] ?? 0) - (float) ($editorLayer['height_pct'] ?? 0), 2),
            ],
        ];
    }

    $renderEvent = $renderEventIndex[(int) ($job['id'] ?? 0)] ?? [];
    $renderTrace = is_array($renderEvent['render_trace'] ?? null) ? (array) $renderEvent['render_trace'] : [];
    $attempt = is_array($renderTrace['attempts'][0] ?? null) ? (array) $renderTrace['attempts'][0] : [];

    return [
        'job_id' => (int) ($job['id'] ?? 0),
        'job_public_id' => trim((string) ($job['public_id'] ?? '')),
        'customer_email' => trim((string) ($job['customer_email'] ?? '')),
        'scene_canvas' => [
            'width' => $canvasWidth,
            'height' => $canvasHeight,
        ],
        'background_asset' => admin_message_send_log_image_dimensions(
            (string) ($prepared['hero_image_path'] ?? ($canvas['backgroundImage'] ?? ''))
        ),
        'render_output' => admin_message_send_log_image_dimensions((string) ($job['render_path'] ?? '')),
        'render_trace_summary' => [
            'event_id' => (int) ($renderEvent['event_id'] ?? 0),
            'stage' => trim((string) ($renderEvent['stage'] ?? '')),
            'status' => trim((string) ($renderEvent['status'] ?? '')),
            'final_renderer' => trim((string) ($renderTrace['final_renderer'] ?? '')),
            'cache_status' => trim((string) ($renderTrace['render_cache_status'] ?? '')),
            'cache_key' => trim((string) ($renderTrace['render_cache_key'] ?? '')),
            'final_width' => isset($renderTrace['final_width']) ? (int) $renderTrace['final_width'] : null,
            'final_height' => isset($renderTrace['final_height']) ? (int) $renderTrace['final_height'] : null,
            'attempt_output_scale' => isset($attempt['output_scale']) ? (float) $attempt['output_scale'] : null,
            'attempt_output_width' => isset($attempt['output_width']) ? (int) $attempt['output_width'] : null,
            'attempt_output_height' => isset($attempt['output_height']) ? (int) $attempt['output_height'] : null,
            'background_image' => trim((string) ($attempt['background_image'] ?? '')),
        ],
        'layer_metrics' => is_array($attempt['layer_metrics'] ?? null)
            ? array_values((array) $attempt['layer_metrics'])
            : [],
        'layer_comparisons' => $layerComparisons,
    ];
}

function admin_message_send_log_payload(
    string $status,
    string $message,
    array $draft = [],
    ?array $admin = null,
    array $extra = [],
    array $database = []
): array {
    return [
        'generated_at' => date('c'),
        'status' => trim($status) !== '' ? trim($status) : 'info',
        'message' => trim($message) !== '' ? trim($message) : 'Sem mensagem.',
        'log_file' => admin_message_send_log_file_path(),
        'active_state' => admin_message_send_log_read_state(),
        'context' => admin_message_send_log_context($admin),
        'draft_summary' => admin_message_send_log_draft_summary($draft),
        'draft_full' => $draft,
        'extra' => $extra,
        'database' => $database,
    ];
}

function admin_message_send_log_format(array $payload): string
{
    $sections = [
        'Moda Tropical - Auditoria completa do ultimo clique em Enviar mensagem',
        'Gerado em: ' . (string) ($payload['generated_at'] ?? date('c')),
        'Status: ' . (string) ($payload['status'] ?? 'info'),
        'Mensagem: ' . (string) ($payload['message'] ?? 'Sem mensagem.'),
        'Arquivo: ' . (string) ($payload['log_file'] ?? admin_message_send_log_file_path()),
        '',
        'Estado ativo:',
        json_encode((array) ($payload['active_state'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        '',
        'Contexto:',
        json_encode((array) ($payload['context'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        '',
        'Draft resumo:',
        json_encode((array) ($payload['draft_summary'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        '',
        'Draft completo:',
        json_encode((array) ($payload['draft_full'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        '',
        'Extra:',
        json_encode((array) ($payload['extra'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        '',
        'Banco e fila:',
        json_encode((array) ($payload['database'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        '',
    ];

    return implode(PHP_EOL, $sections);
}

function admin_message_send_log_write(array $payload): void
{
    admin_message_send_log_atomic_write(
        admin_message_send_log_file_path(),
        admin_message_send_log_format($payload)
    );
}

function admin_message_send_log_write_attempt(
    string $status,
    string $message,
    array $draft = [],
    ?array $admin = null,
    array $extra = []
): void {
    admin_message_send_log_write(
        admin_message_send_log_payload($status, $message, $draft, $admin, $extra)
    );
}

function admin_message_send_log_build_batch_database_snapshot(int $batchId): array
{
    $progress = admin_message_send_log_batch_progress($batchId);
    $batchRow = is_array($progress['batch'] ?? null)
        ? admin_message_send_log_normalize_batch_row((array) $progress['batch'])
        : [];
    $jobs = admin_message_send_log_fetch_batch_jobs($batchId);
    $events = admin_message_send_log_fetch_batch_events($batchId);
    $renderEventIndex = admin_message_send_log_render_event_index($events);
    $renderDiagnostics = [];

    foreach ($jobs as $job) {
        if (!is_array($job)) {
            continue;
        }
        $renderDiagnostics[] = admin_message_send_log_job_render_diagnostics($job, $renderEventIndex);
    }

    return [
        'batch' => $batchRow,
        'recent_errors' => (array) ($progress['recent_errors'] ?? []),
        'render_diagnostics' => $renderDiagnostics,
        'jobs' => $jobs,
        'events' => $events,
    ];
}

function admin_message_send_log_write_batch_audit(
    string $status,
    string $message,
    string $batchPublicId,
    array $context = []
): bool {
    $batchPublicId = trim($batchPublicId);

    if ($batchPublicId === '' || !message_queue_tables_ready()) {
        return false;
    }

    if (!admin_message_send_log_is_batch_active($batchPublicId)) {
        return false;
    }

    $batch = message_queue_fetch_batch_by_public_id($batchPublicId);

    if (!is_array($batch) || (int) ($batch['id'] ?? 0) <= 0) {
        return false;
    }

    $database = admin_message_send_log_build_batch_database_snapshot((int) $batch['id']);
    $extra = $context;
    $extra['batch_public_id'] = $batchPublicId;
    $extra['batch_id'] = (int) ($batch['id'] ?? 0);

    admin_message_send_log_write(
        admin_message_send_log_payload(
            $status,
            $message,
            (array) ($context['draft'] ?? []),
            is_array($context['admin'] ?? null) ? $context['admin'] : null,
            $extra,
            $database
        )
    );

    return true;
}

function admin_message_send_log_refresh_batch_from_job(
    array $job,
    string $status,
    string $message,
    array $context = []
): bool {
    $batchPublicId = trim((string) ($job['batch_public_id'] ?? ''));

    if ($batchPublicId === '' && !empty($job['batch_id'])) {
        $batch = message_queue_fetch_batch((int) $job['batch_id']);
        $batchPublicId = trim((string) ($batch['public_id'] ?? ''));
    }

    if ($batchPublicId === '') {
        return false;
    }

    $context['job_id'] = (int) ($job['id'] ?? 0);
    $context['job_public_id'] = trim((string) ($job['public_id'] ?? ''));
    $context['worker_id'] = trim((string) ($job['worker_id'] ?? ($context['worker_id'] ?? '')));

    return admin_message_send_log_write_batch_audit($status, $message, $batchPublicId, $context);
}
