<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/customer_message_scene.php';
require_once __DIR__ . '/../includes/customer_messages.php';
require_once __DIR__ . '/../includes/admin_message_queue.php';
require_once __DIR__ . '/../includes/admin_message_batch_status.php';
require_once __DIR__ . '/../includes/admin_message_send_log.php';

require_admin_auth();

function admin_message_target_customers(string $recipientMode, int $customerId = 0): array
{
    if ($recipientMode === 'inactive_45') {
        if (table_exists('pedidos')) {
            return db()->query(
                'SELECT c.id, c.nome, c.email
                 FROM clientes c
                 LEFT JOIN pedidos p ON p.cliente_id = c.id
                 WHERE c.ativo = 1
                 GROUP BY c.id, c.nome, c.email, c.criado_em
                 HAVING COALESCE(MAX(p.criado_em), c.criado_em) < DATE_SUB(NOW(), INTERVAL 45 DAY)
                 ORDER BY c.nome ASC'
            )->fetchAll();
        }

        return db()->query(
            'SELECT id, nome, email
             FROM clientes
             WHERE ativo = 1
               AND criado_em < DATE_SUB(NOW(), INTERVAL 45 DAY)
             ORDER BY nome ASC'
        )->fetchAll();
    }

    if ($recipientMode === 'customer' && $customerId > 0) {
        $statement = db()->prepare(
            'SELECT id, nome, email
             FROM clientes
             WHERE ativo = 1
               AND id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $customerId]);

        return $statement->fetchAll();
    }

    return db()->query(
        'SELECT id, nome, email
         FROM clientes
         WHERE ativo = 1
         ORDER BY nome ASC'
    )->fetchAll();
}

function admin_message_normalize_link(?string $value): ?string
{
    return customer_message_normalize_link($value);
}

function admin_message_layers_json_value(string $value): string
{
    $layers = customer_message_editor_layers(['email_editor_layers' => $value]);

    return json_encode($layers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function admin_message_scene_json_value(?string $value, array $draft = []): string
{
    $value = trim((string) $value);

    if ($value !== '') {
        $decoded = json_decode($value, true);
        $scene = is_array($decoded)
            ? customer_message_scene_normalize($decoded)
            : customer_message_scene_from_context(['scene_json' => $value] + $draft);
        $heroImagePath = trim((string) ($draft['hero_image_path'] ?? ''));
        if ($heroImagePath !== '') {
            $scene['canvas']['backgroundImage'] = $heroImagePath;
        }

        return json_encode(customer_message_scene_normalize($scene), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: customer_message_scene_json($draft);
    }

    return customer_message_scene_json($draft);
}

function admin_message_editor_layers_json_from_scene_value(?string $value, array $draft = []): string
{
    $raw = trim((string) $value);

    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        $scene = is_array($decoded)
            ? customer_message_scene_normalize($decoded)
            : customer_message_scene_from_context(['scene_json' => $raw] + $draft);
        $heroImagePath = trim((string) ($draft['hero_image_path'] ?? ''));
        if ($heroImagePath !== '') {
            $scene['canvas']['backgroundImage'] = $heroImagePath;
        }
        return json_encode(
            customer_message_scene_to_editor_layers($scene),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '[]';
    }

    return admin_message_layers_json_value((string) ($draft['editor_layers_json'] ?? '[]'));
}

function admin_message_default_draft(): array
{
    return [
        'project_id' => '',
        'project_name' => '',
        'recipient_mode' => 'all',
        'customer_id' => '',
        'message_kind' => 'manual',
        'title' => '',
        'message' => '',
        'link_url' => '',
        'image_link_url' => '',
        'button_label' => '',
        'hero_image_path' => '',
        'editor_layers_json' => '[]',
        'scene_json' => '',
        'fabric_scene_json' => '',
        'editor_engine' => 'fabric_v2',
        'email_render_scale_percent' => '110',
        'show_title' => '1',
        'show_body' => '1',
        'show_button' => '1',
        'show_image_hotspot' => '0',
        'title_x' => '8',
        'title_y' => '10',
        'title_width' => '72',
        'body_x' => '8',
        'body_y' => '30',
        'body_width' => '72',
        'title_size' => '54',
        'body_size' => '18',
        'title_line_height' => '104',
        'body_line_height' => '175',
        'title_align' => 'left',
        'body_align' => 'left',
        'title_bold' => '1',
        'body_bold' => '0',
        'title_italic' => '0',
        'body_italic' => '0',
        'title_uppercase' => '0',
        'body_uppercase' => '0',
        'title_shadow' => 'strong',
        'body_shadow' => 'soft',
        'title_color' => '#fff7f0',
        'body_color' => '#2c1917',
        'button_x' => '24',
        'button_y' => '82',
        'button_width' => '26',
        'button_height' => '11',
        'image_hotspot_x' => '24',
        'image_hotspot_y' => '78',
        'image_hotspot_width' => '26',
        'image_hotspot_height' => '10',
        'send_notification' => '1',
        'send_email' => '1',
    ];
}

function admin_message_projects_file(): string
{
    return BASE_PATH . '/storage/messages/projects.json';
}

function admin_message_normalize_www_data_permissions(string $path, int $mode = 0664): void
{
    if (!file_exists($path)) {
        return;
    }

    @chmod($path, $mode);

    if (!function_exists('posix_geteuid') || (int) posix_geteuid() !== 0) {
        return;
    }

    $user = function_exists('posix_getpwnam') ? @posix_getpwnam('www-data') : false;
    $group = function_exists('posix_getgrnam') ? @posix_getgrnam('www-data') : false;

    if (is_array($user) && isset($user['uid'])) {
        @chown($path, (int) $user['uid']);
    }

    if (is_array($group) && isset($group['gid'])) {
        @chgrp($path, (int) $group['gid']);
    }
}

function admin_message_projects_normalize_permissions(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    admin_message_normalize_www_data_permissions($file, 0664);
}

function admin_message_projects_load(): array
{
    $file = admin_message_projects_file();

    if (!is_file($file)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($file), true);

    return is_array($decoded) ? $decoded : [];
}

function admin_message_projects_save(array $projects): void
{
    $file = admin_message_projects_file();
    $directory = dirname($file);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de projetos salvos.');
    }

    if (file_put_contents($file, json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        throw new RuntimeException('Nao foi possivel salvar o projeto.');
    }

    admin_message_projects_normalize_permissions($file);
}

function admin_message_project_slug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?: '';
    $normalized = trim((string) $normalized, '-');

    return $normalized !== '' ? $normalized : 'projeto';
}

function admin_message_project_find(string $projectId, array $projects): ?array
{
    foreach ($projects as $project) {
        if ((string) ($project['id'] ?? '') === $projectId) {
            return is_array($project) ? $project : null;
        }
    }

    return null;
}

function admin_message_project_assets_relative_dir(): string
{
    return 'uploads/messages/project-assets';
}

function admin_message_project_assets_directory(): string
{
    return BASE_PATH . '/' . admin_message_project_assets_relative_dir();
}

function admin_message_project_assets_prepare_directory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de imagens do projeto.');
    }

    admin_message_normalize_www_data_permissions($directory, 0775);

    if (!is_writable($directory)) {
        throw new RuntimeException('A pasta de imagens do projeto nao esta gravavel.');
    }
}

function admin_message_project_persist_hero_image(string $projectId, string $heroImagePath): string
{
    $heroImagePath = trim($heroImagePath);
    if ($heroImagePath === '' || !str_starts_with($heroImagePath, 'uploads/')) {
        return $heroImagePath;
    }

    $sourcePath = BASE_PATH . '/' . ltrim($heroImagePath, '/');
    if (!is_file($sourcePath)) {
        return $heroImagePath;
    }

    $relativeDirectory = admin_message_project_assets_relative_dir();
    if (str_starts_with($heroImagePath, $relativeDirectory . '/')) {
        return $heroImagePath;
    }

    $targetDirectory = admin_message_project_assets_directory();
    admin_message_project_assets_prepare_directory($targetDirectory);

    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if ($extension === '') {
        $extension = 'png';
    }

    $safeProjectId = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($projectId)) ?: 'projeto';
    $sourceHash = hash_file('sha256', $sourcePath) ?: sha1($heroImagePath);
    $targetFileName = $safeProjectId . '-hero-' . substr($sourceHash, 0, 16) . '.' . $extension;
    $targetRelativePath = $relativeDirectory . '/' . $targetFileName;
    $targetPath = BASE_PATH . '/' . $targetRelativePath;

    if (!is_file($targetPath)) {
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Nao foi possivel copiar a imagem do projeto.');
        }
        admin_message_normalize_www_data_permissions($targetPath, 0664);
    }

    return $targetRelativePath;
}

