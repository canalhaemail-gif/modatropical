<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_auth();

$customer = current_customer();
$customerId = (int) ($customer['id'] ?? 0);

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('editar-contato.php');
    }

    $action = (string) posted_value('action');

    if ($action === 'update_phone') {
        $phone = digits_only((string) posted_value('telefone'));

        if ($phone === '' || strlen($phone) < 10) {
            set_flash('error', 'Informe um telefone valido com DDD.');
            redirect('editar-contato.php#editar-telefone');
        }

        db()->prepare(
            'UPDATE clientes
             SET telefone = :telefone
             WHERE id = :id'
        )->execute([
            'telefone' => $phone,
            'id' => $customerId,
        ]);

        set_flash('success', 'Telefone atualizado com sucesso.');
        redirect('editar-contato.php#editar-telefone');
    }

    if ($action === 'request_email_change_code') {
        $newEmail = normalize_email((string) posted_value('novo_email'));
        $newEmailConfirmation = normalize_email((string) posted_value('novo_email_confirmation'));

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Informe um novo email valido.');
            redirect('editar-contato.php#alterar-email');
        }

        if ($newEmail !== $newEmailConfirmation) {
            set_flash('error', 'A confirmacao do novo email nao confere.');
            redirect('editar-contato.php#alterar-email');
        }

        if ($newEmail === normalize_email((string) ($customer['email'] ?? ''))) {
            set_flash('error', 'Informe um email diferente do atual.');
            redirect('editar-contato.php#alterar-email');
        }

        if (customer_email_exists($newEmail, $customerId)) {
            set_flash('error', 'Ja existe uma conta cadastrada com este email.');
            redirect('editar-contato.php#alterar-email');
        }

        $result = request_customer_email_change_code($customer, $newEmail);

        if (!empty($result['delivered'])) {
            set_flash('success', 'Enviamos um codigo para o seu email atual. Digite esse codigo para autorizar a troca.');
        } elseif (!empty($result['logged'])) {
            $_SESSION['email_change_mail_log_path'] = (string) ($result['log_path'] ?? '');
            set_flash('success', 'No localhost, o email com o codigo foi salvo localmente para teste.');
        } else {
            set_flash('error', 'Nao foi possivel enviar o codigo agora. Tente novamente.');
        }

        redirect('editar-contato.php#alterar-email');
    }

    if ($action === 'resend_email_change_code') {
        $pendingChange = find_customer_email_change_request($customerId);

        if (!$pendingChange) {
            set_flash('error', 'Nao existe uma troca de email pendente para reenviar.');
            redirect('editar-contato.php#alterar-email');
        }

        $result = request_customer_email_change_code($customer, (string) $pendingChange['novo_email']);

        if (!empty($result['delivered'])) {
            set_flash('success', 'Enviamos um novo codigo para o seu email atual.');
        } elseif (!empty($result['logged'])) {
            $_SESSION['email_change_mail_log_path'] = (string) ($result['log_path'] ?? '');
            set_flash('success', 'No localhost, o novo codigo foi salvo localmente para teste.');
        } else {
            set_flash('error', 'Nao foi possivel reenviar o codigo agora. Tente novamente.');
        }

        redirect('editar-contato.php#alterar-email');
    }

    if ($action === 'confirm_email_change') {
        $pendingChange = find_customer_email_change_request($customerId);
        $code = trim((string) posted_value('code'));

        if (!$pendingChange) {
            set_flash('error', 'Nao existe uma troca de email pendente.');
            redirect('editar-contato.php#alterar-email');
        }

        if ($code === '' || preg_match('/^\d{6}$/', $code) !== 1) {
            set_flash('error', 'Informe o codigo de 6 digitos enviado para o seu email atual.');
            redirect('editar-contato.php#alterar-email');
        }

        $result = confirm_customer_email_change_with_code($customerId, $code);

        if (!$result['success']) {
            if (($result['reason'] ?? '') === 'email_taken') {
                set_flash('error', 'Esse novo email ja esta em uso por outra conta.');
            } else {
                set_flash('error', 'Codigo invalido ou expirado. Solicite um novo envio.');
            }

            redirect('editar-contato.php#alterar-email');
        }

        $newEmail = (string) ($result['new_email'] ?? '');
        $verification = $result['verification'] ?? [];
        $_SESSION['pending_verification_email'] = $newEmail;

        if (!empty($verification['logged'])) {
            $_SESSION['verification_mail_log_path'] = (string) ($verification['log_path'] ?? '');
        }

        logout_customer();

        if (!empty($verification['delivered'])) {
            set_flash('success', 'Email atualizado. Agora confirme o novo endereco pelo codigo ou link que enviamos.');
        } elseif (!empty($verification['logged'])) {
            set_flash('success', 'Email atualizado. No localhost, a confirmacao do novo email foi salva localmente para teste.');
        } else {
            set_flash('error', 'Email atualizado, mas nao foi possivel enviar a confirmacao agora. Solicite um novo envio.');
        }

        redirect('verificar-email.php?email=' . rawurlencode($newEmail));
    }
}

