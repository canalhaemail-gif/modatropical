<?php
declare(strict_types=1);

function fetch_customer_notifications(int $customerId, int $limit = 8): array
{
    if ($customerId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $statement = db()->prepare(
        'SELECT *
         FROM cliente_notificacoes
         WHERE cliente_id = :cliente_id
         ORDER BY lida_em IS NULL DESC, criado_em DESC, id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['cliente_id' => $customerId]);

    return $statement->fetchAll();
}

function count_customer_unread_notifications(int $customerId): int
{
    if ($customerId <= 0) {
        return 0;
    }

    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM cliente_notificacoes
         WHERE cliente_id = :cliente_id
           AND lida_em IS NULL'
    );
    $statement->execute(['cliente_id' => $customerId]);

    return (int) $statement->fetchColumn();
}

function customer_notifications_snapshot(int $customerId, int $limit = 8): array
{
    return [
        'notifications' => fetch_customer_notifications($customerId, $limit),
        'unread_count' => count_customer_unread_notifications($customerId),
    ];
}

function render_customer_notifications_widget(
    array $notifications,
    int $unreadCount = 0,
    ?string $returnTo = null
): string {
    $returnTo = trim((string) $returnTo);
    $returnTo = $returnTo !== '' ? $returnTo : 'notificacoes.php';

    ob_start();
    ?>
    <?php if (!$notifications): ?>
        <div class="storefront-notification-menu__empty">
            <strong>Nada por aqui.</strong>
            <p>Pedidos e cupons vao aparecer aqui quando houver novidades.</p>
        </div>
    <?php else: ?>
        <div class="storefront-notification-menu__list">
            <?php foreach ($notifications as $notification): ?>
                <?php $notificationOpenUrl = app_url('notificacoes.php?goto=' . (int) ($notification['id'] ?? 0)); ?>
                <article class="storefront-notification-item<?= empty($notification['lida_em']) ? ' is-unread' : ''; ?>">
                    <a
                        class="storefront-notification-item__open"
                        href="<?= e($notificationOpenUrl); ?>"
                    >
                        <span class="storefront-notification-item__type"><?= e(customer_notification_type_label((string) ($notification['tipo'] ?? ''))); ?></span>
                        <strong><?= e((string) ($notification['titulo'] ?? 'Notificacao')); ?></strong>
                        <small><?= e((string) ($notification['mensagem'] ?? '')); ?></small>
                    </a>

                    <div class="storefront-notification-item__actions">
                        <a class="storefront-notification-item__view" href="<?= e($notificationOpenUrl); ?>">
                            Abrir
                        </a>

                        <form class="storefront-notification-item__delete-form" method="post" action="<?= e(app_url('notificacoes.php')); ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete_one">
                            <input type="hidden" name="notification_id" value="<?= e((string) ((int) ($notification['id'] ?? 0))); ?>">
                            <input type="hidden" name="return_to" value="<?= e($returnTo); ?>">
                            <button class="storefront-notification-item__delete" type="submit" aria-label="Apagar notificacao">Apagar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="storefront-notification-menu__footer">
            <?php if ($unreadCount > 0): ?>
                <form method="post" action="<?= e(app_url('notificacoes.php')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="mark_all">
                    <input type="hidden" name="return_to" value="<?= e($returnTo); ?>">
                    <button class="storefront-notification-menu__mark-all" type="submit">Marcar todas como lidas</button>
                </form>
            <?php endif; ?>

            <form method="post" action="<?= e(app_url('notificacoes.php')); ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="delete_all">
                <input type="hidden" name="return_to" value="<?= e($returnTo); ?>">
                <button class="storefront-notification-menu__clear-all" type="submit">Apagar todas</button>
            </form>

            <a class="storefront-notification-menu__view-all" href="<?= e(app_url('notificacoes.php')); ?>">
                Ver todas
            </a>
        </div>
    <?php endif; ?>
    <?php

    return trim((string) ob_get_clean());
}

function create_customer_notification(
    int $customerId,
    string $type,
    string $title,
    string $message,
    ?string $linkUrl = null,
    ?array $payload = null
): void {
    if ($customerId <= 0) {
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO cliente_notificacoes (
            cliente_id, tipo, titulo, mensagem, link_url, payload_json
         ) VALUES (
            :cliente_id, :tipo, :titulo, :mensagem, :link_url, :payload_json
         )'
    );
    $statement->execute([
        'cliente_id' => $customerId,
        'tipo' => trim($type) !== '' ? trim($type) : 'geral',
        'titulo' => trim($title) !== '' ? trim($title) : 'Notificacao',
        'mensagem' => trim($message),
        'link_url' => $linkUrl !== null && trim($linkUrl) !== '' ? trim($linkUrl) : null,
        'payload_json' => $payload !== null
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null,
    ]);
}

