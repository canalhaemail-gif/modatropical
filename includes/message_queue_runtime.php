<?php
declare(strict_types=1);

function message_queue_locked_job_update(int $jobId, ?string $workerId, callable $callback): ?array
{
    if ($jobId <= 0 || !message_queue_tables_ready()) {
        return null;
    }

    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'SELECT *
             FROM message_batch_jobs
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['id' => $jobId]);
        $job = $statement->fetch();

        if (!is_array($job)) {
            db()->rollBack();
            return null;
        }

        if ($workerId !== null && trim((string) ($job['worker_id'] ?? '')) !== trim($workerId)) {
            db()->rollBack();
            return null;
        }

        $changes = $callback($job);

        if (!is_array($changes) || $changes === []) {
            db()->commit();
            return $job;
        }

        $allowedFields = [
            'notification_status',
            'email_status',
            'status',
            'available_at',
            'reserved_at',
            'processing_at',
            'heartbeat_at',
            'lease_until',
            'worker_id',
            'reservation_token',
            'render_cache_key',
            'render_path',
            'last_stage',
            'last_error_class',
            'last_error_code',
            'last_error_excerpt',
            'last_error_detail',
            'notification_sent_at',
            'email_sent_at',
            'finished_at',
        ];
        $setParts = [];
        $params = ['id' => $jobId];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $changes)) {
                continue;
            }

            $setParts[] = $field . ' = :' . $field;
            $params[$field] = $changes[$field];
        }

        if ($setParts !== []) {
            $update = db()->prepare(
                'UPDATE message_batch_jobs
                 SET ' . implode(', ', $setParts) . '
                 WHERE id = :id'
            );
            $update->execute($params);
        }

        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }

    $freshJob = message_queue_fetch_job($jobId);

    if ($freshJob !== null) {
        message_queue_refresh_batch_counters((int) ($freshJob['batch_id'] ?? 0));
    }

    return $freshJob;
}