$customer = current_customer();
$pendingChange = find_customer_email_change_request($customerId);
$mailLogPath = (string) pull_session_value('email_change_mail_log_path', '');
$storeSettings = fetch_store_settings();
$pageTitle = 'Editar Contato';
$bodyClass = 'storefront-body public-body--customer';
$extraStylesheets = ['assets/css/public-auth.css'];
$customerAreaSection = 'contact';
$customerAreaTitle = 'Contato da conta';
$customerAreaDescription = 'Atualize telefone e email com um fluxo mais claro, mantendo a mesma identidade visual da vitrine.';

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <?php require BASE_PATH . '/includes/customer_area_topbar.php'; ?>

    <section class="account-layout">
        <article class="account-card">
            <div class="account-card__header">
                <span class="auth-form-card__badge">Contato</span>
                <h2>Edite seu telefone ou email</h2>
                <p>O telefone pode ser atualizado direto. Para trocar o email, primeiro confirmamos sua conta pelo email atual.</p>
            </div>

            <div class="account-edit-grid">
                <section class="signup-section" id="editar-telefone">
                    <div class="signup-section__header">
                        <span>1</span>
                        <div>
                            <strong>Editar telefone</strong>
                            <p>Atualize o numero para agilizar o contato da loja com voce.</p>
                        </div>
                    </div>

                    <form method="post" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="update_phone">

                        <div class="form-row">
                            <label for="telefone">Telefone</label>
                            <input id="telefone" name="telefone" type="text" required inputmode="numeric" value="<?= e((string) posted_value('telefone', format_phone($customer['telefone'] ?? ''))); ?>" placeholder="(11) 99999-8888" data-phone-mask>
                        </div>

                        <button class="btn btn--primary" type="submit">Salvar telefone</button>
                    </form>
                </section>

                <section class="signup-section" id="alterar-email">
                    <div class="signup-section__header">
                        <span>2</span>
                        <div>
                            <strong>Alterar email</strong>
                            <p>Antes de trocar o email da conta, enviamos um codigo para o seu email atual.</p>
                        </div>
                    </div>

                    <div class="status-card">
                        <h2>Email atual</h2>
                        <p><?= e((string) ($customer['email'] ?? '')); ?></p>
                    </div>

                    <form method="post" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="request_email_change_code">

                        <div class="form-row">
                            <label for="novo_email">Novo email</label>
                            <input id="novo_email" name="novo_email" type="email" required autocomplete="email" value="<?= e((string) posted_value('novo_email', $pendingChange['novo_email'] ?? '')); ?>">
                        </div>

                        <div class="form-row">
                            <label for="novo_email_confirmation">Confirmacao do novo email</label>
                            <input id="novo_email_confirmation" name="novo_email_confirmation" type="email" required autocomplete="email" value="<?= e((string) posted_value('novo_email_confirmation', $pendingChange['novo_email'] ?? '')); ?>">
                        </div>

                        <button class="btn btn--ghost" type="submit">Enviar codigo no email atual</button>
                    </form>

                    <?php if ($pendingChange): ?>
                        <div class="status-card account-status-card">
                            <h2>Troca pendente</h2>
                            <p>Novo email solicitado: <strong><?= e((string) $pendingChange['novo_email']); ?></strong></p>
                            <p>Digite abaixo o codigo recebido no email atual para continuar.</p>
                        </div>

                        <form method="post" class="admin-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                            <div class="form-row">
                                <label for="code">Codigo recebido no email atual</label>
                                <input id="code" name="code" type="text" required inputmode="numeric" maxlength="6" placeholder="000000">
                            </div>

                            <div class="account-inline-actions">
                                <button class="btn btn--primary" type="submit" name="action" value="confirm_email_change">Confirmar troca de email</button>
                            </div>
                        </form>

                        <form method="post" class="admin-form auth-form-card__secondary-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="resend_email_change_code">
                            <div class="account-inline-actions">
                                <button class="btn btn--ghost" type="submit">Reenviar codigo</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($mailLogPath !== '' && is_local_environment()): ?>
                        <div class="debug-reset-box">
                            <strong>Email de teste salvo localmente</strong>
                            <p>Arquivo: <?= e($mailLogPath); ?></p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="account-card__actions">
                <a class="btn btn--ghost" href="<?= e(app_url('minha-conta.php')); ?>">Voltar para minha conta</a>
                <a class="btn btn--primary" href="<?= e(app_url('index.php')); ?>">Voltar para a vitrine</a>
            </div>
        </article>

        <aside class="account-side-card">
            <span class="auth-panel__eyebrow">mais seguranca</span>
            <h2>Troca de email com dupla confirmacao.</h2>
            <p>Primeiro voce autoriza a alteracao no email atual. Depois, confirmamos o novo email para garantir que ele e seu mesmo.</p>
            <a class="btn btn--ghost" href="<?= e(app_url('minha-conta.php')); ?>">Ver meus dados</a>
        </aside>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
