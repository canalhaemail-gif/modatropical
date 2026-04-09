#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este worker so pode rodar via CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/customer_message_queue_dispatch.php';
require_once __DIR__ . '/../includes/admin_message_send_log.php';

function message_worker_parse_args(array $argv): array
{
    $options = [
        'once' => false,
        'limit' => 1,
        'lease_seconds' => MESSAGE_QUEUE_DEFAULT_LEASE_SECONDS,
        'sleep_ms' => 2000,
        'worker_id' => gethostname() . ':' . getmypid(),
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--once') {
            $options['once'] = true;
            continue;
        }

        if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
            continue;
        }

        [$key, $value] = explode('=', substr($arg, 2), 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === 'limit') {
            $options['limit'] = max(1, min(10, (int) $value));
            continue;
        }

        if ($key === 'lease-seconds') {
            $options['lease_seconds'] = max(30, (int) $value);
            continue;
        }

        if ($key === 'sleep-ms') {
            $options['sleep_ms'] = max(250, (int) $value);
            continue;
        }

        if ($key === 'worker-id' && $value !== '') {
            $options['worker_id'] = $value;
        }
    }

    return $options;
}

function message_worker_log(string $level, string $message, array $context = []): void
{
    $line = '[' . date('c') . ']'
        . ' [' . strtoupper($level) . '] '
        . $message;

    if ($context !== []) {
        $line .= ' ' . message_queue_json_encode($context);
    }

    fwrite(STDOUT, $line . PHP_EOL);
}

function message_worker_sleep(int $milliseconds): void
{
    usleep(max(1, $milliseconds) * 1000);
}

function message_worker_create_smtp_session(array $options): ?array
{
    if (strtolower((string) MAIL_DRIVER) !== 'smtp') {
        return null;
    }

    return smtp_session_create([
        'persistent' => true,
        'label' => 'message_worker:' . (string) ($options['worker_id'] ?? getmypid()),
        'max_messages' => 30,
        'max_idle_seconds' => 90,
        'noop_after_seconds' => 15,
    ]);
}

function message_worker_close_smtp_session(&$smtpSession, string $reason): void
{
    if (!is_array($smtpSession)) {
        return;
    }

    smtp_session_close($smtpSession, $reason);
}

function message_worker_error_code_from_message(string $message): ?string
{
    if (preg_match('/\b([245]\d{2})\b/', $message, $matches) === 1) {
        return $matches[1];
    }

    return null;
}

function message_worker_classify_failure(Throwable $exception, string $channel, string $stage): array
{
    $message = trim($exception->getMessage());
    $errorCode = message_worker_error_code_from_message($message) ?? ($exception->getCode() !== 0 ? (string) $exception->getCode() : null);
    $normalized = strtolower($message);
    $transientPatterns = [
        'timeout',
        'timed out',
        'conexao',
        'connection',
        'network',
        'broken pipe',
        'temporar',
        'renderer_exec_failed',
        'renderer_output_missing',
        'browser',
        'chromium',
        '421',
        '450',
        '451',
        '452',
        '454',
    ];
    $permanentPatterns = [
        'email inval',
        'invalid email',
        'user unknown',
        'recipient address rejected',
        'mailbox unavailable',
        '550',
        '551',
        '552',
        '553',
        '554',
        '5.1.1',
        'snapshot',
        'payload imposs',
        'scene inval',
        'sem customer_id',
        'sem endereco',
        'sem snapshot',
    ];
    $errorClass = MESSAGE_QUEUE_ERROR_CLASS_TRANSIENT;

    foreach ($permanentPatterns as $pattern) {
        if ($pattern !== '' && str_contains($normalized, $pattern)) {
            $errorClass = MESSAGE_QUEUE_ERROR_CLASS_PERMANENT;
            break;
        }
    }

    if ($errorClass !== MESSAGE_QUEUE_ERROR_CLASS_PERMANENT) {
        foreach ($transientPatterns as $pattern) {
            if ($pattern !== '' && str_contains($normalized, $pattern)) {
                $errorClass = MESSAGE_QUEUE_ERROR_CLASS_TRANSIENT;
                break;
            }
        }
    }

    if (
        $errorClass !== MESSAGE_QUEUE_ERROR_CLASS_PERMANENT
        && $channel === 'notification'
        && ($stage === 'notification_dispatch' || $stage === 'notification_processing')
    ) {
        $errorClass = MESSAGE_QUEUE_ERROR_CLASS_TRANSIENT;
    }

    return [
        'error_class' => $errorClass,
        'error_code' => $errorCode,
        'message' => $message !== '' ? $message : ('Falha em ' . $channel . '/' . $stage),
        'detail' => [
            'exception_class' => get_class($exception),
            'channel' => $channel,
            'stage' => $stage,
        ],
    ];
}