function admin_message_project_store(array $draft, string $projectName, array $projects): string
{
    $projectId = trim((string) ($draft['project_id'] ?? ''));
    if ($projectId === '') {
        $projectId = admin_message_project_slug($projectName) . '-' . date('YmdHis');
    }

    $draft['hero_image_path'] = admin_message_project_persist_hero_image(
        $projectId,
        trim((string) ($draft['hero_image_path'] ?? ''))
    );

    $payload = admin_message_default_draft();
    foreach (array_keys($payload) as $key) {
        if (array_key_exists($key, $draft)) {
            $payload[$key] = (string) $draft[$key];
        }
    }

    $canonicalSceneJson = admin_message_scene_json_value(
        (string) (($draft['fabric_scene_json'] ?? '') !== '' ? $draft['fabric_scene_json'] : ($draft['scene_json'] ?? '')),
        $draft
    );

    $payload['project_id'] = $projectId;
    $payload['project_name'] = $projectName;
    $payload['scene_json'] = $canonicalSceneJson;
    $payload['fabric_scene_json'] = $canonicalSceneJson;
    $payload['editor_layers_json'] = admin_message_editor_layers_json_from_scene_value($canonicalSceneJson, $draft);

    $record = [
        'id' => $projectId,
        'name' => $projectName,
        'updated_at' => date('c'),
        'payload' => $payload,
    ];

    $updated = false;
    foreach ($projects as $index => $project) {
        if ((string) ($project['id'] ?? '') === $projectId) {
            $record['created_at'] = (string) ($project['created_at'] ?? date('c'));
            $projects[$index] = $record;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $record['created_at'] = date('c');
        array_unshift($projects, $record);
    }

    admin_message_projects_save($projects);

    return $projectId;
}

function admin_message_post_draft(): array
{
    $draft = admin_message_default_draft();

    foreach (array_keys($draft) as $key) {
        $draft[$key] = (string) posted_value($key, $draft[$key]);
    }

    $draft['message_kind'] = (string) posted_value('message_kind', 'manual');
    $postedEditorLayersJson = (string) posted_value('editor_layers_json', '[]');
    $draft['fabric_scene_json'] = admin_message_scene_json_value((string) posted_value('fabric_scene_json', ''), $draft);
    $draft['editor_engine'] = 'fabric_v2';
    $draft['scene_json'] = admin_message_scene_json_value(
        (string) posted_value('scene_json', $draft['fabric_scene_json']),
        $draft
    );
    $draft['editor_layers_json'] = admin_message_editor_layers_json_from_scene_value(
        (string) ($draft['fabric_scene_json'] !== '' ? $draft['fabric_scene_json'] : $draft['scene_json']),
        $draft + ['editor_layers_json' => $postedEditorLayersJson]
    );
    $draft['send_notification'] = posted_value('send_notification') ? '1' : '0';
    $draft['send_email'] = posted_value('send_email') ? '1' : '0';
    $draft['show_title'] = posted_value('show_title') ? '1' : '0';
    $draft['show_body'] = posted_value('show_body') ? '1' : '0';
    $draft['show_button'] = posted_value('show_button') ? '1' : '0';
    $draft['show_image_hotspot'] = posted_value('show_image_hotspot') ? '1' : '0';

    return $draft;
}

function admin_message_debug_decode_entries(string $raw): array
{
    $decoded = json_decode(trim($raw), true);

    if (!is_array($decoded)) {
        return [];
    }

    $entries = [];

    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $entries[] = [
            'ts' => trim((string) ($row['ts'] ?? '')),
            'step' => trim((string) ($row['step'] ?? '')),
            'detail' => is_array($row['detail'] ?? null) ? $row['detail'] : [],
        ];
    }

    return $entries;
}

function admin_message_debug_json_count(string $raw, string $mode = 'scene'): int
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

function admin_message_debug_form_snapshot(array $draft): array
{
    return [
        'form_action' => trim((string) ($draft['form_action'] ?? '')),
        'project_id' => trim((string) ($draft['project_id'] ?? '')),
        'project_name' => trim((string) ($draft['project_name'] ?? '')),
        'recipient_mode' => trim((string) ($draft['recipient_mode'] ?? 'all')),
        'customer_id' => (int) ($draft['customer_id'] ?? 0),
        'message_kind' => trim((string) ($draft['message_kind'] ?? 'manual')),
        'title_bytes' => strlen((string) ($draft['title'] ?? '')),
        'message_bytes' => strlen((string) ($draft['message'] ?? '')),
        'link_url' => trim((string) ($draft['link_url'] ?? '')),
        'button_label' => trim((string) ($draft['button_label'] ?? '')),
        'image_link_url' => trim((string) ($draft['image_link_url'] ?? '')),
        'hero_image_path' => trim((string) ($draft['hero_image_path'] ?? '')),
        'send_notification' => ($draft['send_notification'] ?? '0') === '1',
        'send_email' => ($draft['send_email'] ?? '0') === '1',
        'editor_engine' => trim((string) ($draft['editor_engine'] ?? 'fabric_v2')),
        'scene_json_bytes' => strlen((string) ($draft['scene_json'] ?? '')),
        'fabric_scene_json_bytes' => strlen((string) ($draft['fabric_scene_json'] ?? '')),
        'editor_layers_json_bytes' => strlen((string) ($draft['editor_layers_json'] ?? '')),
        'scene_layer_count' => admin_message_debug_json_count((string) ($draft['scene_json'] ?? ''), 'scene'),
        'fabric_scene_layer_count' => admin_message_debug_json_count((string) ($draft['fabric_scene_json'] ?? ''), 'scene'),
        'editor_layers_count' => admin_message_debug_json_count((string) ($draft['editor_layers_json'] ?? ''), 'layers'),
    ];
}

function admin_message_debug_scene_snapshot(string $raw): array
{
    $decoded = json_decode(trim($raw), true);
    if (!is_array($decoded)) {
        return [
            'bytes' => strlen($raw),
            'parse_ok' => false,
            'canvas' => null,
            'layers' => [],
        ];
    }

    $layers = [];
    foreach ((array) ($decoded['layers'] ?? []) as $index => $layer) {
        if (!is_array($layer)) {
            continue;
        }
        $layers[] = [
            'index' => $index,
            'id' => trim((string) ($layer['id'] ?? '')),
            'type' => trim((string) ($layer['type'] ?? '')),
            'role' => trim((string) ($layer['role'] ?? '')),
            'x' => (int) ($layer['x'] ?? 0),
            'y' => (int) ($layer['y'] ?? 0),
            'width' => (int) ($layer['width'] ?? 0),
            'height' => (int) ($layer['height'] ?? 0),
            'fontSize' => (int) ($layer['fontSize'] ?? 0),
            'lineHeight' => (float) ($layer['lineHeight'] ?? 0),
            'textBytes' => strlen((string) ($layer['textRaw'] ?? '')),
            'href' => trim((string) ($layer['hrefRaw'] ?? '')),
        ];
    }

    return [
        'bytes' => strlen($raw),
        'parse_ok' => true,
        'canvas' => [
            'width' => (int) (($decoded['canvas']['width'] ?? 0)),
            'height' => (int) (($decoded['canvas']['height'] ?? 0)),
            'backgroundImage' => trim((string) ($decoded['canvas']['backgroundImage'] ?? '')),
        ],
        'layers' => $layers,
    ];
}

function admin_message_debug_layers_snapshot(string $raw): array
{
    $decoded = json_decode(trim($raw), true);
    if (!is_array($decoded)) {
        return [
            'bytes' => strlen($raw),
            'parse_ok' => false,
            'layers' => [],
        ];
    }

    $layers = [];
    foreach ($decoded as $index => $layer) {
        if (!is_array($layer)) {
            continue;
        }
        $layers[] = [
            'index' => $index,
            'id' => trim((string) ($layer['id'] ?? '')),
            'type' => trim((string) ($layer['type'] ?? '')),
            'x' => (int) ($layer['x'] ?? 0),
            'y' => (int) ($layer['y'] ?? 0),
            'width' => (int) ($layer['width'] ?? 0),
            'height' => (int) ($layer['height'] ?? 0),
            'font_size' => (int) ($layer['font_size'] ?? 0),
            'line_height' => (int) ($layer['line_height'] ?? 0),
            'content_bytes' => strlen((string) ($layer['content'] ?? '')),
            'link_url' => trim((string) ($layer['link_url'] ?? '')),
        ];
    }

    return [
        'bytes' => strlen($raw),
        'parse_ok' => true,
        'layers' => $layers,
    ];
}