function message_queue_mark_channel_processing(int $jobId, string $workerId, string $channel, int $leaseSeconds = MESSAGE_QUEUE_DEFAULT_LEASE_SECONDS): ?array
{
    $channel = trim($channel);

    return message_queue_locked_job_update($jobId, $workerId, static function (array $job) use ($channel, $leaseSeconds): array {
        if (!message_queue_channel_can_run($job, $channel)) {
            return [];
        }

        $notificationStatus = (string) ($job['notification_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);
        $emailStatus = (string) ($job['email_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);

        if ($channel === 'notification') {
            $notificationStatus = MESSAGE_QUEUE_CHANNEL_STATUS_PROCESSING;
        } elseif ($channel === 'email') {
            $emailStatus = MESSAGE_QUEUE_CHANNEL_STATUS_PROCESSING;
        }

        return [
            'notification_status' => $notificationStatus,
            'email_status' => $emailStatus,
            'status' => message_queue_derive_job_status($job, $notificationStatus, $emailStatus),
            'heartbeat_at' => message_queue_now(),
            'lease_until' => message_queue_datetime_in(max(30, $leaseSeconds)),
            'last_stage' => $channel . '_processing',
            'last_error_class' => null,
            'last_error_code' => null,
            'last_error_excerpt' => null,
            'last_error_detail' => null,
        ];
    });
}

function message_queue_mark_channel_sent(int $jobId, string $workerId, string $channel, array $meta = []): ?array
{
    $channel = trim($channel);

    return message_queue_locked_job_update($jobId, $workerId, static function (array $job) use ($channel, $meta): array {
        $notificationStatus = (string) ($job['notification_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);
        $emailStatus = (string) ($job['email_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);
        $changes = [
            'heartbeat_at' => message_queue_now(),
            'last_stage' => $channel . '_sent',
        ];

        if ($channel === 'notification') {
            $notificationStatus = MESSAGE_QUEUE_CHANNEL_STATUS_SENT;
            $changes['notification_status'] = $notificationStatus;
            $changes['notification_sent_at'] = $meta['sent_at'] ?? message_queue_now();
        } elseif ($channel === 'email') {
            $emailStatus = MESSAGE_QUEUE_CHANNEL_STATUS_SENT;
            $changes['email_status'] = $emailStatus;
            $changes['email_sent_at'] = $meta['sent_at'] ?? message_queue_now();
            if (array_key_exists('render_cache_key', $meta)) {
                $changes['render_cache_key'] = $meta['render_cache_key'];
            }
            if (array_key_exists('render_path', $meta)) {
                $changes['render_path'] = $meta['render_path'];
            }
        }

        $jobStatus = message_queue_derive_job_status($job, $notificationStatus, $emailStatus);
        $changes['status'] = $jobStatus;

        if ($jobStatus === MESSAGE_QUEUE_JOB_STATUS_SENT) {
            $changes['finished_at'] = message_queue_now();
            $changes['worker_id'] = null;
            $changes['reservation_token'] = null;
            $changes['lease_until'] = null;
            $changes['heartbeat_at'] = null;
        }

        return $changes;
    });
}

function message_queue_mark_job_retry(int $jobId, string $workerId, string $channel, array $failure): ?array
{
    $channel = trim($channel);

    return message_queue_locked_job_update($jobId, $workerId, static function (array $job) use ($channel, $failure): array {
        $notificationStatus = (string) ($job['notification_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);
        $emailStatus = (string) ($job['email_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);

        if ($channel === 'notification' && !empty($job['send_notification'])) {
            $notificationStatus = MESSAGE_QUEUE_CHANNEL_STATUS_RETRY;
        }

        if ($channel === 'email' && !empty($job['send_email'])) {
            $emailStatus = MESSAGE_QUEUE_CHANNEL_STATUS_RETRY;
        }

        return [
            'notification_status' => $notificationStatus,
            'email_status' => $emailStatus,
            'status' => MESSAGE_QUEUE_JOB_STATUS_RETRY,
            'available_at' => $failure['available_at'] ?? message_queue_datetime_in(
                message_queue_backoff_seconds(((int) ($job['attempts'] ?? 0)) + 1)
            ),
            'reserved_at' => null,
            'processing_at' => null,
            'heartbeat_at' => null,
            'lease_until' => null,
            'worker_id' => null,
            'reservation_token' => null,
            'last_stage' => $channel . '_retry',
            'last_error_class' => $failure['error_class'] ?? MESSAGE_QUEUE_ERROR_CLASS_TRANSIENT,
            'last_error_code' => $failure['error_code'] ?? null,
            'last_error_excerpt' => message_queue_error_excerpt($failure['message'] ?? null),
            'last_error_detail' => message_queue_error_detail_json($failure['detail'] ?? null),
            'finished_at' => null,
        ];
    });
}

function message_queue_mark_job_failed(int $jobId, string $workerId, string $channel, array $failure): ?array
{
    $channel = trim($channel);

    return message_queue_locked_job_update($jobId, $workerId, static function (array $job) use ($channel, $failure): array {
        $notificationStatus = (string) ($job['notification_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);
        $emailStatus = (string) ($job['email_status'] ?? MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED);

        if ($channel === 'notification' && !empty($job['send_notification'])) {
            $notificationStatus = MESSAGE_QUEUE_CHANNEL_STATUS_FAILED;
        }

        if ($channel === 'email' && !empty($job['send_email'])) {
            $emailStatus = MESSAGE_QUEUE_CHANNEL_STATUS_FAILED;
        }

        return [
            'notification_status' => $notificationStatus,
            'email_status' => $emailStatus,
            'status' => MESSAGE_QUEUE_JOB_STATUS_FAILED,
            'available_at' => $failure['available_at'] ?? message_queue_now(),
            'reserved_at' => null,
            'processing_at' => null,
            'heartbeat_at' => null,
            'lease_until' => null,
            'worker_id' => null,
            'reservation_token' => null,
            'last_stage' => $channel . '_failed',
            'last_error_class' => $failure['error_class'] ?? MESSAGE_QUEUE_ERROR_CLASS_PERMANENT,
            'last_error_code' => $failure['error_code'] ?? null,
            'last_error_excerpt' => message_queue_error_excerpt($failure['message'] ?? null),
            'last_error_detail' => message_queue_error_detail_json($failure['detail'] ?? null),
            'finished_at' => message_queue_now(),
        ];
    });
}
