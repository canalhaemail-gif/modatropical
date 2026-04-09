<?php
declare(strict_types=1);

function message_queue_stale_channel_status(
    array $job,
    string $channel,
    string $channelStatus,
    bool $sendEnabled,
    bool $isFinal
): string
{
    if (!$sendEnabled) {
        return MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED;
    }

    $channelStatus = trim($channelStatus);

    if ($channelStatus === MESSAGE_QUEUE_CHANNEL_STATUS_SENT) {
        return MESSAGE_QUEUE_CHANNEL_STATUS_SENT;
    }

    if ($channelStatus === MESSAGE_QUEUE_CHANNEL_STATUS_FAILED && $isFinal) {
        return MESSAGE_QUEUE_CHANNEL_STATUS_FAILED;
    }

    if ($channelStatus === MESSAGE_QUEUE_CHANNEL_STATUS_FAILED && !$isFinal) {
        return MESSAGE_QUEUE_CHANNEL_STATUS_FAILED;
    }

    if ($channel === 'email' && $channelStatus === MESSAGE_QUEUE_CHANNEL_STATUS_PROCESSING) {
        return MESSAGE_QUEUE_CHANNEL_STATUS_FAILED;
    }

    return $isFinal
        ? MESSAGE_QUEUE_CHANNEL_STATUS_FAILED
        : MESSAGE_QUEUE_CHANNEL_STATUS_RETRY;
}