function message_worker_handle_failure(array $job, string $workerId, string $channel, string $stage, Throwable $exception): void
{
    $classification = message_worker_classify_failure($exception, $channel, $stage);
    $attempts = max(0, (int) ($job['attempts'] ?? 0));
    $maxAttempts = max(1, (int) ($job['max_attempts'] ?? MESSAGE_QUEUE_DEFAULT_MAX_ATTEMPTS));
    $shouldRetry = $classification['error_class'] === MESSAGE_QUEUE_ERROR_CLASS_TRANSIENT
        && $attempts < $maxAttempts;

    if ($shouldRetry) {
        $updatedJob = message_queue_mark_job_retry(
            (int) $job['id'],
            $workerId,
            $channel,
            $classification
        );
    } else {
        $updatedJob = message_queue_mark_job_failed(
            (int) $job['id'],
            $workerId,
            $channel,
            $classification
        );
    }

    message_queue_log_event([
        'batch_id' => (int) ($job['batch_id'] ?? 0),
        'job_id' => (int) ($job['id'] ?? 0),
        'attempt_number' => $attempts,
        'channel' => $channel,
        'stage' => $stage,
        'status' => $shouldRetry ? 'retry' : 'failed',
        'worker_id' => $workerId,
        'error_class' => $classification['error_class'],
        'error_code' => $classification['error_code'],
        'summary' => $classification['message'],
        'details' => $classification['detail'],
    ]);

    message_worker_log($shouldRetry ? 'warning' : 'error', 'Falha no job.', [
        'job_id' => (int) ($job['id'] ?? 0),
        'batch_id' => (int) ($job['batch_id'] ?? 0),
        'channel' => $channel,
        'stage' => $stage,
        'status' => $shouldRetry ? 'retry' : 'failed',
        'attempts' => $attempts,
        'max_attempts' => $maxAttempts,
        'message' => $classification['message'],
        'job_status' => (string) ($updatedJob['status'] ?? ''),
    ]);

    $logJob = is_array($updatedJob) ? $updatedJob : $job;
    admin_message_send_log_refresh_batch_from_job(
        $logJob,
        $shouldRetry ? 'worker_retry' : 'worker_failed',
        $classification['message'],
        [
            'worker_id' => $workerId,
            'channel' => $channel,
            'stage' => $stage,
            'error_class' => $classification['error_class'],
            'error_code' => $classification['error_code'],
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
        ]
    );
}