function admin_message_debug_init(string $requestId, array $draft, array $clientEntries = []): array
{
    $requestId = trim($requestId) !== '' ? trim($requestId) : 'msgdbg_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

    return [
        'request_id' => $requestId,
        'captured_at' => date('c'),
        'server' => [
            'environment' => [
                'request_method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
                'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ],
            'form' => admin_message_debug_form_snapshot($draft),
            'events' => [],
            'targets' => [],
            'summary' => [],
        ],
        'client' => [
            'submitted_entries_count' => count($clientEntries),
            'submitted_entries' => $clientEntries,
        ],
    ];
}

function admin_message_debug_event(array &$debug, string $step, array $detail = []): void
{
    if (!isset($debug['server']['events']) || !is_array($debug['server']['events'])) {
        $debug['server']['events'] = [];
    }

    $debug['server']['events'][] = [
        'ts' => date('c'),
        'step' => $step,
        'detail' => $detail,
    ];
}

function admin_message_debug_store(?array $debug): void
{
    if ($debug === null) {
        return;
    }

    $_SESSION['admin_message_send_debug'] = $debug;
}

function admin_message_debug_project_payload_snapshot(array $payload): array
{
    return [
        'project_id' => trim((string) ($payload['project_id'] ?? '')),
        'project_name' => trim((string) ($payload['project_name'] ?? '')),
        'hero_image_path' => trim((string) ($payload['hero_image_path'] ?? '')),
        'title_bytes' => strlen((string) ($payload['title'] ?? '')),
        'message_bytes' => strlen((string) ($payload['message'] ?? '')),
        'link_url' => trim((string) ($payload['link_url'] ?? '')),
        'button_label' => trim((string) ($payload['button_label'] ?? '')),
        'image_link_url' => trim((string) ($payload['image_link_url'] ?? '')),
        'scene' => admin_message_debug_scene_snapshot((string) ($payload['scene_json'] ?? '')),
        'fabric_scene' => admin_message_debug_scene_snapshot((string) ($payload['fabric_scene_json'] ?? '')),
        'editor_layers' => admin_message_debug_layers_snapshot((string) ($payload['editor_layers_json'] ?? '')),
    ];
}

function admin_message_redirect_url(?string $projectId = null): string
{
    $projectId = trim((string) $projectId);

    if ($projectId === '') {
        return 'admin/mensagens.php';
    }

    return 'admin/mensagens.php?project=' . rawurlencode($projectId);
}

$presets = customer_message_presets();
$activeCustomers = admin_message_target_customers('all');
$inactiveCustomers = admin_message_target_customers('inactive_45');
$savedProjects = admin_message_projects_load();
$smtpReady = trim((string) MAIL_SMTP_USERNAME) !== '' && trim((string) MAIL_SMTP_PASSWORD) !== '';
$mailLogWritable = is_dir(MAIL_LOG_DIRECTORY)
    ? is_writable(MAIL_LOG_DIRECTORY)
    : is_writable(dirname(MAIL_LOG_DIRECTORY));

if (is_post()) {
    $postedProjectId = trim((string) posted_value('project_id', ''));
    $formAction = trim((string) posted_value('form_action', 'send_message'));

    if (!verify_csrf_token(posted_value('csrf_token'))) {
        if ($formAction === 'send_message') {
            $csrfDraft = admin_message_post_draft();
            $csrfDraft['form_action'] = $formAction;
            $csrfDraft['message_enqueue_token'] = (string) posted_value('message_enqueue_token', '');
            $csrfDraft['message_debug_request_id'] = (string) posted_value('message_debug_request_id', '');
            admin_message_send_log_write_attempt(
                'csrf_error',
                'Token invalido para enviar a mensagem.',
                $csrfDraft,
                current_admin(),
                [
                    'error_reason' => 'invalid_csrf',
                ]
            );
        }
        set_flash('error', 'Token invalido para enviar a mensagem.');
        redirect(admin_message_redirect_url($postedProjectId));
    }

    $draftFromPost = admin_message_post_draft();
    $draftFromPost['form_action'] = $formAction;
    $draftFromPost['message_enqueue_token'] = (string) posted_value('message_enqueue_token', '');
    $draftFromPost['message_debug_request_id'] = (string) posted_value('message_debug_request_id', '');
    $adminActor = current_admin();
    if ($formAction === 'send_message') {
        admin_message_send_log_write_state(null, [
            'reason' => 'new_send_click_started',
            'request_started_at' => date('c'),
        ]);
    }
    $writeSendAttemptLog = static function (string $status, string $message, array $extra = []) use (&$draftFromPost, $formAction, $adminActor): void {
        if ($formAction !== 'send_message') {
            return;
        }

        admin_message_send_log_write_attempt(
            $status,
            $message,
            $draftFromPost,
            $adminActor,
            $extra + [
                'form_action' => $formAction,
                'message_enqueue_token' => (string) ($draftFromPost['message_enqueue_token'] ?? ''),
                'message_debug_request_id' => (string) ($draftFromPost['message_debug_request_id'] ?? ''),
            ]
        );
    };
    $writeSendAttemptLog('started', 'Clique em Enviar mensagem recebido.');
    $debugCaptureRequested = in_array($formAction, ['send_message', 'save_project'], true)
        && posted_value('message_debug_capture') === '1';
    $sendDebug = $debugCaptureRequested
        ? admin_message_debug_init(
            (string) posted_value('message_debug_request_id', ''),
            $draftFromPost,
            admin_message_debug_decode_entries((string) posted_value('message_debug_client_trace', ''))
        )
        : null;
    if ($sendDebug !== null) {
        $sendDebug['server']['action'] = $formAction;
    }
    $recipientMode = (string) ($draftFromPost['recipient_mode'] ?? 'all');
    $recipientMode = in_array($recipientMode, ['all', 'inactive_45', 'customer'], true) ? $recipientMode : 'all';
    $customerId = (int) ($draftFromPost['customer_id'] ?? 0);
    $messageKind = trim((string) ($draftFromPost['message_kind'] ?? 'manual'));
    $title = trim((string) ($draftFromPost['title'] ?? ''));
    $message = trim((string) ($draftFromPost['message'] ?? ''));
    $linkUrl = admin_message_normalize_link((string) ($draftFromPost['link_url'] ?? ''));
    $buttonLabel = trim((string) ($draftFromPost['button_label'] ?? ''));
    $editorLayersJson = admin_message_layers_json_value((string) ($draftFromPost['editor_layers_json'] ?? '[]'));
    $sendNotification = ($draftFromPost['send_notification'] ?? '0') === '1' ? 1 : 0;
    $sendEmail = ($draftFromPost['send_email'] ?? '0') === '1' ? 1 : 0;
    $messageKind = isset($presets[$messageKind]) ? $messageKind : 'manual';

    $heroImagePath = trim((string) ($draftFromPost['hero_image_path'] ?? ''));

    if ($sendDebug !== null) {
        admin_message_debug_event($sendDebug, 'post_received', [
            'form_action' => $formAction,
            'post_keys' => array_keys($_POST),
            'files_keys' => array_keys($_FILES),
            'client_entries_count' => count((array) ($sendDebug['client']['submitted_entries'] ?? [])),
            'scene_post_snapshot' => admin_message_debug_scene_snapshot((string) posted_value('scene_json', '')),
            'fabric_scene_post_snapshot' => admin_message_debug_scene_snapshot((string) posted_value('fabric_scene_json', '')),
            'editor_layers_post_snapshot' => admin_message_debug_layers_snapshot((string) posted_value('editor_layers_json', '')),
        ]);
    }

    if (
        isset($_FILES['hero_image'])
        && (int) ($_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {
        try {
            $heroImagePath = handle_image_upload('hero_image', 'messages', $heroImagePath !== '' ? $heroImagePath : null) ?? $heroImagePath;
            $draftFromPost['hero_image_path'] = $heroImagePath;
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'hero_upload_ok', [
                    'hero_image_path' => $heroImagePath,
                ]);
            }
        } catch (RuntimeException $exception) {
            $writeSendAttemptLog('hero_upload_error', $exception->getMessage(), [
                'error_reason' => 'hero_upload_error',
            ]);
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'hero_upload_error', [
                    'error' => $exception->getMessage(),
                ]);
                admin_message_debug_store($sendDebug);
            }
            set_flash('error', $exception->getMessage());
            redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
        }
    }

    $draftFromPost['hero_image_path'] = $heroImagePath;
    $draftFromPost['fabric_scene_json'] = admin_message_scene_json_value((string) ($draftFromPost['fabric_scene_json'] ?? ''), $draftFromPost);
    $draftFromPost['editor_engine'] = 'fabric_v2';
    if (trim((string) ($draftFromPost['fabric_scene_json'] ?? '')) !== '') {
        $draftFromPost['scene_json'] = admin_message_scene_json_value((string) ($draftFromPost['fabric_scene_json'] ?? ''), $draftFromPost);
    }
    $sceneJson = admin_message_scene_json_value((string) ($draftFromPost['scene_json'] ?? ''), $draftFromPost);
    $draftFromPost['scene_json'] = $sceneJson;
    $draftFromPost['fabric_scene_json'] = $sceneJson;
    $draftFromPost['editor_layers_json'] = admin_message_editor_layers_json_from_scene_value($sceneJson, $draftFromPost);

    if ($sendDebug !== null) {
        $sendDebug['server']['form'] = admin_message_debug_form_snapshot($draftFromPost);
        admin_message_debug_event($sendDebug, 'payload_normalized', [
            'scene_json_bytes' => strlen($sceneJson),
            'scene_layer_count' => admin_message_debug_json_count($sceneJson, 'scene'),
            'fabric_scene_layer_count' => admin_message_debug_json_count((string) ($draftFromPost['fabric_scene_json'] ?? ''), 'scene'),
            'scene_snapshot' => admin_message_debug_scene_snapshot((string) ($draftFromPost['scene_json'] ?? '')),
            'fabric_scene_snapshot' => admin_message_debug_scene_snapshot((string) ($draftFromPost['fabric_scene_json'] ?? '')),
            'editor_layers_snapshot' => admin_message_debug_layers_snapshot((string) ($draftFromPost['editor_layers_json'] ?? '')),
        ]);
    }

    if ($formAction === 'save_project') {
        $projectName = trim((string) ($draftFromPost['project_name'] ?? ''));

        if ($projectName === '') {
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'validation_error', [
                    'reason' => 'project_name_empty',
                ]);
                admin_message_debug_store($sendDebug);
            }
            set_flash('error', 'Informe um nome para salvar o projeto.');
            redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
        }

        $draftFromPost['scene_json'] = admin_message_scene_json_value((string) ($draftFromPost['scene_json'] ?? ''), $draftFromPost);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'save_project_before_store', [
                'project_name' => $projectName,
                'draft_payload' => admin_message_debug_project_payload_snapshot($draftFromPost),
            ]);
        }
        try {
            $projectId = admin_message_project_store($draftFromPost, $projectName, $savedProjects);
        } catch (RuntimeException $exception) {
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'save_project_error', [
                    'project_name' => $projectName,
                    'error' => $exception->getMessage(),
                ]);
                admin_message_debug_store($sendDebug);
            }
            set_flash('error', $exception->getMessage());
            redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
        }
        if ($sendDebug !== null) {
            $savedProjectsAfter = admin_message_projects_load();
            $savedProject = admin_message_project_find($projectId, $savedProjectsAfter);
            admin_message_debug_event($sendDebug, 'save_project_after_store', [
                'project_id' => $projectId,
                'projects_file' => admin_message_projects_file(),
                'projects_file_mtime' => is_file(admin_message_projects_file()) ? date('c', (int) filemtime(admin_message_projects_file())) : null,
                'project_found_after_reload' => $savedProject !== null,
                'saved_project_record' => $savedProject ? [
                    'id' => (string) ($savedProject['id'] ?? ''),
                    'name' => (string) ($savedProject['name'] ?? ''),
                    'updated_at' => (string) ($savedProject['updated_at'] ?? ''),
                    'payload' => admin_message_debug_project_payload_snapshot((array) ($savedProject['payload'] ?? [])),
                ] : null,
            ]);
            $sendDebug['server']['summary'] = [
                'action' => 'save_project',
                'project_id' => $projectId,
                'project_name' => $projectName,
                'status' => 'saved',
            ];
            admin_message_debug_store($sendDebug);
        }
        set_flash('success', 'Projeto salvo com sucesso.');
        redirect('admin/mensagens.php?project=' . rawurlencode($projectId));
    }

    if ($title === '' || $message === '') {
        $writeSendAttemptLog('validation_error', 'Preencha titulo e mensagem antes de enviar.', [
            'error_reason' => 'title_or_message_empty',
            'title_bytes' => strlen($title),
            'message_bytes' => strlen($message),
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'title_or_message_empty',
                'title_bytes' => strlen($title),
                'message_bytes' => strlen($message),
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Preencha titulo e mensagem antes de enviar.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
    }

    if ($sendNotification !== 1 && $sendEmail !== 1) {
        $writeSendAttemptLog('validation_error', 'Escolha pelo menos um canal: notificacao ou email.', [
            'error_reason' => 'no_channel_selected',
            'send_notification' => $sendNotification === 1,
            'send_email' => $sendEmail === 1,
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'no_channel_selected',
                'send_notification' => $sendNotification,
                'send_email' => $sendEmail,
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Escolha pelo menos um canal: notificacao ou email.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
    }

    if ($recipientMode === 'customer' && $customerId <= 0) {
        $writeSendAttemptLog('validation_error', 'Escolha o cliente que vai receber a mensagem.', [
            'error_reason' => 'customer_missing',
            'recipient_mode' => $recipientMode,
            'customer_id' => $customerId,
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'customer_missing',
                'recipient_mode' => $recipientMode,
                'customer_id' => $customerId,
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Escolha o cliente que vai receber a mensagem.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
    }

    $targets = admin_message_target_customers($recipientMode, $customerId);

    if ($targets === []) {
        $writeSendAttemptLog('validation_error', 'Nenhum cliente ativo encontrado para esse envio.', [
            'error_reason' => 'no_targets',
            'recipient_mode' => $recipientMode,
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'no_targets',
                'recipient_mode' => $recipientMode,
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Nenhum cliente ativo encontrado para esse envio.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
    }

    if ($sendDebug !== null) {
        admin_message_debug_event($sendDebug, 'targets_resolved', [
            'recipient_mode' => $recipientMode,
            'targets_count' => count($targets),
            'send_notification' => $sendNotification === 1,
            'send_email' => $sendEmail === 1,
        ]);
    }

    try {
        $queueResult = admin_message_queue_enqueue_campaign(
            $draftFromPost,
            $targets,
            $adminActor,
            $sendDebug
        );
    } catch (Throwable $exception) {
        $writeSendAttemptLog('enqueue_error', $exception->getMessage(), [
            'error_reason' => 'queue_enqueue_error',
            'targets_total' => count($targets),
            'targets_preview' => admin_message_send_log_targets_snapshot($targets),
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'queue_enqueue_error', [
                'error' => $exception->getMessage(),
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', $exception->getMessage());
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
    }

    admin_message_queue_rotate_form_token();
    $batchPublicId = trim((string) ($queueResult['batch']['public_id'] ?? ''));
    $flashMessage = !empty($queueResult['duplicate'])
        ? 'Esse envio ja tinha sido enfileirado antes. Nenhum job novo foi duplicado.'
        : 'Lote criado com sucesso para processamento em background.';
    $flashMessage .= ' Jobs: ' . (int) ($queueResult['jobs_count'] ?? 0) . '.';
    $flashMessage .= ' Destinatarios resolvidos: ' . (int) ($queueResult['targets_total'] ?? 0) . '.';

    if (($queueResult['notification_jobs'] ?? 0) > 0) {
        $flashMessage .= ' Jobs com notificacao: ' . (int) $queueResult['notification_jobs'] . '.';
    }

    if (($queueResult['email_jobs'] ?? 0) > 0) {
        $flashMessage .= ' Jobs com email: ' . (int) $queueResult['email_jobs'] . '.';
    }

    if (($queueResult['email_skipped_count'] ?? 0) > 0) {
        $flashMessage .= ' Sem email e nao enfileirados para esse canal: ' . (int) $queueResult['email_skipped_count'] . '.';
    }

    if ($batchPublicId !== '') {
        $flashMessage .= ' Lote: ' . $batchPublicId . '.';
    }

    admin_message_send_log_write_state($batchPublicId, [
        'reason' => 'batch_created',
        'request_completed_at' => date('c'),
    ]);

    admin_message_send_log_write_batch_audit('queued', $flashMessage, $batchPublicId, [
        'admin' => $adminActor,
        'draft' => $draftFromPost,
        'batch_public_id' => $batchPublicId,
        'batch_created' => !empty($queueResult['created']),
        'batch_duplicate' => !empty($queueResult['duplicate']),
        'targets_total' => (int) ($queueResult['targets_total'] ?? 0),
        'targets' => admin_message_send_log_targets_snapshot($targets),
        'jobs_count' => (int) ($queueResult['jobs_count'] ?? 0),
        'notification_jobs' => (int) ($queueResult['notification_jobs'] ?? 0),
        'email_jobs' => (int) ($queueResult['email_jobs'] ?? 0),
        'email_skipped_count' => (int) ($queueResult['email_skipped_count'] ?? 0),
    ]);

    if ($sendDebug !== null) {
        $sendDebug['server']['summary'] = [
            'batch_public_id' => $batchPublicId,
            'batch_created' => !empty($queueResult['created']),
            'batch_duplicate' => !empty($queueResult['duplicate']),
            'targets_total' => (int) ($queueResult['targets_total'] ?? 0),
            'jobs_count' => (int) ($queueResult['jobs_count'] ?? 0),
            'notification_jobs' => (int) ($queueResult['notification_jobs'] ?? 0),
            'email_jobs' => (int) ($queueResult['email_jobs'] ?? 0),
            'email_skipped_count' => (int) ($queueResult['email_skipped_count'] ?? 0),
            'flash_message' => $flashMessage,
        ];
        admin_message_debug_event($sendDebug, 'enqueue_completed', $sendDebug['server']['summary']);
        admin_message_debug_store($sendDebug);
    }

    set_flash('success', $flashMessage);
    redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId)));
}

$currentAdminPage = 'mensagens';
$pageTitle = 'Mensagens';

$messageStats = [
    'clientes_ativos' => count($activeCustomers),
    'clientes_inativos' => count($inactiveCustomers),
    'mensagens_enviadas' => table_exists('cliente_notificacoes')
        ? (int) db()->query("SELECT COUNT(*) FROM cliente_notificacoes WHERE tipo = 'mensagem'")->fetchColumn()
        : 0,
    'automacoes_ativas' => 2,
];

$draft = admin_message_default_draft();

if (is_post()) {
    $draft = admin_message_post_draft();
} elseif (isset($_GET['project'])) {
    $loadedProject = admin_message_project_find(trim((string) $_GET['project']), $savedProjects);
    if ($loadedProject && !empty($loadedProject['payload']) && is_array($loadedProject['payload'])) {
        foreach ($draft as $key => $defaultValue) {
            if (array_key_exists($key, $loadedProject['payload'])) {
                $draft[$key] = (string) $loadedProject['payload'][$key];
            }
        }
    }
}

$draft['editor_engine'] = 'fabric_v2';

$draft['scene_json'] = admin_message_scene_json_value((string) ($draft['scene_json'] ?? ''), $draft);
$draft['fabric_scene_json'] = admin_message_scene_json_value(
    (string) (($draft['fabric_scene_json'] ?? '') !== '' ? $draft['fabric_scene_json'] : $draft['scene_json']),
    $draft
);
$draft['editor_layers'] = customer_message_editor_layers([
    'email_editor_layers' => $draft['editor_layers_json'],
    'editor_layers_json' => $draft['editor_layers_json'],
]);
$draft['message_kind'] = isset($presets[$draft['message_kind']]) ? $draft['message_kind'] : 'manual';

$messageEditorBootScene = json_decode((string) ($draft['fabric_scene_json'] ?? ''), true);
if (!is_array($messageEditorBootScene)) {
    $messageEditorBootScene = customer_message_scene_defaults();
}
$messageEditorBootSceneJson = json_encode(
    $messageEditorBootScene,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS
) ?: '{}';

$messageEditorBootLayers = json_decode((string) ($draft['editor_layers_json'] ?? ''), true);
if (!is_array($messageEditorBootLayers)) {
    $messageEditorBootLayers = [];
}
$messageEditorBootLayersJson = json_encode(
    $messageEditorBootLayers,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS
) ?: '[]';
$debugProjectQuery = isset($_GET['project']) ? trim((string) $_GET['project']) : '';
$debugProjectRecord = $debugProjectQuery !== ''
    ? admin_message_project_find($debugProjectQuery, $savedProjects)
    : null;
$debugBootLayerCount = isset($messageEditorBootScene['layers']) && is_array($messageEditorBootScene['layers'])
    ? count($messageEditorBootScene['layers'])
    : 0;
$debugEditorLayersCount = count($messageEditorBootLayers);
$draft['button_label'] = trim($draft['button_label']);
$draft['project_name'] = trim((string) ($draft['project_name'] ?? ''));
$currentHeroImageUrl = $draft['hero_image_path'] !== '' ? customer_message_absolute_asset_url($draft['hero_image_path']) : null;
$messageSendDebug = pull_session_value('admin_message_send_debug', null);
if (is_array($messageSendDebug)) {
    $messageSendDebug['server']['boot_after_redirect'] = [
        'query_project' => isset($_GET['project']) ? trim((string) $_GET['project']) : '',
        'draft_payload' => admin_message_debug_project_payload_snapshot($draft),
    ];
}
$messageDebugAction = is_array($messageSendDebug)
    ? trim((string) ($messageSendDebug['server']['action'] ?? ''))
    : '';
$messageDebugActionLabel = match ($messageDebugAction) {
    'save_project' => 'Salvar projeto',
    'send_message' => 'Enviar mensagem',
    default => 'Acao no formulario',
};
$messageEnqueueToken = admin_message_queue_form_token();
$messageBatchStatus = admin_message_batch_progress_from_last_session();
$messageBatchStatusUrl = app_url('admin/message_batch_status.php');
$messageBatchCountLabels = [
    'total_jobs' => 'Total',
    'pending_jobs' => 'Pendentes',
    'reserved_jobs' => 'Reservados',
    'processing_jobs' => 'Processando',
    'retry_jobs' => 'Retry',
    'sent_jobs' => 'Enviados',
    'failed_jobs' => 'Falhas',
];
$messageBatchStatusBootstrap = [
    'endpointUrl' => $messageBatchStatusUrl,
    'pollIntervalMs' => 5000,
    'batch' => $messageBatchStatus !== [] ? $messageBatchStatus : null,
];

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-grid admin-grid--metrics">
    <article class="metric-card">
        <span>Clientes ativos</span>
        <strong><?= e((string) $messageStats['clientes_ativos']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Mensagens internas</span>
        <strong><?= e((string) $messageStats['mensagens_enviadas']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Clientes inativos (45d)</span>
        <strong><?= e((string) $messageStats['clientes_inativos']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Automacoes ativas</span>
        <strong><?= e((string) $messageStats['automacoes_ativas']); ?></strong>
    </article>
</section>

<?php
$messageBatchHasData = $messageBatchStatus !== [];
$messageBatchStatusLabel = $messageBatchHasData
    ? admin_message_batch_status_label((string) ($messageBatchStatus['status'] ?? ''))
    : 'Sem lote';
$messageBatchStatusTone = $messageBatchHasData
    ? admin_message_batch_status_tone((string) ($messageBatchStatus['status'] ?? ''))
    : 'muted';
$messageBatchMetaLabel = 'O ultimo lote criado aparece aqui para acompanhamento automatico.';

if ($messageBatchHasData) {
    if (!empty($messageBatchStatus['is_terminal']) && !empty($messageBatchStatus['finished_at_label'])) {
        $messageBatchMetaLabel = 'Encerrado em ' . (string) $messageBatchStatus['finished_at_label'] . '.';
    } elseif (!empty($messageBatchStatus['started_at_label'])) {
        $messageBatchMetaLabel = 'Em processamento desde ' . (string) $messageBatchStatus['started_at_label'] . '.';
    } elseif (!empty($messageBatchStatus['created_at_label'])) {
        $messageBatchMetaLabel = 'Aguardando worker desde ' . (string) $messageBatchStatus['created_at_label'] . '.';
    }
}
?>
<section
    class="panel-card panel-card--form panel-card--messages message-batch-progress-card"
    data-message-batch-root
    data-message-batch-poll="5000"
>
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">fila</p>
            <h2>Ultimo lote de envio</h2>
        </div>
        <span class="message-batch-status-badge is-<?= e($messageBatchStatusTone); ?>" data-message-batch-status-badge><?= e($messageBatchStatusLabel); ?></span>
    </div>

    <div class="message-batch-progress__summary">
        <div class="message-batch-progress__headline">
            <strong data-message-batch-public-id><?= e($messageBatchHasData ? (string) ($messageBatchStatus['public_id'] ?? '') : 'Nenhum lote recente'); ?></strong>
            <p class="form-hint message-batch-progress__meta" data-message-batch-meta><?= e($messageBatchMetaLabel); ?></p>
        </div>
        <div class="message-batch-progress__meter" data-message-batch-meter-wrap <?= $messageBatchHasData ? '' : 'hidden'; ?>>
            <span data-message-batch-percent><?= e((string) ($messageBatchHasData ? (int) ($messageBatchStatus['completion_percent'] ?? 0) : 0)); ?>%</span>
            <div class="message-batch-progress__bar" aria-hidden="true">
                <span data-message-batch-bar style="width: <?= e((string) ($messageBatchHasData ? (int) ($messageBatchStatus['completion_percent'] ?? 0) : 0)); ?>%;"></span>
            </div>
        </div>
    </div>

    <div class="message-batch-progress__grid" data-message-batch-counts-wrap <?= $messageBatchHasData ? '' : 'hidden'; ?>>
        <?php foreach ($messageBatchCountLabels as $countKey => $countLabel): ?>
            <article class="message-batch-progress__metric">
                <span><?= e($countLabel); ?></span>
                <strong data-message-batch-count="<?= e($countKey); ?>"><?= e((string) (int) (($messageBatchStatus['counts'][$countKey] ?? 0))); ?></strong>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="form-hint message-batch-progress__empty" data-message-batch-empty <?= $messageBatchHasData ? 'hidden' : ''; ?>>
        Quando voce criar um novo lote, o progresso vai aparecer aqui sem precisar recarregar para acompanhar.
    </p>

    <div class="message-batch-progress__errors" data-message-batch-errors-wrap <?= !empty($messageBatchStatus['recent_errors']) ? '' : 'hidden'; ?>>
        <strong>Ultimos erros</strong>
        <ul data-message-batch-errors-list>
            <?php foreach ((array) ($messageBatchStatus['recent_errors'] ?? []) as $errorRow): ?>
                <?php
                $errorLabelParts = array_filter([
                    (string) ($errorRow['created_at_label'] ?? ''),
                    (string) ($errorRow['channel'] ?? ''),
                    (string) ($errorRow['stage'] ?? ''),
                    (string) ($errorRow['error_code'] ?? ''),
                ]);
                ?>
                <li>
                    <strong><?= e(trim((string) ($errorRow['summary'] ?? 'Erro sem resumo'))); ?></strong>
                    <span><?= e(implode(' | ', $errorLabelParts)); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="panel-card panel-card--form panel-card--messages">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">projetos</p>
            <h2>Projetos salvos</h2>
        </div>
    </div>

    <?php if ($savedProjects !== []): ?>
        <div class="message-projects-grid">
            <?php foreach ($savedProjects as $project): ?>
                <?php $projectId = (string) ($project['id'] ?? ''); ?>
                <article class="message-project-card<?= $draft['project_id'] === $projectId ? ' is-active' : ''; ?>">
                    <div>
                        <strong><?= e((string) ($project['name'] ?? 'Projeto salvo')); ?></strong>
                        <p>Atualizado em <?= e(date('d/m/Y H:i', strtotime((string) ($project['updated_at'] ?? 'now')))); ?></p>
                    </div>
                    <a class="button button--ghost button--small button--fit" href="mensagens.php?project=<?= e(rawurlencode($projectId)); ?>">Abrir</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="form-hint">Quando voce salvar um layout, ele aparece aqui para abrir depois.</p>
    <?php endif; ?>
</section>

<section class="panel-card panel-card--form panel-card--messages">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">comunicacao</p>
            <h2>Disparar mensagens</h2>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="admin-form" data-message-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="message_kind" id="message_kind" value="<?= e($draft['message_kind']); ?>">
        <input type="hidden" name="project_id" value="<?= e($draft['project_id']); ?>">
        <input type="hidden" name="hero_image_path" id="current_hero_image_path" value="<?= e($draft['hero_image_path']); ?>">
        <input type="hidden" name="scene_json" id="scene_json" value="<?= e($draft['scene_json']); ?>">
        <input type="hidden" name="fabric_scene_json" id="fabric_scene_json" value="<?= e($draft['fabric_scene_json']); ?>">
        <input type="hidden" name="editor_engine" id="editor_engine" value="<?= e($draft['editor_engine']); ?>">
        <input type="hidden" name="email_render_scale_percent" id="email_render_scale_percent" value="<?= e($draft['email_render_scale_percent']); ?>">
        <input type="hidden" name="message_enqueue_token" value="<?= e($messageEnqueueToken); ?>">
        <input type="hidden" name="message_debug_capture" id="message_debug_capture" value="0">
        <input type="hidden" name="message_debug_request_id" id="message_debug_request_id" value="">
        <input type="hidden" name="message_debug_client_trace" id="message_debug_client_trace" value="">

        <div class="form-grid">
            <div class="form-row">
                <label for="recipient_mode">Destinatario</label>
                <select id="recipient_mode" name="recipient_mode" data-recipient-mode>
                    <option value="all" <?= selected($draft['recipient_mode'], 'all'); ?>>Todos os clientes ativos</option>
                    <option value="inactive_45" <?= selected($draft['recipient_mode'], 'inactive_45'); ?>>Clientes sem pedido ha 45 dias</option>
                    <option value="customer" <?= selected($draft['recipient_mode'], 'customer'); ?>>Cliente especifico</option>
                </select>
                <p class="form-hint">Use <strong>Clientes sem pedido ha 45 dias</strong> para campanhas do tipo "Oi, sumido(a)?".</p>
            </div>

            <div class="form-row" data-customer-row>
                <label for="customer_id">Cliente</label>
                <select id="customer_id" name="customer_id">
                    <option value="">Selecione um cliente</option>
                    <?php foreach ($activeCustomers as $customer): ?>
                        <option value="<?= e((string) ((int) ($customer['id'] ?? 0))); ?>" <?= selected($draft['customer_id'], (string) ((int) ($customer['id'] ?? 0))); ?>>
                            <?= e((string) ($customer['nome'] ?? 'Cliente')); ?><?= !empty($customer['email']) ? ' - ' . e((string) $customer['email']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Esse campo so e usado quando o destinatario for um cliente especifico.</p>
            </div>

            <div class="form-row form-row--wide">
                <label>Tags disponiveis</label>
                <div class="message-token-list">
                    <span>{{primeiro_nome}}</span>
                    <span>{{nome}}</span>
                    <span>{{saudacao_sumido}}</span>
                    <span>{{bem_vindo_ou_vinda}}</span>
                    <span>{{loja}}</span>
                </div>
                <p class="form-hint">Use essas tags para personalizar campanhas como boas-vindas e reengajamento.</p>
            </div>

            <div class="form-row form-row--wide">
                <div class="form-row__header">
                    <label for="title">Titulo</label>
                    <button class="button button--primary button--small button--fit" type="button" data-add-layer="title">Adicionar</button>
                </div>
                <input id="title" name="title" type="text" maxlength="140" required value="<?= e($draft['title']); ?>" placeholder="Ex.: Seu pedido chegou, novidade da semana, cupom liberado...">
            </div>

            <div class="form-row form-row--wide">
                <div class="form-row__header">
                    <label for="message">Mensagem</label>
                    <button class="button button--primary button--small button--fit" type="button" data-add-layer="body">Adicionar</button>
                </div>
                <textarea id="message" name="message" required placeholder="Escreva a mensagem que vai para a notificacao e para o email."><?= e($draft['message']); ?></textarea>
            </div>

            <div class="form-row form-row--wide">
                <div class="form-row__header">
                    <label for="link_url">Link opcional</label>
                    <button class="button button--primary button--small button--fit" type="button" data-add-layer="button">Adicionar</button>
                </div>
                <input id="link_url" name="link_url" type="text" value="<?= e($draft['link_url']); ?>" placeholder="Ex.: meus-pedidos.php ou https://modatropical.store/promocoes.php">
                <p class="form-hint">Se informar um link, a notificacao abre esse destino e o email ganha o botao amarelo padrao.</p>
            </div>

            <div class="form-row form-row--wide">
                <div class="form-row__header">
                    <label for="button_label">Texto do botao</label>
                    <button class="button button--primary button--small button--fit" type="button" data-add-layer="button">Adicionar</button>
                </div>
                <input id="button_label" name="button_label" type="text" maxlength="60" value="<?= e($draft['button_label']); ?>" placeholder="Ex.: Ver promocoes, Comprar agora, Conferir novidade">
                <p class="form-hint">Se deixar vazio, o sistema usa o texto padrao do preset escolhido.</p>
            </div>

            <div class="form-row form-row--wide">
                <div class="form-row__header">
                    <label for="image_link_url">Link invisivel sobre a imagem</label>
                    <button class="button button--primary button--small button--fit" type="button" data-add-layer="hotspot">Adicionar</button>
                </div>
                <input id="image_link_url" name="image_link_url" type="text" value="<?= e($draft['image_link_url']); ?>" placeholder="Ex.: https://modatropical.store/promocoes.php">
                <p class="form-hint">Use esse campo quando a propria arte ja tiver um botao desenhado e voce quiser colocar um clique invisivel por cima dele.</p>
            </div>

            <div class="form-row form-row--wide">
                <label>Editor visual do email</label>
                <input id="show_title" name="show_title" type="hidden" value="<?= e($draft['show_title']); ?>">
                <input id="show_body" name="show_body" type="hidden" value="<?= e($draft['show_body']); ?>">
                <input id="show_button" name="show_button" type="hidden" value="<?= e($draft['show_button']); ?>">
                <input id="show_image_hotspot" name="show_image_hotspot" type="hidden" value="<?= e($draft['show_image_hotspot']); ?>">
                <input id="image_hotspot_width" name="image_hotspot_width" type="hidden" value="<?= e($draft['image_hotspot_width']); ?>">
                <input id="image_hotspot_height" name="image_hotspot_height" type="hidden" value="<?= e($draft['image_hotspot_height']); ?>">
                <input id="message_style_align" type="hidden" value="">
                <input id="message_style_shadow" type="hidden" value="">
                <input id="message_style_bold" type="hidden" value="0">
                <input id="message_style_italic" type="hidden" value="0">
                <input id="message_style_uppercase" type="hidden" value="0">
                <input id="title_size" name="title_size" type="hidden" value="<?= e($draft['title_size']); ?>">
                <input id="body_size" name="body_size" type="hidden" value="<?= e($draft['body_size']); ?>">
                <input id="title_line_height" name="title_line_height" type="hidden" value="<?= e($draft['title_line_height']); ?>">
                <input id="body_line_height" name="body_line_height" type="hidden" value="<?= e($draft['body_line_height']); ?>">
                <input id="title_align" name="title_align" type="hidden" value="<?= e($draft['title_align']); ?>">
                <input id="body_align" name="body_align" type="hidden" value="<?= e($draft['body_align']); ?>">
                <input id="title_bold" name="title_bold" type="hidden" value="<?= e($draft['title_bold']); ?>">
                <input id="body_bold" name="body_bold" type="hidden" value="<?= e($draft['body_bold']); ?>">
                <input id="title_italic" name="title_italic" type="hidden" value="<?= e($draft['title_italic']); ?>">
                <input id="body_italic" name="body_italic" type="hidden" value="<?= e($draft['body_italic']); ?>">
                <input id="title_uppercase" name="title_uppercase" type="hidden" value="<?= e($draft['title_uppercase']); ?>">
                <input id="body_uppercase" name="body_uppercase" type="hidden" value="<?= e($draft['body_uppercase']); ?>">
                <input id="title_shadow" name="title_shadow" type="hidden" value="<?= e($draft['title_shadow']); ?>">
                <input id="body_shadow" name="body_shadow" type="hidden" value="<?= e($draft['body_shadow']); ?>">
                <input id="title_color" name="title_color" type="hidden" value="<?= e($draft['title_color']); ?>">
                <input id="body_color" name="body_color" type="hidden" value="<?= e($draft['body_color']); ?>">
                <input type="hidden" name="title_x" value="<?= e($draft['title_x']); ?>" data-layout-input="title_x">
                <input type="hidden" name="title_y" value="<?= e($draft['title_y']); ?>" data-layout-input="title_y">
                <input type="hidden" name="title_width" value="<?= e($draft['title_width']); ?>" data-layout-input="title_width">
                <input type="hidden" name="body_x" value="<?= e($draft['body_x']); ?>" data-layout-input="body_x">
                <input type="hidden" name="body_y" value="<?= e($draft['body_y']); ?>" data-layout-input="body_y">
                <input type="hidden" name="body_width" value="<?= e($draft['body_width']); ?>" data-layout-input="body_width">
                <input type="hidden" name="button_x" value="<?= e($draft['button_x']); ?>" data-layout-input="button_x">
                <input type="hidden" name="button_y" value="<?= e($draft['button_y']); ?>" data-layout-input="button_y">
                <input type="hidden" name="button_width" value="<?= e($draft['button_width']); ?>" data-layout-input="button_width">
                <input type="hidden" name="button_height" value="<?= e($draft['button_height']); ?>" data-layout-input="button_height">
                <input type="hidden" name="image_hotspot_x" value="<?= e($draft['image_hotspot_x']); ?>" data-layout-input="image_hotspot_x">
                <input type="hidden" name="image_hotspot_y" value="<?= e($draft['image_hotspot_y']); ?>" data-layout-input="image_hotspot_y">
                <input id="editor_layers_json" name="editor_layers_json" type="hidden" value="<?= e($draft['editor_layers_json']); ?>">
            </div>

            <div class="form-row form-row--wide">
                <label>Editor visual</label>
                <div class="message-fabric-editor" data-message-fabric-root>
                    <div class="message-fabric-editor__toolbar">
                        <div class="message-fabric-editor__tools">
                            <button class="button button--ghost button--small" type="button" data-fabric-add="title">Novo titulo</button>
                            <button class="button button--ghost button--small" type="button" data-fabric-add="body">Novo texto</button>
                            <button class="button button--ghost button--small" type="button" data-fabric-add="button">Novo botao</button>
                            <button class="button button--ghost button--small" type="button" data-fabric-add="hotspot">Novo link</button>
                        </div>
                        <div class="message-fabric-editor__tools">
                            <button class="button button--ghost button--small" type="button" data-fabric-action="edit-text">Editar texto</button>
                            <button class="button button--ghost button--small" type="button" data-fabric-action="duplicate">Duplicar</button>
                            <button class="button button--ghost button--small" type="button" data-fabric-action="delete">Excluir</button>
                        </div>
                    </div>
                    <div class="message-fabric-editor__status">
                        <strong>Status:</strong>
                        <span data-fabric-mode-label>V2 ativa para envio</span>
                    </div>
                    <div class="message-fabric-editor__workspace">
                        <div class="message-fabric-editor__canvas-area">
                            <div class="message-fabric-editor__canvas-shell">
                                <canvas
                                    id="message_editor_v2_canvas"
                                    data-message-fabric-canvas
                                    width="320"
                                    height="180"
                                ></canvas>
                            </div>
                            <p class="form-hint message-fabric-editor__hint">
                                Selecione um texto e clique em <strong>Editar texto</strong> ou aperte <strong>Enter</strong>.
                                Use as <strong>setas</strong> para mover a camada selecionada e <strong>Shift</strong> para andar mais rapido.
                            </p>
                        </div>
                        <aside class="message-fabric-editor__inspector" aria-label="Proporcoes do email">
                            <div class="message-fabric-editor__inspector-card">
                                <strong>Proporcao no e-mail</strong>
                                <label class="message-fabric-editor__inspector-label" for="email_render_scale_percent_control">Escala da arte</label>
                                <select id="email_render_scale_percent_control" class="message-fabric-editor__scale-select" data-email-render-scale>
                                    <?php foreach ([100, 105, 110, 115, 120, 125, 130, 140] as $scalePercent): ?>
                                        <option value="<?= e((string) $scalePercent); ?>" <?= selected((string) $draft['email_render_scale_percent'], (string) $scalePercent); ?>><?= e((string) $scalePercent); ?>%</option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-hint message-fabric-editor__inspector-hint">
                                    A area de montagem acompanha essa escala para ficar com o mesmo enquadramento do e-mail final.
                                </p>
                            </div>
                            <div class="message-fabric-editor__inspector-card">
                                <strong>Dimensoes previstas</strong>
                                <dl class="message-fabric-editor__metrics">
                                    <div>
                                        <dt>Cena base</dt>
                                        <dd data-email-preview-base-size>800 x 1100</dd>
                                    </div>
                                    <div>
                                        <dt>Email final</dt>
                                        <dd data-email-preview-output-size>880 x 1210</dd>
                                    </div>
                                    <div>
                                        <dt>Escala aplicada</dt>
                                        <dd data-email-preview-scale-label>110%</dd>
                                    </div>
                                </dl>
                            </div>
                        </aside>
                    </div>
                    <p class="form-hint">Monte a arte aqui. Esse editor V2 ja e o responsavel pelo envio.</p>
                </div>
            </div>

            <div class="form-row form-row--toggle">
                <label class="checkbox-row">
                    <input name="send_notification" type="checkbox" value="1" <?= checked($draft['send_notification'], 1); ?>>
                    <span>Enviar notificacao no site</span>
                </label>
            </div>

            <div class="form-row form-row--toggle">
                <label class="checkbox-row">
                    <input name="send_email" type="checkbox" value="1" <?= checked($draft['send_email'], 1); ?>>
                    <span>Enviar email</span>
                </label>
                <p class="form-hint">
                    <?php if ($smtpReady): ?>
                        O SMTP esta configurado. Os emails devem ser entregues normalmente.
                    <?php elseif ($mailLogWritable): ?>
                        O SMTP nao esta configurado nesta VPS. Os emails ficam registrados em <code>storage/mail</code> ate voce configurar o envio real.
                    <?php else: ?>
                        O SMTP nao esta configurado e o diretorio <code>storage/mail</code> tambem esta sem escrita. Ajuste o envio ou as permissoes antes de usar email.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="form-actions form-actions--message-builder">
            <div class="message-form-actions__left">
                <div class="admin-file-field admin-file-field--compact">
                    <input
                        id="hero_image"
                        name="hero_image"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp"
                        data-message-hero-input
                    >
                    <label class="admin-file-field__button" for="hero_image">Escolher imagem</label>
                    <span class="admin-file-field__name" data-message-hero-name><?= e($draft['hero_image_path'] !== '' ? basename($draft['hero_image_path']) : 'Nenhuma imagem selecionada'); ?></span>
                </div>
                <button class="button button--ghost button--small" type="button" <?= $draft['hero_image_path'] === '' ? 'hidden' : ''; ?> data-message-clear-image>Limpar imagem</button>
                <input class="message-project-name" id="project_name" name="project_name" type="text" maxlength="120" value="<?= e($draft['project_name']); ?>" placeholder="Nome do projeto">
            </div>
            <div class="message-form-actions__right">
                <button class="button button--ghost" type="submit" name="form_action" value="save_project">Salvar projeto</button>
                <button class="button button--primary" type="submit" name="form_action" value="send_message">Enviar mensagem</button>
            </div>
        </div>
    </form>
</section>

<section class="panel-card panel-card--form panel-card--messages message-editor-debug-wrap" aria-label="Debug do ultimo envio">
    <div class="message-editor-debug__body">
        <h3 class="message-editor-debug__h">Log em tempo real do editor</h3>
        <p class="form-hint message-editor-debug__hint">Esse painel fica sempre ligado. Ele registra em tempo real cliques, duplo clique, foco, digitacao, alteracoes de campo e os eventos internos do editor.</p>
        <pre class="message-editor-debug__pre" id="message-editor-debug-client">Aguardando eventos do navegador...</pre>
    </div>
</section>

<script>
(function () {
    'use strict';

    var storageKey = 'mt_message_send_debug_state';
    var bootstrap = <?= json_encode([
        'hasServerDebug' => is_array($messageSendDebug),
        'requestId' => is_array($messageSendDebug) ? (string) ($messageSendDebug['request_id'] ?? '') : '',
        'action' => $messageDebugAction,
        'clientEntries' => is_array($messageSendDebug) ? (array) (($messageSendDebug['client']['submitted_entries'] ?? [])) : [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS); ?>;
    var state = {
        active: true,
        requestId: '',
        action: '',
        entries: []
    };

    var createRequestId = function () {
        return 'msgdbg_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    };

    var safeParse = function (raw, fallback) {
        try {
            return JSON.parse(raw);
        } catch (err) {
            return fallback;
        }
    };

    var trimEntries = function () {
        if (state.entries.length > 400) {
            state.entries = state.entries.slice(-400);
        }
    };

    var persistState = function () {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify({
                active: state.active,
                requestId: state.requestId,
                action: state.action,
                entries: state.entries
            }));
        } catch (err) {
            // sem storage: segue sem persistencia
        }
    };

    var hydrateFromStorage = function () {
        try {
            var stored = safeParse(sessionStorage.getItem(storageKey) || '', null);
            if (!stored || typeof stored !== 'object') {
                return;
            }
            state.active = stored.active === true;
            state.requestId = String(stored.requestId || '');
            state.action = String(stored.action || '');
            state.entries = Array.isArray(stored.entries) ? stored.entries : [];
        } catch (err) {
            state.active = false;
            state.requestId = '';
            state.action = '';
            state.entries = [];
        }
    };

    var renderEntries = function () {
        var pre = document.getElementById('message-editor-debug-client');
        if (!pre) {
            return;
        }

        if (!Array.isArray(state.entries) || state.entries.length === 0) {
            pre.textContent = 'Aguardando eventos do navegador...';
            return;
        }

        pre.textContent = state.entries.map(function (row) {
            return String(row.ts || '') + ' | ' + String(row.step || '') + ' | ' + JSON.stringify(row.detail || {});
        }).join('\n');
        pre.scrollTop = pre.scrollHeight;
    };

    var normalizeEventTarget = function (target) {
        if (!target) {
            return null;
        }

        if (target.nodeType === Node.TEXT_NODE) {
            return target.parentElement || null;
        }

        return target.nodeType === Node.ELEMENT_NODE ? target : null;
    };

    var compactText = function (value, limit) {
        var normalized = String(value || '').replace(/\s+/g, ' ').trim();
        if (normalized === '') {
            return '';
        }
        return normalized.length > limit
            ? normalized.slice(0, Math.max(0, limit - 1)) + '...'
            : normalized;
    };

    var summarizeTarget = function (target) {
        var element = normalizeEventTarget(target);
        if (!element) {
            return {};
        }

        var summary = {
            tag: String(element.tagName || '').toLowerCase()
        };

        if (element.id) {
            summary.id = String(element.id);
        }
        if (element.name) {
            summary.name = String(element.name);
        }
        if (element.className && typeof element.className === 'string') {
            summary.className = compactText(element.className, 120);
        }
        if (element.getAttribute) {
            var inputType = element.getAttribute('type');
            if (inputType) {
                summary.inputType = String(inputType);
            }
            var fabricAction = element.getAttribute('data-fabric-action');
            if (fabricAction) {
                summary.fabricAction = String(fabricAction);
            }
            var fabricAdd = element.getAttribute('data-fabric-add');
            if (fabricAdd) {
                summary.fabricAdd = String(fabricAdd);
            }
            var addLayer = element.getAttribute('data-add-layer');
            if (addLayer) {
                summary.addLayer = String(addLayer);
            }
        }

        if ('value' in element && typeof element.value === 'string') {
            summary.valueBytes = String(element.value || '').length;
            if (summary.tag === 'textarea' || summary.tag === 'input') {
                summary.valuePreview = compactText(element.value || '', 140);
            }
        }

        if ('checked' in element) {
            summary.checked = Boolean(element.checked);
        }

        if (summary.tag === 'button' || summary.tag === 'a' || summary.tag === 'label') {
            summary.textPreview = compactText(element.textContent || '', 80);
        }

        return summary;
    };

    var pushDomEvent = function (form, step, event) {
        var target = normalizeEventTarget(event && event.target);
        if (!target || !form || !form.contains(target)) {
            return;
        }

        var detail = summarizeTarget(target);
        if (event && typeof event.clientX === 'number' && typeof event.clientY === 'number') {
            detail.clientX = Math.round(event.clientX);
            detail.clientY = Math.round(event.clientY);
        }
        if (event && typeof event.key === 'string' && event.key !== '') {
            detail.key = String(event.key);
        }
        push(step, detail);
    };

    var startLiveSession = function (options) {
        var opts = options && typeof options === 'object' ? options : {};
        state.active = true;
        state.requestId = opts.requestId
            ? String(opts.requestId)
            : (state.requestId || createRequestId());
        state.action = String(opts.action || state.action || 'live_editor');
        if (opts.resetEntries === true) {
            state.entries = [];
        }
        persistState();
        renderEntries();
    };

    var readJsonSummary = function (value, mode) {
        var raw = String(value || '').trim();
        if (raw === '') {
            return { bytes: 0, count: 0, parseOk: false };
        }

        var parsed = safeParse(raw, null);
        if (!parsed || typeof parsed !== 'object') {
            return { bytes: raw.length, count: 0, parseOk: false };
        }

        if (mode === 'layers') {
            return {
                bytes: raw.length,
                count: Array.isArray(parsed) ? parsed.length : 0,
                parseOk: Array.isArray(parsed)
            };
        }

        return {
            bytes: raw.length,
            count: Array.isArray(parsed.layers) ? parsed.layers.length : 0,
            parseOk: true
        };
    };

    var buildFormSnapshot = function (form, submitter) {
        var sendEmail = form.querySelector('input[name="send_email"]');
        var sendNotification = form.querySelector('input[name="send_notification"]');
        var title = form.querySelector('#title');
        var message = form.querySelector('#message');
        var link = form.querySelector('#link_url');
        var buttonLabel = form.querySelector('#button_label');
        var recipientMode = form.querySelector('#recipient_mode');
        var customerId = form.querySelector('#customer_id');
        var heroPath = form.querySelector('#current_hero_image_path');
        var sceneInput = form.querySelector('#scene_json');
        var fabricSceneInput = form.querySelector('#fabric_scene_json');
        var editorLayersInput = form.querySelector('#editor_layers_json');

        return {
            submitterAction: submitter && submitter.name === 'form_action' ? submitter.value : '',
            submitterText: submitter ? String(submitter.textContent || '').trim() : '',
            locationHref: String(window.location.href || ''),
            recipientMode: recipientMode ? String(recipientMode.value || '') : '',
            customerId: customerId ? String(customerId.value || '') : '',
            titleBytes: title ? String(title.value || '').length : 0,
            messageBytes: message ? String(message.value || '').length : 0,
            linkUrl: link ? String(link.value || '') : '',
            buttonLabel: buttonLabel ? String(buttonLabel.value || '') : '',
            heroImagePath: heroPath ? String(heroPath.value || '') : '',
            sendEmailChecked: Boolean(sendEmail && sendEmail.checked),
            sendNotificationChecked: Boolean(sendNotification && sendNotification.checked),
            sceneSummary: readJsonSummary(sceneInput ? sceneInput.value : '', 'scene'),
            fabricSceneSummary: readJsonSummary(fabricSceneInput ? fabricSceneInput.value : '', 'scene'),
            editorLayersSummary: readJsonSummary(editorLayersInput ? editorLayersInput.value : '', 'layers')
        };
    };

    var writeHiddenPayload = function (form) {
        var captureInput = form.querySelector('#message_debug_capture');
        var requestIdInput = form.querySelector('#message_debug_request_id');
        var traceInput = form.querySelector('#message_debug_client_trace');

        if (captureInput) {
            captureInput.value = state.active ? '1' : '0';
        }
        if (requestIdInput) {
            requestIdInput.value = state.requestId;
        }
        if (traceInput) {
            traceInput.value = JSON.stringify(state.entries);
        }
    };

    var push = function (step, detail) {
        if (!state.active) {
            return;
        }

        state.entries.push({
            ts: new Date().toISOString(),
            step: step,
            detail: detail || {}
        });
        trimEntries();
        persistState();
        renderEntries();

        if (typeof console !== 'undefined' && console.info) {
            console.info('[mensagens-form-debug]', step, detail || {});
        }
    };

    var ensureDebugSession = function (form, submitter, reason) {
        var action = submitter && submitter.name === 'form_action' ? submitter.value : '';
        if (!state.requestId) {
            state.requestId = createRequestId();
        }
        state.active = true;
        state.action = String(action || state.action || '');
        push(reason, buildFormSnapshot(form, submitter));
        writeHiddenPayload(form);
    };

    window.messageEditorDebugPush = function (step, detail) {
        push(step, detail);
    };

    window.messageEditorDebugFinalize = function (step, detail) {
        if (step) {
            push(step, detail || {});
        }
        persistState();
        renderEntries();
    };

    hydrateFromStorage();

    if (bootstrap.hasServerDebug) {
        if (String(bootstrap.requestId || '') !== '' && String(bootstrap.requestId || '') !== String(state.requestId || '')) {
            state.entries = [];
        }
        state.requestId = String(bootstrap.requestId || state.requestId || '');
        state.action = String(bootstrap.action || state.action || '');
        if (Array.isArray(bootstrap.clientEntries)) {
            state.entries = bootstrap.clientEntries.slice();
        }
        state.active = true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (bootstrap.hasServerDebug) {
            startLiveSession({
                requestId: String(bootstrap.requestId || state.requestId || createRequestId()),
                action: String(bootstrap.action || state.action || 'live_editor'),
                resetEntries: false
            });
            push('pagina_recarregada_apos_submit', {
                requestId: state.requestId,
                action: state.action,
                hasServerDebug: Boolean(bootstrap.hasServerDebug),
                bootstrapClientEntries: Array.isArray(bootstrap.clientEntries) ? bootstrap.clientEntries.length : 0,
                fullUrl: String(window.location.href || '')
            });
        } else {
            startLiveSession({
                requestId: createRequestId(),
                action: 'live_editor',
                resetEntries: true
            });
            push('pagina_carregada', {
                requestId: state.requestId,
                action: state.action,
                hasServerDebug: false,
                fullUrl: String(window.location.href || '')
            });
        }

        var form = document.querySelector('[data-message-form]');
        if (!form) {
            return;
        }

        writeHiddenPayload(form);

        form.addEventListener('click', function (event) {
            pushDomEvent(form, 'dom: click', event);
        }, true);

        form.addEventListener('dblclick', function (event) {
            pushDomEvent(form, 'dom: dblclick', event);
        }, true);

        form.addEventListener('focusin', function (event) {
            pushDomEvent(form, 'dom: focusin', event);
        });

        form.addEventListener('input', function (event) {
            pushDomEvent(form, 'dom: input', event);
            writeHiddenPayload(form);
        });

        form.addEventListener('change', function (event) {
            pushDomEvent(form, 'dom: change', event);
            writeHiddenPayload(form);
        });

        var sendButton = form.querySelector('button[name="form_action"][value="send_message"]');
        var saveButton = form.querySelector('button[name="form_action"][value="save_project"]');

        if (sendButton) {
            sendButton.addEventListener('click', function () {
                ensureDebugSession(form, sendButton, 'clique_em_enviar_mensagem');
            });
        }

        if (saveButton) {
            saveButton.addEventListener('click', function () {
                ensureDebugSession(form, saveButton, 'clique_em_salvar_projeto');
            });
        }

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || document.activeElement;
            var action = submitter && submitter.name === 'form_action' ? submitter.value : '';

            if (action !== 'send_message' && action !== 'save_project') {
                state.active = true;
                state.action = 'live_editor';
                writeHiddenPayload(form);
                persistState();
                return;
            }

            ensureDebugSession(form, submitter, action === 'save_project' ? 'submit_salvar_projeto' : 'submit_enviar_mensagem');
            push('payload_hidden_preenchido', {
                requestId: state.requestId,
                action: action,
                entriesCount: state.entries.length
            });
            writeHiddenPayload(form);
        });

        window.addEventListener('beforeunload', function () {
            if (!state.active) {
                return;
            }
            push('beforeunload_apos_submit', {
                requestId: state.requestId,
                href: String(window.location.href || '')
            });
            writeHiddenPayload(form);
        });
    });
}());
</script>
<script>
window.messageBatchStatusConfig = <?= json_encode(
    $messageBatchStatusBootstrap,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS
); ?>;
</script>
<script>
window.messageEditorConfig = <?= json_encode([
    'sceneJson' => $draft['scene_json'],
    'fabricSceneJson' => $draft['fabric_scene_json'],
    'editorEngine' => $draft['editor_engine'],
    'currentHeroImageUrl' => $currentHeroImageUrl,
    'emailRenderScalePercent' => (int) ($draft['email_render_scale_percent'] ?? 110),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS); ?>;
</script>
<script type="application/json" id="message-editor-initial-scene"><?= $messageEditorBootSceneJson ?></script>
<script type="application/json" id="message-editor-initial-layers"><?= $messageEditorBootLayersJson ?></script>
<script src="<?= e(asset_url('assets/js/admin-message-batch-status.js')); ?>"></script>
<script src="<?= e(asset_url('assets/vendor/fabric.min.js')); ?>"></script>
<script src="<?= e(asset_url('assets/js/message-scene-renderer.js')); ?>"></script>
<script src="<?= e(asset_url('assets/js/admin-message-editor-v2.js')); ?>"></script>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