function message_queue_reclaim_stale_jobs(int $limit = 25): int
{
    if (!message_queue_tables_ready()) {
        return 0;
    }

    $limit = max(1, min(250, $limit));
    $now = message_queue_now();
    $batchIds = [];

    db()->beginTransaction();

    try {
        $select = db()->query(
            'SELECT id, batch_id, attempts, max_attempts, send_notification, send_email, notification_status, email_status
             FROM message_batch_jobs
             WHERE status IN (\'' . MESSAGE_QUEUE_JOB_STATUS_RESERVED . '\', \'' . MESSAGE_QUEUE_JOB_STATUS_PROCESSING . '\')
               AND lease_until IS NOT NULL
               AND lease_until < ' . db()->quote($now) . '
             ORDER BY lease_until ASC, id ASC
             LIMIT ' . $limit . '
             FOR UPDATE'
        );
        $rows = $select->fetchAll();
        $update = db()->prepare(
            'UPDATE message_batch_jobs
             SET status = :status,
                 notification_status = :notification_status,
                 email_status = :email_status,
                 available_at = :available_at,
                 reserved_at = NULL,
                 processing_at = NULL,
                 heartbeat_at = NULL,
                 lease_until = NULL,
                 worker_id = NULL,
                 reservation_token = NULL,
                 last_stage = :last_stage,
                 last_error_class = :last_error_class,
                 last_error_code = :last_error_code,
                 last_error_excerpt = :last_error_excerpt,
                 finished_at = :finished_at
             WHERE id = :id'
        );

        foreach ($rows as $row) {
            $attempts = (int) ($row['attempts'] ?? 0);
            $maxAttempts = max(1, (int) ($row['max_attempts'] ?? MESSAGE_QUEUE_DEFAULT_MAX_ATTEMPTS));
            $isFinal = $attempts >= $maxAttempts;
            $notificationStatus = message_queue_stale_channel_status(
                $row,
                'notification',
                (string) ($row['notification_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_PENDING),
                !empty($row['send_notification']),
                $isFinal
            );
            $emailStatus = message_queue_stale_channel_status(
                $row,
                'email',
                (string) ($row['email_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_PENDING),
                !empty($row['send_email']),
                $isFinal
            );
            $status = message_queue_derive_job_status($row, $notificationStatus, $emailStatus);
            $emailWasAmbiguous = !empty($row['send_email'])
                && (string) ($row['email_status'] ?? '') === MESSAGE_QUEUE_CHANNEL_STATUS_PROCESSING
                && $emailStatus === MESSAGE_QUEUE_CHANNEL_STATUS_FAILED;
            $errorCode = $emailWasAmbiguous ? 'lease_expired_email_review' : 'lease_expired';
            $errorExcerpt = $emailWasAmbiguous
                ? 'Job parado durante envio de email; marcado como falha segura para evitar duplicidade.'
                : 'Job recuperado apos lease expirada.';
            $stage = $isFinal || $emailWasAmbiguous ? 'stale_failed_final' : 'stale_requeued';
            $availableAt = ($isFinal || $emailWasAmbiguous)
                ? $now
                : message_queue_datetime_in(message_queue_backoff_seconds($attempts + 1));
            $update->execute([
                'id' => (int) $row['id'],
                'status' => $status,
                'notification_status' => $notificationStatus,
                'email_status' => $emailStatus,
                'available_at' => $availableAt,
                'last_stage' => $stage,
                'last_error_class' => MESSAGE_QUEUE_ERROR_CLASS_TRANSIENT,
                'last_error_code' => $errorCode,
                'last_error_excerpt' => $errorExcerpt,
                'finished_at' => ($isFinal || $emailWasAmbiguous) ? $now : null,
            ]);
            $batchIds[] = (int) $row['batch_id'];
        }

        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }

    foreach (array_values(array_unique($batchIds)) as $batchId) {
        message_queue_refresh_batch_counters((int) $batchId);
    }

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $attempts = (int) ($row['attempts'] ?? 0);
            $maxAttempts = max(1, (int) ($row['max_attempts'] ?? MESSAGE_QUEUE_DEFAULT_MAX_ATTEMPTS));
            $isFinal = $attempts >= $maxAttempts;
            $emailWasAmbiguous = !empty($row['send_email'])
                && (string) ($row['email_status'] ?? '') === MESSAGE_QUEUE_CHANNEL_STATUS_PROCESSING;
            message_queue_log_event([
                'batch_id' => (int) ($row['batch_id'] ?? 0),
                'job_id' => (int) ($row['id'] ?? 0),
                'attempt_number' => $attempts,
                'channel' => 'job',
                'stage' => ($isFinal || $emailWasAmbiguous) ? 'stale_failed_final' : 'stale_requeued',
                'status' => ($isFinal || $emailWasAmbiguous) ? 'failed' : 'retry',
                'error_class' => MESSAGE_QUEUE_ERROR_CLASS_TRANSIENT,
                'error_code' => $emailWasAmbiguous ? 'lease_expired_email_review' : 'lease_expired',
                'summary' => $emailWasAmbiguous
                    ? 'Lease expirada durante envio de email; job parado em falha segura para evitar duplicidade.'
                    : 'Lease expirada; job recolocado para tratamento seguro.',
            ]);
        }
    }

    return isset($rows) ? count($rows) : 0;
}

function message_queue_reserve_jobs(string $workerId, int $limit = 1, int $leaseSeconds = MESSAGE_QUEUE_DEFAULT_LEASE_SECONDS): array
{
    if (!message_queue_tables_ready()) {
        return [];
    }

    $workerId = trim($workerId);
    if ($workerId === '') {
        throw new RuntimeException('worker_id e obrigatorio para reservar jobs.');
    }

    message_queue_reclaim_stale_jobs();

    $limit = max(1, min(100, $limit));
    $leaseSeconds = max(30, $leaseSeconds);
    $now = message_queue_now();
    $leaseUntil = message_queue_datetime_in($leaseSeconds);
    $reservationToken = bin2hex(random_bytes(16));
    $batchIds = [];

    db()->beginTransaction();

    try {
        $statement = db()->query(
            'SELECT id, batch_id
             FROM message_batch_jobs
             WHERE status IN (\'' . MESSAGE_QUEUE_JOB_STATUS_PENDING . '\', \'' . MESSAGE_QUEUE_JOB_STATUS_RETRY . '\')
               AND available_at <= ' . db()->quote($now) . '
             ORDER BY available_at ASC, id ASC
             LIMIT ' . $limit . '
             FOR UPDATE'
        );
        $rows = $statement->fetchAll();

        if ($rows === []) {
            db()->commit();
            return [];
        }

        $update = db()->prepare(
            'UPDATE message_batch_jobs
             SET status = :status,
                 attempts = attempts + 1,
                 reserved_at = :reserved_at,
                 heartbeat_at = :heartbeat_at,
                 lease_until = :lease_until,
                 worker_id = :worker_id,
                 reservation_token = :reservation_token,
                 last_stage = :last_stage
             WHERE id = :id'
        );

        foreach ($rows as $row) {
            $update->execute([
                'id' => (int) $row['id'],
                'status' => MESSAGE_QUEUE_JOB_STATUS_RESERVED,
                'reserved_at' => $now,
                'heartbeat_at' => $now,
                'lease_until' => $leaseUntil,
                'worker_id' => $workerId,
                'reservation_token' => $reservationToken,
                'last_stage' => 'reserved',
            ]);
            $batchIds[] = (int) ($row['batch_id'] ?? 0);
        }

        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }

    $fetch = db()->prepare(
        'SELECT *
         FROM message_batch_jobs
         WHERE reservation_token = :reservation_token
         ORDER BY id ASC'
    );
    $fetch->execute(['reservation_token' => $reservationToken]);
    $reservedJobs = $fetch->fetchAll();

    foreach (array_values(array_unique($batchIds)) as $batchId) {
        message_queue_refresh_batch_counters((int) $batchId);
    }

    foreach ($reservedJobs as $job) {
        message_queue_log_event([
            'batch_id' => (int) ($job['batch_id'] ?? 0),
            'job_id' => (int) ($job['id'] ?? 0),
            'attempt_number' => (int) ($job['attempts'] ?? 0),
            'channel' => 'job',
            'stage' => 'reserved',
            'status' => 'info',
            'worker_id' => $workerId,
            'summary' => 'Job reservado para processamento.',
            'details' => [
                'reservation_token' => $reservationToken,
                'lease_until' => $leaseUntil,
            ],
        ]);
    }

    return $reservedJobs;
}

function message_queue_mark_job_processing(int $jobId, string $workerId, int $leaseSeconds = MESSAGE_QUEUE_DEFAULT_LEASE_SECONDS): bool
{
    if ($jobId <= 0 || trim($workerId) === '' || !message_queue_tables_ready()) {
        return false;
    }

    $now = message_queue_now();
    $statement = db()->prepare(
        'UPDATE message_batch_jobs
         SET status = :status,
             processing_at = :processing_at,
             heartbeat_at = :heartbeat_at,
             lease_until = :lease_until,
             last_stage = :last_stage
         WHERE id = :id
           AND worker_id = :worker_id
           AND status = :current_status'
    );
    $updated = $statement->execute([
        'id' => $jobId,
        'worker_id' => trim($workerId),
        'status' => MESSAGE_QUEUE_JOB_STATUS_PROCESSING,
        'current_status' => MESSAGE_QUEUE_JOB_STATUS_RESERVED,
        'processing_at' => $now,
        'heartbeat_at' => $now,
        'lease_until' => message_queue_datetime_in(max(30, $leaseSeconds)),
        'last_stage' => 'processing',
    ]);

    if (!$updated || $statement->rowCount() <= 0) {
        return false;
    }

    $job = message_queue_fetch_job($jobId);
    if ($job !== null) {
        message_queue_refresh_batch_counters((int) ($job['batch_id'] ?? 0));
        message_queue_log_event([
            'batch_id' => (int) ($job['batch_id'] ?? 0),
            'job_id' => $jobId,
            'attempt_number' => (int) ($job['attempts'] ?? 0),
            'channel' => 'job',
            'stage' => 'processing',
            'status' => 'info',
            'worker_id' => trim($workerId),
            'summary' => 'Job entrou em processamento.',
        ]);
    }

    return true;
}

function message_queue_touch_job_lease(int $jobId, string $workerId, int $leaseSeconds = MESSAGE_QUEUE_DEFAULT_LEASE_SECONDS): bool
{
    if ($jobId <= 0 || trim($workerId) === '' || !message_queue_tables_ready()) {
        return false;
    }

    $now = message_queue_now();
    $params = [
        'id' => $jobId,
        'worker_id' => trim($workerId),
        'heartbeat_at' => $now,
        'lease_until' => message_queue_datetime_in(max(30, $leaseSeconds)),
        'reserved_status' => MESSAGE_QUEUE_JOB_STATUS_RESERVED,
        'processing_status' => MESSAGE_QUEUE_JOB_STATUS_PROCESSING,
    ];
    $fallback = null;

    try {
        $statement = db()->prepare(
            'UPDATE message_batch_jobs
             SET heartbeat_at = :heartbeat_at,
                 lease_until = :lease_until
             WHERE id = :id
               AND worker_id = :worker_id
               AND status IN (:reserved_status, :processing_status)'
        );
        $updated = $statement->execute($params);
        $affectedRows = $statement->rowCount();
    } catch (PDOException) {
        $fallback = db()->prepare(
            'UPDATE message_batch_jobs
             SET heartbeat_at = :heartbeat_at,
                 lease_until = :lease_until
             WHERE id = :id
               AND worker_id = :worker_id
               AND (status = :reserved_status OR status = :processing_status)'
        );
        $updated = $fallback->execute($params);
        $affectedRows = $fallback->rowCount();
    }

    return $updated && $affectedRows > 0;
}