function message_worker_process_job(array $job, array $options, &$smtpSession = null): void
{
    $jobId = (int) ($job['id'] ?? 0);
    $workerId = (string) $options['worker_id'];
    $leaseSeconds = (int) $options['lease_seconds'];

    if ($jobId <= 0) {
        return;
    }

    if (!message_queue_mark_job_processing($jobId, $workerId, $leaseSeconds)) {
        message_worker_log('warning', 'Nao foi possivel marcar job como processing.', [
            'job_id' => $jobId,
            'worker_id' => $workerId,
        ]);
        return;
    }

    $job = message_queue_fetch_job($jobId) ?? $job;
    $channels = ['notification', 'email'];

    foreach ($channels as $channel) {
        $job = message_queue_fetch_job($jobId) ?? $job;

        if (!message_queue_channel_can_run($job, $channel)) {
            continue;
        }

        message_queue_touch_job_lease($jobId, $workerId, $leaseSeconds);
        $channelJob = message_queue_mark_channel_processing($jobId, $workerId, $channel, $leaseSeconds);

        if (!is_array($channelJob)) {
            message_worker_log('warning', 'Nao foi possivel assumir o canal para processamento.', [
                'job_id' => $jobId,
                'batch_id' => (int) ($job['batch_id'] ?? 0),
                'channel' => $channel,
                'worker_id' => $workerId,
            ]);
            return;
        }

        $job = $channelJob;

        try {
            if ($channel === 'notification') {
                $result = customer_message_queue_dispatch_notification($job);
                $job = message_queue_mark_channel_sent($jobId, $workerId, 'notification', [
                    'sent_at' => $result['sent_at'] ?? message_queue_now(),
                ]) ?? $job;
                message_queue_log_event([
                    'batch_id' => (int) ($job['batch_id'] ?? 0),
                    'job_id' => $jobId,
                    'attempt_number' => (int) ($job['attempts'] ?? 0),
                    'channel' => 'notification',
                    'stage' => 'notification_sent',
                    'status' => 'success',
                    'worker_id' => $workerId,
                    'summary' => 'Notificacao interna criada com sucesso.',
                    'details' => $result,
                ]);
                continue;
            }

            $dispatchMeta = customer_message_queue_email_dispatch_metadata($job);
            message_queue_log_event([
                'batch_id' => (int) ($job['batch_id'] ?? 0),
                'job_id' => $jobId,
                'attempt_number' => (int) ($dispatchMeta['attempt_number'] ?? ($job['attempts'] ?? 0)),
                'channel' => 'email',
                'stage' => 'email_dispatch_started',
                'status' => 'info',
                'worker_id' => $workerId,
                'summary' => 'Tentativa de email iniciada.',
                'details' => [
                    'message_id' => $dispatchMeta['message_id'] ?? null,
                    'dispatch_key' => $dispatchMeta['dispatch_key'] ?? null,
                    'headers' => $dispatchMeta['headers'] ?? [],
                ],
            ]);

            $result = customer_message_queue_dispatch_email($job, $smtpSession);
            $mailResult = (array) ($result['mail_result'] ?? []);
            $deliveryMeta = (array) ($result['delivery'] ?? $dispatchMeta);

            if (empty($mailResult['success'])) {
                throw new RuntimeException((string) ($mailResult['error'] ?? 'Falha no envio de email.'));
            }

            $renderTrace = (array) ($result['render_trace'] ?? []);
            $job = message_queue_mark_channel_sent($jobId, $workerId, 'email', [
                'sent_at' => $result['sent_at'] ?? message_queue_now(),
                'render_path' => trim((string) ($renderTrace['final_path'] ?? '')) ?: null,
                'render_cache_key' => trim((string) ($renderTrace['render_cache_key'] ?? '')) ?: null,
            ]) ?? $job;
            message_queue_log_event([
                'batch_id' => (int) ($job['batch_id'] ?? 0),
                'job_id' => $jobId,
                'attempt_number' => (int) ($job['attempts'] ?? 0),
                'channel' => 'email',
                'stage' => 'email_sent',
                'status' => 'success',
                'worker_id' => $workerId,
                'summary' => !empty($mailResult['delivered'])
                    ? 'Email entregue ao driver SMTP.'
                    : 'Email processado com fallback/log.',
                'details' => [
                    'delivery' => $deliveryMeta,
                    'mail_result' => $mailResult,
                    'smtp' => is_array($mailResult['smtp'] ?? null) ? $mailResult['smtp'] : null,
                    'render_trace' => $renderTrace,
                ],
            ]);
        } catch (Throwable $exception) {
            message_worker_handle_failure($job, $workerId, $channel, $channel . '_dispatch', $exception);
            return;
        }
    }

    $job = message_queue_fetch_job($jobId) ?? $job;
    message_worker_log('info', 'Job processado.', [
        'job_id' => $jobId,
        'batch_id' => (int) ($job['batch_id'] ?? 0),
        'status' => (string) ($job['status'] ?? ''),
        'notification_status' => (string) ($job['notification_status'] ?? ''),
        'email_status' => (string) ($job['email_status'] ?? ''),
    ]);
    admin_message_send_log_refresh_batch_from_job($job, 'worker_processed', 'Job processado pelo worker.', [
        'worker_id' => $workerId,
        'job_status' => (string) ($job['status'] ?? ''),
        'notification_status' => (string) ($job['notification_status'] ?? ''),
        'email_status' => (string) ($job['email_status'] ?? ''),
    ]);
}

function message_worker_run(array $options): int
{
    if (!message_queue_tables_ready()) {
        fwrite(STDERR, "As tabelas da fila nao existem. Aplique database/update_message_queue.sql primeiro.\n");
        return 1;
    }

    $smtpSession = message_worker_create_smtp_session($options);
    message_worker_log('info', 'Worker iniciado.', $options);

    try {
        do {
            $jobs = message_queue_reserve_jobs(
                (string) $options['worker_id'],
                (int) $options['limit'],
                (int) $options['lease_seconds']
            );

            if ($jobs === []) {
                message_worker_close_smtp_session($smtpSession, 'worker_idle');

                if (!empty($options['once'])) {
                    message_worker_log('info', 'Nenhum job disponivel nesta execucao.');
                    return 0;
                }

                message_worker_sleep((int) $options['sleep_ms']);
                continue;
            }

            foreach ($jobs as $job) {
                try {
                    message_worker_process_job($job, $options, $smtpSession);
                } catch (Throwable $exception) {
                    message_worker_log('error', 'Erro inesperado no loop do worker.', [
                        'job_id' => (int) ($job['id'] ?? 0),
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            if (!empty($options['once'])) {
                return 0;
            }
        } while (true);
    } finally {
        message_worker_close_smtp_session($smtpSession, 'worker_shutdown');
    }
}

exit(message_worker_run(message_worker_parse_args($argv)));
