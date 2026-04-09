<?php
declare(strict_types=1);

const MESSAGE_QUEUE_BATCH_STATUS_QUEUED = 'queued';
const MESSAGE_QUEUE_BATCH_STATUS_PROCESSING = 'processing';
const MESSAGE_QUEUE_BATCH_STATUS_COMPLETED = 'completed';
const MESSAGE_QUEUE_BATCH_STATUS_COMPLETED_WITH_FAILURES = 'completed_with_failures';
const MESSAGE_QUEUE_BATCH_STATUS_FAILED = 'failed';
const MESSAGE_QUEUE_BATCH_STATUS_CANCELLED = 'cancelled';

const MESSAGE_QUEUE_JOB_STATUS_PENDING = 'pending';
const MESSAGE_QUEUE_JOB_STATUS_RESERVED = 'reserved';
const MESSAGE_QUEUE_JOB_STATUS_PROCESSING = 'processing';
const MESSAGE_QUEUE_JOB_STATUS_RETRY = 'retry';
const MESSAGE_QUEUE_JOB_STATUS_SENT = 'sent';
const MESSAGE_QUEUE_JOB_STATUS_FAILED = 'failed';
const MESSAGE_QUEUE_JOB_STATUS_CANCELLED = 'cancelled';

const MESSAGE_QUEUE_CHANNEL_STATUS_PENDING = 'pending';
const MESSAGE_QUEUE_CHANNEL_STATUS_PROCESSING = 'processing';
const MESSAGE_QUEUE_CHANNEL_STATUS_RETRY = 'retry';
const MESSAGE_QUEUE_CHANNEL_STATUS_SENT = 'sent';
const MESSAGE_QUEUE_CHANNEL_STATUS_FAILED = 'failed';
const MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED = 'skipped';

const MESSAGE_QUEUE_ERROR_CLASS_TRANSIENT = 'transient';
const MESSAGE_QUEUE_ERROR_CLASS_PERMANENT = 'permanent';
const MESSAGE_QUEUE_DEFAULT_LEASE_SECONDS = 300;
const MESSAGE_QUEUE_DEFAULT_MAX_ATTEMPTS = 4;

function message_queue_tables_ready(): bool
{
    static $ready = null;

    if (is_bool($ready)) {
        return $ready;
    }

    $ready = table_exists('message_batches')
        && table_exists('message_batch_jobs')
        && table_exists('message_send_log');

    return $ready;
}

function message_queue_is_list(array $value): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($value);
    }

    return array_keys($value) === range(0, count($value) - 1);
}

function message_queue_normalize_for_hash($value)
{
    if (is_array($value)) {
        if (!message_queue_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = message_queue_normalize_for_hash($item);
        }

        return $value;
    }

    if (is_object($value)) {
        return message_queue_normalize_for_hash((array) $value);
    }

    return $value;
}

function message_queue_json_encode($value): string
{
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : 'null';
}

