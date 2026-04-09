<?php
declare(strict_types=1);

function message_queue_create_batch(array $batchData, array $jobs): array
{
    if (!message_queue_tables_ready()) {
        throw new RuntimeException('As tabelas da fila de mensagens ainda nao existem no banco.');
    }

    $idempotencyKey = trim((string) ($batchData['idempotency_key'] ?? ''));
    if ($idempotencyKey === '') {
        throw new RuntimeException('idempotency_key e obrigatorio para criar o lote.');
    }

    $publicId = trim((string) ($batchData['public_id'] ?? ''));
    $publicId = $publicId !== '' ? $publicId : message_queue_build_batch_public_id();
    $queuedAt = trim((string) ($batchData['queued_at'] ?? '')) ?: message_queue_now();
    $payloadSnapshot = (array) ($batchData['payload_snapshot'] ?? []);
    $sceneSnapshot = $batchData['scene_snapshot'] ?? null;
    $editorLayersSnapshot = $batchData['editor_layers_snapshot'] ?? null;
    $smtpProfileSnapshot = (array) ($batchData['smtp_profile_snapshot'] ?? message_queue_current_smtp_profile_snapshot());
    $normalizedJobs = [];

    foreach ($jobs as $job) {
        if (!is_array($job)) {
            continue;
        }

        $customerSnapshot = (array) ($job['customer_snapshot'] ?? []);
        $recipientKey = trim((string) ($job['recipient_key'] ?? ''));
        $recipientKey = $recipientKey !== '' ? $recipientKey : message_queue_build_recipient_key($customerSnapshot);
        $sendNotification = array_key_exists('send_notification', $job)
            ? (bool) $job['send_notification']
            : (bool) ($batchData['send_notification'] ?? true);
        $sendEmail = array_key_exists('send_email', $job)
            ? (bool) $job['send_email']
            : (bool) ($batchData['send_email'] ?? true);

        if (!$sendNotification && !$sendEmail) {
            throw new RuntimeException('Cada job precisa ter pelo menos um canal habilitado.');
        }

        $normalizedJobs[] = [
            'public_id' => trim((string) ($job['public_id'] ?? '')) ?: message_queue_build_job_public_id(),
            'recipient_key' => $recipientKey,
            'dedupe_key' => trim((string) ($job['dedupe_key'] ?? ''))
                ?: message_queue_build_job_dedupe_key($publicId, $recipientKey),
            'customer_id' => isset($job['customer_id'])
                ? (int) $job['customer_id']
                : ((int) ($customerSnapshot['id'] ?? 0) ?: null),
            'customer_email' => trim((string) ($job['customer_email'] ?? ($customerSnapshot['email'] ?? ''))) ?: null,
            'send_notification' => $sendNotification,
            'send_email' => $sendEmail,
            'notification_status' => $sendNotification ? MESSAGE_QUEUE_CHANNEL_STATUS_PENDING : MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED,
            'email_status' => $sendEmail ? MESSAGE_QUEUE_CHANNEL_STATUS_PENDING : MESSAGE_QUEUE_CHANNEL_STATUS_SKIPPED,
            'status' => MESSAGE_QUEUE_JOB_STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => max(1, (int) ($job['max_attempts'] ?? $batchData['max_attempts'] ?? MESSAGE_QUEUE_DEFAULT_MAX_ATTEMPTS)),
            'available_at' => trim((string) ($job['available_at'] ?? '')) ?: $queuedAt,
            'subject_snapshot' => trim((string) ($job['subject_snapshot'] ?? '')),
            'customer_snapshot_json' => message_queue_json_encode($customerSnapshot),
            'token_snapshot_json' => message_queue_json_encode((array) ($job['token_snapshot'] ?? [])),
            'content_snapshot_json' => message_queue_json_encode((array) ($job['content_snapshot'] ?? [])),
            'scene_hash' => trim((string) ($job['scene_hash'] ?? $batchData['scene_hash'] ?? '')) ?: null,
            'scene_version' => trim((string) ($job['scene_version'] ?? $batchData['scene_version'] ?? '')) ?: null,
            'normalizer_version' => trim((string) ($job['normalizer_version'] ?? $batchData['normalizer_version'] ?? '')) ?: null,
        ];
    }

    if ($normalizedJobs === []) {
        throw new RuntimeException('Nenhum job valido foi informado para o lote.');
    }

    $insertBatch = db()->prepare(
        'INSERT INTO message_batches (
            public_id, status, idempotency_key, project_id, project_name, recipient_mode, message_kind,
            send_notification, send_email, smtp_profile_key, payload_hash, scene_hash, scene_version,
            normalizer_version, rate_limit_per_minute, total_jobs, pending_jobs, payload_snapshot_json,
            scene_snapshot_json, editor_layers_snapshot_json, smtp_profile_snapshot_json, created_by, queued_at
         ) VALUES (
            :public_id, :status, :idempotency_key, :project_id, :project_name, :recipient_mode, :message_kind,
            :send_notification, :send_email, :smtp_profile_key, :payload_hash, :scene_hash, :scene_version,
            :normalizer_version, :rate_limit_per_minute, :total_jobs, :pending_jobs, :payload_snapshot_json,
            :scene_snapshot_json, :editor_layers_snapshot_json, :smtp_profile_snapshot_json, :created_by, :queued_at
         )'
    );
    $insertJob = db()->prepare(
        'INSERT INTO message_batch_jobs (
            batch_id, public_id, recipient_key, dedupe_key, customer_id, customer_email,
            send_notification, send_email, notification_status, email_status, status, attempts, max_attempts,
            available_at, subject_snapshot, customer_snapshot_json, token_snapshot_json, content_snapshot_json,
            scene_hash, scene_version, normalizer_version
         ) VALUES (
            :batch_id, :public_id, :recipient_key, :dedupe_key, :customer_id, :customer_email,
            :send_notification, :send_email, :notification_status, :email_status, :status, :attempts, :max_attempts,
            :available_at, :subject_snapshot, :customer_snapshot_json, :token_snapshot_json, :content_snapshot_json,
            :scene_hash, :scene_version, :normalizer_version
         )'
    );

    db()->beginTransaction();

    try {
        $insertBatch->execute([
            'public_id' => $publicId,
            'status' => MESSAGE_QUEUE_BATCH_STATUS_QUEUED,
            'idempotency_key' => $idempotencyKey,
            'project_id' => trim((string) ($batchData['project_id'] ?? '')) ?: null,
            'project_name' => trim((string) ($batchData['project_name'] ?? '')) ?: null,
            'recipient_mode' => trim((string) ($batchData['recipient_mode'] ?? 'all')) ?: 'all',
            'message_kind' => trim((string) ($batchData['message_kind'] ?? 'manual')) ?: 'manual',
            'send_notification' => !empty($batchData['send_notification']) ? 1 : 0,
            'send_email' => !empty($batchData['send_email']) ? 1 : 0,
            'smtp_profile_key' => trim((string) ($batchData['smtp_profile_key'] ?? ''))
                ?: message_queue_hash_payload($smtpProfileSnapshot),
            'payload_hash' => trim((string) ($batchData['payload_hash'] ?? ''))
                ?: message_queue_hash_payload($payloadSnapshot),
            'scene_hash' => trim((string) ($batchData['scene_hash'] ?? '')) ?: null,
            'scene_version' => trim((string) ($batchData['scene_version'] ?? '')) ?: null,
            'normalizer_version' => trim((string) ($batchData['normalizer_version'] ?? '')) ?: null,
            'rate_limit_per_minute' => isset($batchData['rate_limit_per_minute'])
                ? max(1, (int) $batchData['rate_limit_per_minute'])
                : null,
            'total_jobs' => count($normalizedJobs),
            'pending_jobs' => count($normalizedJobs),
            'payload_snapshot_json' => message_queue_json_encode($payloadSnapshot),
            'scene_snapshot_json' => $sceneSnapshot !== null ? message_queue_json_encode($sceneSnapshot) : null,
            'editor_layers_snapshot_json' => $editorLayersSnapshot !== null
                ? message_queue_json_encode($editorLayersSnapshot)
                : null,
            'smtp_profile_snapshot_json' => message_queue_json_encode($smtpProfileSnapshot),
            'created_by' => isset($batchData['created_by']) ? (int) $batchData['created_by'] : null,
            'queued_at' => $queuedAt,
        ]);
        $batchId = (int) db()->lastInsertId();

        foreach ($normalizedJobs as $job) {
            $insertJob->execute([
                'batch_id' => $batchId,
                'public_id' => $job['public_id'],
                'recipient_key' => $job['recipient_key'],
                'dedupe_key' => $job['dedupe_key'],
                'customer_id' => $job['customer_id'],
                'customer_email' => $job['customer_email'],
                'send_notification' => $job['send_notification'] ? 1 : 0,
                'send_email' => $job['send_email'] ? 1 : 0,
                'notification_status' => $job['notification_status'],
                'email_status' => $job['email_status'],
                'status' => $job['status'],
                'attempts' => $job['attempts'],
                'max_attempts' => $job['max_attempts'],
                'available_at' => $job['available_at'],
                'subject_snapshot' => $job['subject_snapshot'] !== '' ? $job['subject_snapshot'] : 'Mensagem',
                'customer_snapshot_json' => $job['customer_snapshot_json'],
                'token_snapshot_json' => $job['token_snapshot_json'],
                'content_snapshot_json' => $job['content_snapshot_json'],
                'scene_hash' => $job['scene_hash'],
                'scene_version' => $job['scene_version'],
                'normalizer_version' => $job['normalizer_version'],
            ]);
        }

        db()->commit();
    } catch (PDOException $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($sqlState === '23000' && $driverCode === 1062) {
            $existing = message_queue_fetch_batch_by_idempotency_key($idempotencyKey);

            if ($existing !== null) {
                return [
                    'created' => false,
                    'duplicate' => true,
                    'batch' => $existing,
                ];
            }

            throw new RuntimeException('Violacao de unicidade ao criar lote ou jobs da fila.', 0, $exception);
        }

        if ($sqlState === '23000') {
            throw new RuntimeException(
                'Falha de integridade ao criar lote ou jobs da fila: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        throw $exception;
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $exception;
    }

    message_queue_log_event([
        'batch_id' => $batchId,
        'stage' => 'batch_created',
        'status' => 'success',
        'summary' => 'Lote criado e jobs persistidos com sucesso.',
        'details' => [
            'jobs_count' => count($normalizedJobs),
            'project_id' => trim((string) ($batchData['project_id'] ?? '')),
            'project_name' => trim((string) ($batchData['project_name'] ?? '')),
        ],
    ]);

    return [
        'created' => true,
        'duplicate' => false,
        'batch' => message_queue_fetch_batch($batchId),
    ];
}
