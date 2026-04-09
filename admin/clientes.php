<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

if (is_post() && posted_value('action') === 'delete') {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido para exclusao.');
        redirect('admin/clientes.php');
    }

    $customerId = (int) posted_value('id');
    $customer = find_customer($customerId);

    if (!$customer) {
        set_flash('error', 'Cliente nao encontrado.');
        redirect('admin/clientes.php');
    }

    $statement = db()->prepare('DELETE FROM clientes WHERE id = :id');
    $statement->execute(['id' => $customerId]);

    set_flash('success', 'Cliente removido com sucesso.');
    redirect('admin/clientes.php');
}

$currentAdminPage = 'clientes';
$pageTitle = 'Clientes';

$customers = db()->query(
    'SELECT id, nome, email, telefone, cpf, ativo, criado_em
     FROM clientes
     ORDER BY criado_em DESC, nome ASC'
)->fetchAll();

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--customers">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">clientes</p>
            <h2>Contas cadastradas</h2>
        </div>
        <a class="button button--primary" href="<?= e(app_url('admin/cliente_form.php')); ?>">Novo cliente</a>
    </div>

    <div class="customer-list-mobile">
        <?php if (!$customers): ?>
            <div class="empty-state empty-state--admin">Nenhum cliente cadastrado.</div>
        <?php endif; ?>

        <?php foreach ($customers as $customer): ?>
            <details class="customer-item">
                <summary class="customer-item__summary">
                    <strong><?= e($customer['nome']); ?></strong>
                    <span class="customer-item__toggle">Exibir tudo</span>
                </summary>

                <div class="customer-item__body">
                    <div class="customer-item__row">
                        <span>CPF</span>
                        <strong><?= e(format_cpf($customer['cpf'] ?? '')); ?></strong>
                    </div>
                    <div class="customer-item__row">
                        <span>Email</span>
                        <strong><?= e($customer['email']); ?></strong>
                    </div>
                    <div class="customer-item__row">
                        <span>Telefone</span>
                        <strong><?= e($customer['telefone'] ?? ''); ?></strong>
                    </div>
                    <div class="customer-item__row">
                        <span>Status</span>
                        <span class="status-pill <?= (int) $customer['ativo'] === 1 ? 'status-pill--success' : 'status-pill--neutral'; ?>">
                            <?= (int) $customer['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </div>
                    <div class="customer-item__row">
                        <span>Cadastro</span>
                        <strong><?= e(date('d/m/Y', strtotime((string) $customer['criado_em']))); ?></strong>
                    </div>
                    <div class="customer-item__actions">
                        <a class="button button--ghost button--small" href="<?= e(app_url('admin/cliente_form.php?id=' . $customer['id'])); ?>">Editar</a>
                        <form method="post" onsubmit="return confirm('Excluir esta conta de cliente?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e((string) $customer['id']); ?>">
                            <button class="button button--danger button--small" type="submit">Excluir</button>
                        </form>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Email</th>
                    <th>Telefone</th>
                    <th>Status</th>
                    <th>Cadastro</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$customers): ?>
                    <tr>
                        <td colspan="7" class="table-empty">Nenhum cliente cadastrado.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?= e($customer['nome']); ?></td>
                        <td><?= e(format_cpf($customer['cpf'] ?? '')); ?></td>
                        <td><?= e($customer['email']); ?></td>
                        <td><?= e($customer['telefone'] ?? ''); ?></td>
                        <td>
                            <span class="status-pill <?= (int) $customer['ativo'] === 1 ? 'status-pill--success' : 'status-pill--neutral'; ?>">
                                <?= (int) $customer['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td><?= e(date('d/m/Y', strtotime((string) $customer['criado_em']))); ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="button button--ghost button--small" href="<?= e(app_url('admin/cliente_form.php?id=' . $customer['id'])); ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('Excluir esta conta de cliente?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $customer['id']); ?>">
                                    <button class="button button--danger button--small" type="submit">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