function message_queue_decode_json_array(?string $raw): array
{
    $raw = trim((string) $raw);

    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function message_queue_hash_payload($value): string
{
    return hash('sha256', message_queue_json_encode(message_queue_normalize_for_hash($value)));
}

function message_queue_now(): string
{
    return date('Y-m-d H:i:s');
}

function message_queue_datetime_in(int $seconds): string
{
    return date('Y-m-d H:i:s', time() + max(0, $seconds));
}

function message_queue_build_batch_public_id(): string
{
    return 'mb_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
}

function message_queue_build_job_public_id(): string
{
    return 'mjob_' . date('YmdHis') . '_' . bin2hex(random_bytes(5));
}

function message_queue_build_recipient_key(array $customerSnapshot): string
{
    $customerId = (int) ($customerSnapshot['id'] ?? 0);
    $email = strtolower(trim((string) ($customerSnapshot['email'] ?? '')));
    $name = trim((string) ($customerSnapshot['nome'] ?? ''));

    if ($customerId > 0) {
        return hash('sha256', 'customer:' . $customerId);
    }

    if ($email !== '') {
        return hash('sha256', 'email:' . $email);
    }

    return hash('sha256', 'snapshot:' . message_queue_json_encode($customerSnapshot) . '|' . $name);
}

function message_queue_build_job_dedupe_key(string $batchPublicId, string $recipientKey): string
{
    return hash('sha256', $batchPublicId . '|' . $recipientKey);
}

function message_queue_current_smtp_profile_snapshot(): array
{
    return [
        'driver' => defined('MAIL_DRIVER') ? (string) MAIL_DRIVER : '',
        'host' => defined('MAIL_SMTP_HOST') ? (string) MAIL_SMTP_HOST : '',
        'port' => defined('MAIL_SMTP_PORT') ? (int) MAIL_SMTP_PORT : 0,
        'security' => defined('MAIL_SMTP_SECURITY') ? (string) MAIL_SMTP_SECURITY : '',
        'timeout' => defined('MAIL_SMTP_TIMEOUT') ? (int) MAIL_SMTP_TIMEOUT : 0,
        'ehlo' => defined('MAIL_SMTP_EHLO') ? (string) MAIL_SMTP_EHLO : '',
        'from_address' => defined('MAIL_FROM_ADDRESS') ? (string) MAIL_FROM_ADDRESS : '',
        'from_name' => defined('MAIL_FROM_NAME') ? (string) MAIL_FROM_NAME : '',
        'reply_to_address' => defined('MAIL_REPLY_TO_ADDRESS') ? (string) MAIL_REPLY_TO_ADDRESS : '',
        'reply_to_name' => defined('MAIL_REPLY_TO_NAME') ? (string) MAIL_REPLY_TO_NAME : '',
    ];
}

function message_queue_fetch_batch(int $batchId): ?array
{
    if ($batchId <= 0 || !message_queue_tables_ready()) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM message_batches WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $batchId]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function message_queue_fetch_batch_by_public_id(string $publicId): ?array
{
    $publicId = trim($publicId);

    if ($publicId === '' || !message_queue_tables_ready()) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM message_batches WHERE public_id = :public_id LIMIT 1');
    $statement->execute(['public_id' => $publicId]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function message_queue_fetch_batch_by_idempotency_key(string $idempotencyKey): ?array
{
    $idempotencyKey = trim($idempotencyKey);

    if ($idempotencyKey === '' || !message_queue_tables_ready()) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM message_batches WHERE idempotency_key = :idempotency_key LIMIT 1');
    $statement->execute(['idempotency_key' => $idempotencyKey]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function message_queue_fetch_job(int $jobId): ?array
{
    if ($jobId <= 0 || !message_queue_tables_ready()) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM message_batch_jobs WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $jobId]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function message_queue_backoff_seconds(int $attempts): int
{
    return match (true) {
        $attempts <= 1 => 0,
        $attempts === 2 => 120,
        $attempts === 3 => 600,
        default => 1800,
    };
}

function message_queue_error_excerpt(?string $value, int $limit = 255): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (strlen($value) <= $limit) {
        return $value;
    }

    return substr($value, 0, max(0, $limit - 3)) . '...';
}

function message_queue_error_detail_json($value): ?string
{
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    return message_queue_json_encode($value);
}

function message_queue_derive_job_status(array $job, ?string $notificationStatus = null, ?string $emailStatus = null): string
{
    $sendNotification = !empty($job['send_notification']);
    $sendEmail = !empty($job['send_email']);
    $notificationStatus = $notificationStatus ?? (string) ($job['notification_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);
    $emailStatus = $emailStatus ?? (string) ($job['email_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);
    $activeStatuses = [];

    if ($sendNotification) {
        $activeStatuses[] = $notificationStatus;
    }

    if ($sendEmail) {
        $activeStatuses[] = $emailStatus;
    }

    if ($activeStatuses === []) {
        return MESSAGE_QUEUE_JOB_STATUS_SENT;
    }

    if (in_array(MESSAGE_QUEUE_CHANNEL_STATUS_RETRY, $activeStatuses, true)) {
        return MESSAGE_QUEUE_JOB_STATUS_RETRY;
    }

    if (in_array(MESSAGE_QUEUE_CHANNEL_STATUS_FAILED, $activeStatuses, true)) {
        return MESSAGE_QUEUE_JOB_STATUS_FAILED;
    }

    if (
        in_array(MESSAGE_QUEUE_CHANNEL_STATUS_PENDING, $activeStatuses, true)
        || in_array(MESSAGE_QUEUE_CHANNEL_STATUS_PROCESSING, $activeStatuses, true)
    ) {
        return MESSAGE_QUEUE_JOB_STATUS_PROCESSING;
    }

    return MESSAGE_QUEUE_JOB_STATUS_SENT;
}

function message_queue_channel_can_run(array $job, string $channel): bool
{
    $channel = trim($channel);

    if ($channel === 'notification') {
        if (empty($job['send_notification'])) {
            return false;
        }

        return in_array((string) ($job['notification_status'] ?? ''), [
            MESSAGE_QUEUE_CHANNEL_STATUS_PENDING,
            MESSAGE_QUEUE_CHANNEL_STATUS_RETRY,
        ], true);
    }

    if ($channel === 'email') {
        if (empty($job['send_email'])) {
            return false;
        }

        return in_array((string) ($job['email_status'] ?? ''), [
            MESSAGE_QUEUE_CHANNEL_STATUS_PENDING,
            MESSAGE_QUEUE_CHANNEL_STATUS_RETRY,
        ], true);
    }

    return false;
}

function message_queue_batch_status_from_counters(array $counters): string
{
    $total = max(0, (int) ($counters['total_jobs'] ?? 0));
    $pending = max(0, (int) ($counters['pending_jobs'] ?? 0));
    $reserved = max(0, (int) ($counters['reserved_jobs'] ?? 0));
    $processing = max(0, (int) ($counters['processing_jobs'] ?? 0));
    $retry = max(0, (int) ($counters['retry_jobs'] ?? 0));
    $sent = max(0, (int) ($counters['sent_jobs'] ?? 0));
    $failed = max(0, (int) ($counters['failed_jobs'] ?? 0));
    $cancelled = max(0, (int) ($counters['cancelled_jobs'] ?? 0));

    if ($total === 0) {
        return MESSAGE_QUEUE_BATCH_STATUS_QUEUED;
    }

    if (($reserved + $processing) > 0) {
        return MESSAGE_QUEUE_BATCH_STATUS_PROCESSING;
    }

    if (($pending + $retry) > 0) {
        return ($sent + $failed + $cancelled) > 0
            ? MESSAGE_QUEUE_BATCH_STATUS_PROCESSING
            : MESSAGE_QUEUE_BATCH_STATUS_QUEUED;
    }

    if ($sent > 0 && ($failed > 0 || $cancelled > 0)) {
        return MESSAGE_QUEUE_BATCH_STATUS_COMPLETED_WITH_FAILURES;
    }

    if ($sent > 0) {
        return MESSAGE_QUEUE_BATCH_STATUS_COMPLETED;
    }

    if ($failed > 0) {
        return MESSAGE_QUEUE_BATCH_STATUS_FAILED;
    }

    if ($cancelled === $total) {
        return MESSAGE_QUEUE_BATCH_STATUS_CANCELLED;
    }

    return MESSAGE_QUEUE_BATCH_STATUS_QUEUED;
}

function message_queue_refresh_batch_counters(int $batchId): array
{
    $batch = message_queue_fetch_batch($batchId);

    if ($batch === null) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT
            COUNT(*) AS total_jobs,
            SUM(CASE WHEN status = :pending THEN 1 ELSE 0 END) AS pending_jobs,
            SUM(CASE WHEN status = :reserved THEN 1 ELSE 0 END) AS reserved_jobs,
            SUM(CASE WHEN status = :processing THEN 1 ELSE 0 END) AS processing_jobs,
            SUM(CASE WHEN status = :retry THEN 1 ELSE 0 END) AS retry_jobs,
            SUM(CASE WHEN status = :sent THEN 1 ELSE 0 END) AS sent_jobs,
            SUM(CASE WHEN status = :failed THEN 1 ELSE 0 END) AS failed_jobs,
            SUM(CASE WHEN status = :cancelled THEN 1 ELSE 0 END) AS cancelled_jobs
         FROM message_batch_jobs
         WHERE batch_id = :batch_id'
    );
    $statement->execute([
        'batch_id' => $batchId,
        'pending' => MESSAGE_QUEUE_JOB_STATUS_PENDING,
        'reserved' => MESSAGE_QUEUE_JOB_STATUS_RESERVED,
        'processing' => MESSAGE_QUEUE_JOB_STATUS_PROCESSING,
        'retry' => MESSAGE_QUEUE_JOB_STATUS_RETRY,
        'sent' => MESSAGE_QUEUE_JOB_STATUS_SENT,
        'failed' => MESSAGE_QUEUE_JOB_STATUS_FAILED,
        'cancelled' => MESSAGE_QUEUE_JOB_STATUS_CANCELLED,
    ]);
    $counters = (array) $statement->fetch();
    $status = message_queue_batch_status_from_counters($counters);
    $hasStartedWork = (
        ((int) ($counters['reserved_jobs'] ?? 0))
        + ((int) ($counters['processing_jobs'] ?? 0))
        + ((int) ($counters['retry_jobs'] ?? 0))
        + ((int) ($counters['sent_jobs'] ?? 0))
        + ((int) ($counters['failed_jobs'] ?? 0))
    ) > 0;
    $isTerminal = in_array($status, [
        MESSAGE_QUEUE_BATCH_STATUS_COMPLETED,
        MESSAGE_QUEUE_BATCH_STATUS_COMPLETED_WITH_FAILURES,
        MESSAGE_QUEUE_BATCH_STATUS_FAILED,
        MESSAGE_QUEUE_BATCH_STATUS_CANCELLED,
    ], true);
    $now = message_queue_now();

    $update = db()->prepare(
        'UPDATE message_batches
         SET status = :status,
             total_jobs = :total_jobs,
             pending_jobs = :pending_jobs,
             reserved_jobs = :reserved_jobs,
             processing_jobs = :processing_jobs,
             retry_jobs = :retry_jobs,
             sent_jobs = :sent_jobs,
             failed_jobs = :failed_jobs,
             cancelled_jobs = :cancelled_jobs,
             started_at = CASE
                 WHEN started_at IS NULL AND :has_started = 1 THEN :started_at
                 ELSE started_at
             END,
             finished_at = CASE
                 WHEN :is_terminal = 1 THEN COALESCE(finished_at, :finished_at)
                 ELSE NULL
             END
         WHERE id = :id'
    );
    $update->execute([
        'id' => $batchId,
        'status' => $status,
        'total_jobs' => (int) ($counters['total_jobs'] ?? 0),
        'pending_jobs' => (int) ($counters['pending_jobs'] ?? 0),
        'reserved_jobs' => (int) ($counters['reserved_jobs'] ?? 0),
        'processing_jobs' => (int) ($counters['processing_jobs'] ?? 0),
        'retry_jobs' => (int) ($counters['retry_jobs'] ?? 0),
        'sent_jobs' => (int) ($counters['sent_jobs'] ?? 0),
        'failed_jobs' => (int) ($counters['failed_jobs'] ?? 0),
        'cancelled_jobs' => (int) ($counters['cancelled_jobs'] ?? 0),
        'has_started' => $hasStartedWork ? 1 : 0,
        'started_at' => $now,
        'is_terminal' => $isTerminal ? 1 : 0,
        'finished_at' => $now,
    ]);

    return message_queue_fetch_batch($batchId) ?? [];
}

function message_queue_log_event(array $payload): void
{
    if (!message_queue_tables_ready()) {
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO message_send_log (
            batch_id, job_id, attempt_number, channel, stage, status, worker_id,
            error_class, error_code, summary, details_json
         ) VALUES (
            :batch_id, :job_id, :attempt_number, :channel, :stage, :status, :worker_id,
            :error_class, :error_code, :summary, :details_json
         )'
    );
    $statement->execute([
        'batch_id' => (int) ($payload['batch_id'] ?? 0),
        'job_id' => isset($payload['job_id']) ? (int) $payload['job_id'] : null,
        'attempt_number' => max(0, (int) ($payload['attempt_number'] ?? 0)),
        'channel' => trim((string) ($payload['channel'] ?? 'job')) ?: 'job',
        'stage' => trim((string) ($payload['stage'] ?? 'unknown')) ?: 'unknown',
        'status' => trim((string) ($payload['status'] ?? 'info')) ?: 'info',
        'worker_id' => trim((string) ($payload['worker_id'] ?? '')) ?: null,
        'error_class' => trim((string) ($payload['error_class'] ?? '')) ?: null,
        'error_code' => trim((string) ($payload['error_code'] ?? '')) ?: null,
        'summary' => trim((string) ($payload['summary'] ?? '')) ?: null,
        'details_json' => array_key_exists('details', $payload)
            ? message_queue_json_encode($payload['details'])
            : null,
    ]);
}

function message_queue_fetch_batch_recent_errors(int $batchId, int $limit = 10): array
{
    if ($batchId <= 0 || !message_queue_tables_ready()) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $statement = db()->prepare(
        'SELECT *
         FROM message_send_log
         WHERE batch_id = :batch_id
           AND (
               status IN (\'failed\', \'retry\')
               OR error_class IS NOT NULL
               OR error_code IS NOT NULL
           )
         ORDER BY created_at DESC, id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['batch_id' => $batchId]);

    return $statement->fetchAll();
}

function message_queue_fetch_batch_progress(int $batchId): array
{
    $batch = message_queue_refresh_batch_counters($batchId);

    if ($batch === []) {
        return [];
    }

    return [
        'batch' => $batch,
        'recent_errors' => message_queue_fetch_batch_recent_errors($batchId, 10),
    ];
}