function find_customer_notification_by_queue_job(int $customerId, int $jobId, ?int $batchId = null): ?array
{
    if ($customerId <= 0 || $jobId <= 0) {
        return null;
    }

    $sql = '
        SELECT *
        FROM cliente_notificacoes
        WHERE cliente_id = :cliente_id
          AND payload_json IS NOT NULL
          AND payload_json LIKE :job_marker
    ';
    $params = [
        'cliente_id' => $customerId,
        'job_marker' => '%"job_id":' . $jobId . '%',
    ];

    if ($batchId !== null && $batchId > 0) {
        $sql .= ' AND payload_json LIKE :batch_marker';
        $params['batch_marker'] = '%"batch_id":' . $batchId . '%';
    }

    $sql .= ' ORDER BY id DESC LIMIT 1';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $notification = $statement->fetch();

    return is_array($notification) ? $notification : null;
}

function create_bulk_customer_notification(
    string $type,
    string $title,
    string $message,
    ?string $linkUrl = null,
    ?array $payload = null
): int {
    $statement = db()->prepare(
        'INSERT INTO cliente_notificacoes (
            cliente_id, tipo, titulo, mensagem, link_url, payload_json
         )
         SELECT id, :tipo, :titulo, :mensagem, :link_url, :payload_json
         FROM clientes
         WHERE ativo = 1'
    );
    $statement->execute([
        'tipo' => trim($type) !== '' ? trim($type) : 'geral',
        'titulo' => trim($title) !== '' ? trim($title) : 'Notificacao',
        'mensagem' => trim($message),
        'link_url' => $linkUrl !== null && trim($linkUrl) !== '' ? trim($linkUrl) : null,
        'payload_json' => $payload !== null
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null,
    ]);

    return $statement->rowCount();
}

function find_customer_notification(int $notificationId, int $customerId): ?array
{
    if ($notificationId <= 0 || $customerId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM cliente_notificacoes
         WHERE id = :id
           AND cliente_id = :cliente_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $notificationId,
        'cliente_id' => $customerId,
    ]);
    $notification = $statement->fetch();

    return $notification ?: null;
}

function mark_customer_notification_read(int $notificationId, int $customerId): void
{
    if ($notificationId <= 0 || $customerId <= 0) {
        return;
    }

    $statement = db()->prepare(
        'UPDATE cliente_notificacoes
         SET lida_em = COALESCE(lida_em, NOW())
         WHERE id = :id
           AND cliente_id = :cliente_id'
    );
    $statement->execute([
        'id' => $notificationId,
        'cliente_id' => $customerId,
    ]);
}

function mark_all_customer_notifications_read(int $customerId): void
{
    if ($customerId <= 0) {
        return;
    }

    $statement = db()->prepare(
        'UPDATE cliente_notificacoes
         SET lida_em = COALESCE(lida_em, NOW())
         WHERE cliente_id = :cliente_id'
    );
    $statement->execute(['cliente_id' => $customerId]);
}

function delete_customer_notification(int $notificationId, int $customerId): bool
{
    if ($notificationId <= 0 || $customerId <= 0) {
        return false;
    }

    $statement = db()->prepare(
        'DELETE FROM cliente_notificacoes
         WHERE id = :id
           AND cliente_id = :cliente_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $notificationId,
        'cliente_id' => $customerId,
    ]);

    return $statement->rowCount() > 0;
}

function delete_all_customer_notifications(int $customerId): int
{
    if ($customerId <= 0) {
        return 0;
    }

    $statement = db()->prepare(
        'DELETE FROM cliente_notificacoes
         WHERE cliente_id = :cliente_id'
    );
    $statement->execute(['cliente_id' => $customerId]);

    return $statement->rowCount();
}

function customer_notification_target_url(array $notification): string
{
    $payload = json_decode((string) ($notification['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];
    $type = trim((string) ($notification['tipo'] ?? ''));
    $notificationId = (int) ($notification['id'] ?? 0);

    if ($type === 'cupom') {
        $couponId = (int) ($payload['cupom_id'] ?? 0);

        if ($couponId > 0) {
            return 'meus-cupons.php?cupom=' . $couponId;
        }
    }

    $linkUrl = trim((string) ($notification['link_url'] ?? ''));

    if ($linkUrl === '') {
        return $notificationId > 0
            ? 'notificacoes.php?focus=' . $notificationId
            : 'notificacoes.php';
    }

    if (preg_match('/^https?:\/\//i', $linkUrl) === 1) {
        return $linkUrl;
    }

    if (
        str_starts_with($linkUrl, '/')
        || $linkUrl === APP_URL
        || str_starts_with($linkUrl, rtrim(APP_URL, '/') . '/')
    ) {
        return $linkUrl;
    }

    return ltrim($linkUrl, '/');
}

function customer_notification_type_label(?string $value): string
{
    return match (trim((string) $value)) {
        'pedido' => 'Pedido',
        'cupom' => 'Cupom',
        'mensagem' => 'Mensagem',
        'boas_vindas' => 'Boas-vindas',
        'estoque' => 'Estoque',
        default => 'Aviso',
    };
}
