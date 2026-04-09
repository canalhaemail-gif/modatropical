<?php
declare(strict_types=1);

function admin_message_batch_is_terminal(string $status): bool
{
    return in_array(trim($status), [
        MESSAGE_QUEUE_BATCH_STATUS_COMPLETED,
        MESSAGE_QUEUE_BATCH_STATUS_COMPLETED_WITH_FAILURES,
        MESSAGE_QUEUE_BATCH_STATUS_FAILED,
        MESSAGE_QUEUE_BATCH_STATUS_CANCELLED,
    ], true);
}

function admin_message_batch_status_label(string $status): string
{
    return match (trim($status)) {
        MESSAGE_QUEUE_BATCH_STATUS_QUEUED => 'Na fila',
        MESSAGE_QUEUE_BATCH_STATUS_PROCESSING => 'Processando',
        MESSAGE_QUEUE_BATCH_STATUS_COMPLETED => 'Concluido',
        MESSAGE_QUEUE_BATCH_STATUS_COMPLETED_WITH_FAILURES => 'Concluido com falhas',
        MESSAGE_QUEUE_BATCH_STATUS_FAILED => 'Falhou',
        MESSAGE_QUEUE_BATCH_STATUS_CANCELLED => 'Cancelado',
        default => 'Desconhecido',
    };
}

function admin_message_batch_status_tone(string $status): string
{
    return match (trim($status)) {
        MESSAGE_QUEUE_BATCH_STATUS_COMPLETED => 'success',
        MESSAGE_QUEUE_BATCH_STATUS_COMPLETED_WITH_FAILURES => 'warning',
        MESSAGE_QUEUE_BATCH_STATUS_FAILED => 'danger',
        MESSAGE_QUEUE_BATCH_STATUS_CANCELLED => 'muted',
        MESSAGE_QUEUE_BATCH_STATUS_PROCESSING => 'processing',
        default => 'queued',
    };
}

function admin_message_batch_datetime_label(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return null;
    }

    return date('d/m/Y H:i:s', $timestamp);
}

function admin_message_batch_recent_errors_payload(array $rows): array
{
    $errors = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $summary = trim((string) ($row['summary'] ?? ''));
        $errorCode = trim((string) ($row['error_code'] ?? ''));
        $stage = trim((string) ($row['stage'] ?? ''));

        if ($summary === '') {
            $summary = $errorCode !== '' ? $errorCode : ($stage !== '' ? $stage : 'Erro sem resumo');
        }

        $errors[] = [
            'created_at' => trim((string) ($row['created_at'] ?? '')),
            'created_at_label' => admin_message_batch_datetime_label((string) ($row['created_at'] ?? '')),
            'channel' => trim((string) ($row['channel'] ?? 'job')),
            'stage' => $stage,
            'status' => trim((string) ($row['status'] ?? '')),
            'error_class' => trim((string) ($row['error_class'] ?? '')),
            'error_code' => $errorCode,
            'summary' => $summary,
        ];
    }

    return $errors;
}

function admin_message_batch_progress_payload(array $progress): array
{
    $batch = (array) ($progress['batch'] ?? []);

    if ($batch === []) {
        return [];
    }

    $counts = [
        'total_jobs' => max(0, (int) ($batch['total_jobs'] ?? 0)),
        'pending_jobs' => max(0, (int) ($batch['pending_jobs'] ?? 0)),
        'reserved_jobs' => max(0, (int) ($batch['reserved_jobs'] ?? 0)),
        'processing_jobs' => max(0, (int) ($batch['processing_jobs'] ?? 0)),
        'retry_jobs' => max(0, (int) ($batch['retry_jobs'] ?? 0)),
        'sent_jobs' => max(0, (int) ($batch['sent_jobs'] ?? 0)),
        'failed_jobs' => max(0, (int) ($batch['failed_jobs'] ?? 0)),
        'cancelled_jobs' => max(0, (int) ($batch['cancelled_jobs'] ?? 0)),
    ];
    $processedJobs = $counts['sent_jobs'] + $counts['failed_jobs'] + $counts['cancelled_jobs'];
    $completionPercent = $counts['total_jobs'] > 0
        ? (int) round(($processedJobs / $counts['total_jobs']) * 100)
        : 0;
    $completionPercent = max(0, min(100, $completionPercent));
    $status = trim((string) ($batch['status'] ?? MESSAGE_QUEUE_BATCH_STATUS_QUEUED));

    return [
        'id' => (int) ($batch['id'] ?? 0),
        'public_id' => trim((string) ($batch['public_id'] ?? '')),
        'status' => $status,
        'status_label' => admin_message_batch_status_label($status),
        'status_tone' => admin_message_batch_status_tone($status),
        'is_terminal' => admin_message_batch_is_terminal($status),
        'counts' => $counts,
        'completion_percent' => $completionPercent,
        'created_at' => trim((string) ($batch['created_at'] ?? '')),
        'created_at_label' => admin_message_batch_datetime_label((string) ($batch['created_at'] ?? '')),
        'started_at' => trim((string) ($batch['started_at'] ?? '')),
        'started_at_label' => admin_message_batch_datetime_label((string) ($batch['started_at'] ?? '')),
        'finished_at' => trim((string) ($batch['finished_at'] ?? '')),
        'finished_at_label' => admin_message_batch_datetime_label((string) ($batch['finished_at'] ?? '')),
        'recent_errors' => admin_message_batch_recent_errors_payload((array) ($progress['recent_errors'] ?? [])),
    ];
}

function admin_message_batch_progress_from_batch_id(int $batchId): array
{
    if ($batchId <= 0 || !message_queue_tables_ready()) {
        return [];
    }

    $progress = message_queue_fetch_batch_progress($batchId);

    return $progress === [] ? [] : admin_message_batch_progress_payload($progress);
}

function admin_message_batch_progress_from_public_id(string $publicId): array
{
    $publicId = trim($publicId);

    if ($publicId === '' || !message_queue_tables_ready()) {
        return [];
    }

    $batch = message_queue_fetch_batch_by_public_id($publicId);

    if (!is_array($batch) || (int) ($batch['id'] ?? 0) <= 0) {
        return [];
    }

    return admin_message_batch_progress_from_batch_id((int) $batch['id']);
}

function admin_message_batch_progress_from_last_session(): array
{
    $publicId = trim((string) ($_SESSION['admin_message_last_batch_public_id'] ?? ''));

    if ($publicId === '') {
        return [];
    }

    $payload = admin_message_batch_progress_from_public_id($publicId);

    if ($payload === []) {
        unset($_SESSION['admin_message_last_batch_public_id']);
    }

    return $payload;
}
