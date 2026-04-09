<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_auth();

$customer = current_customer();
$customerId = (int) ($customer['id'] ?? 0);

function notifications_return_to(mixed $value): string
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return 'notificacoes.php';
    }

    $parts = parse_url($raw);

    if ($parts === false) {
        return 'notificacoes.php';
    }

    $path = trim((string) ($parts['path'] ?? ''), '/');
    $query = trim((string) ($parts['query'] ?? ''));
    $basePath = trim((string) parse_url(app_url(), PHP_URL_PATH), '/');

    if ($basePath !== '' && $path === $basePath) {
        $path = '';
    } elseif ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath) + 1);
    }

    if ($path === '' || str_starts_with($path, 'http')) {
        $path = 'notificacoes.php';
    }

    if (str_contains($path, '..')) {
        $path = 'notificacoes.php';
    }

    return $query !== '' ? $path . '?' . $query : $path;
}

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('notificacoes.php');
    }

    $returnTo = notifications_return_to(posted_value('return_to'));
    $action = (string) posted_value('action');

    if ($action === 'mark_all') {
        mark_all_customer_notifications_read($customerId);
        set_flash('success', 'Todas as notificacoes foram marcadas como lidas.');
        redirect($returnTo);
    }

    if ($action === 'delete_all') {
        delete_all_customer_notifications($customerId);
        set_flash('success', 'Todas as notificacoes foram apagadas.');
        redirect($returnTo);
    }

    if ($action === 'delete_one') {
        $notificationId = (int) posted_value('notification_id');

        if (!delete_customer_notification($notificationId, $customerId)) {
            set_flash('error', 'Notificacao nao encontrada.');
        } else {
            set_flash('success', 'Notificacao apagada.');
        }

        redirect($returnTo);
    }
}

if ((string) ($_GET['mark_all'] ?? '') === '1') {
    mark_all_customer_notifications_read($customerId);
    set_flash('success', 'Todas as notificacoes foram marcadas como lidas.');
    redirect('notificacoes.php');
}

$gotoId = isset($_GET['goto']) ? (int) $_GET['goto'] : 0;
$focusId = isset($_GET['focus']) ? (int) $_GET['focus'] : 0;

if ($gotoId > 0) {
    $notification = find_customer_notification($gotoId, $customerId);

    if (!$notification) {
        set_flash('error', 'Notificacao nao encontrada.');
        redirect('notificacoes.php');
    }

    mark_customer_notification_read($gotoId, $customerId);
    redirect(customer_notification_target_url($notification));
}

$notifications = fetch_customer_notifications($customerId, 100);
$storeSettings = fetch_store_settings();
$pageTitle = 'Notificacoes | ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME);
$bodyClass = 'storefront-body';
$showSplash = false;

extract(storefront_build_context(), EXTR_SKIP);

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/storefront_top.php';
?>

<div class="storefront">
    <section class="storefront-page-heading">
        <div class="storefront-page-heading__content">
            <span class="storefront-toolbar__eyebrow">minha conta</span>
            <h1>Notificacoes</h1>
            <p>Acompanhe novidades dos pedidos, cupons e avisos da loja.</p>
        </div>

        <div class="storefront-page-heading__actions">
            <a class="btn btn--light" href="<?= e(app_url('minha-conta.php')); ?>">Voltar para minha conta</a>
        </div>
    </section>

    <section class="catalog-section notifications-page">
        <div class="catalog-section__header">
            <div>
                <span class="catalog-section__eyebrow">central</span>
                <h2>Ultimas notificacoes</h2>
            </div>

            <?php if ($notifications): ?>
                <div class="notifications-page__toolbar">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="mark_all">
                        <input type="hidden" name="return_to" value="notificacoes.php">
                        <button class="btn btn--ghost" type="submit">Marcar todas como lidas</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete_all">
                        <input type="hidden" name="return_to" value="notificacoes.php">
                        <button class="btn btn--ghost btn--danger-soft" type="submit">Apagar todas</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$notifications): ?>
            <div class="empty-state">
                <strong>Voce ainda nao recebeu notificacoes.</strong>
                <p>Quando a loja atualizar um pedido ou liberar um cupom novo, esse aviso aparece aqui.</p>
            </div>
        <?php else: ?>
            <div class="notifications-page__list">
                <?php foreach ($notifications as $notification): ?>
                    <?php $notificationId = (int) ($notification['id'] ?? 0); ?>
                    <article
                        id="notification-<?= e((string) $notificationId); ?>"
                        class="notifications-page__item<?= empty($notification['lida_em']) ? ' is-unread' : ''; ?><?= $focusId === $notificationId ? ' is-focused' : ''; ?>"
                    >
                        <a class="notifications-page__item-open" href="<?= e(app_url('notificacoes.php?goto=' . $notificationId)); ?>">
                            <div class="notifications-page__item-top">
                                <span class="notifications-page__type"><?= e(customer_notification_type_label((string) ($notification['tipo'] ?? ''))); ?></span>
                                <span class="notifications-page__date"><?= e(format_datetime_br((string) ($notification['criado_em'] ?? ''))); ?></span>
                            </div>
                            <strong><?= e((string) ($notification['titulo'] ?? 'Notificacao')); ?></strong>
                            <p><?= e((string) ($notification['mensagem'] ?? '')); ?></p>
                        </a>
                        <div class="notifications-page__actions">
                            <a class="btn btn--ghost" href="<?= e(app_url('notificacoes.php?goto=' . $notificationId)); ?>">
                                Abrir notificacao
                            </a>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_one">
                                <input type="hidden" name="notification_id" value="<?= e((string) $notificationId); ?>">
                                <input type="hidden" name="return_to" value="notificacoes.php">
                                <button class="btn btn--ghost btn--danger-soft" type="submit">Apagar</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php require BASE_PATH . '/includes/storefront_footer.php'; ?>
</div>

<?php require BASE_PATH . '/includes/storefront_floating_cart.php'; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
