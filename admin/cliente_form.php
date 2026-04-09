<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

$customerId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $customerId !== null && $customerId > 0;
$customer = $editing ? find_customer($customerId) : null;

if ($editing && !$customer) {
    set_flash('error', 'Cliente nao encontrado.');
    redirect('admin/clientes.php');
}

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Token invalido.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    $name = normalize_person_name((string) posted_value('nome'));
    $email = normalize_email((string) posted_value('email'));
    $phone = digits_only((string) posted_value('telefone'));
    $cpf = normalize_cpf((string) posted_value('cpf'));
    $birthDate = trim((string) posted_value('data_nascimento'));
    $cep = normalize_cep((string) posted_value('cep'));
    $address = trim((string) posted_value('endereco'));
    $active = posted_value('ativo') ? 1 : 0;
    $password = (string) posted_value('password');
    $passwordConfirmation = (string) posted_value('password_confirmation');

    if (!is_valid_customer_name($name)) {
        set_flash('error', 'Informe o nome completo corretamente.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Informe um email valido.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    if ($phone === '' || strlen($phone) < 10) {
        set_flash('error', 'Informe um telefone valido com DDD.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    if (!is_valid_cpf($cpf)) {
        set_flash('error', 'Informe um CPF valido.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    if (!is_valid_birth_date($birthDate)) {
        set_flash('error', 'Informe uma data de nascimento valida.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    if (!is_valid_cep($cep)) {
        set_flash('error', 'Informe um CEP valido, com ou sem traco.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    if (strlen($address) < 8) {
        set_flash('error', 'Informe um endereco valido.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    if (customer_email_exists($email, $editing ? $customerId : null)) {
        set_flash('error', 'Ja existe uma conta cadastrada com este email.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    if (customer_cpf_exists($cpf, $editing ? $customerId : null)) {
        set_flash('error', 'Ja existe uma conta cadastrada com este CPF.');
        redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
    }

    $passwordHash = $customer['senha_hash'] ?? null;

    if (!$editing && $password === '') {
        set_flash('error', 'Informe uma senha para a nova conta.');
        redirect('admin/cliente_form.php');
    }

    if ($password !== '') {
        if (!is_strong_password($password)) {
            set_flash('error', password_rule_message());
            redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
        }

        if ($password !== $passwordConfirmation) {
            set_flash('error', 'A confirmacao da senha nao confere.');
            redirect($editing ? 'admin/cliente_form.php?id=' . $customerId : 'admin/cliente_form.php');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($editing) {
        $statement = db()->prepare(
            'UPDATE clientes
             SET nome = :nome,
                 email = :email,
                 telefone = :telefone,
                 cep = :cep,
                 endereco = :endereco,
                 cpf = :cpf,
                 data_nascimento = :data_nascimento,
                 senha_hash = :senha_hash,
                 ativo = :ativo
             WHERE id = :id'
        );

        $statement->execute([
            'nome' => $name,
            'email' => $email,
            'telefone' => $phone,
            'cep' => $cep,
            'endereco' => $address,
            'cpf' => $cpf,
            'data_nascimento' => $birthDate,
            'senha_hash' => $passwordHash,
            'ativo' => $active,
            'id' => $customerId,
        ]);

        set_flash('success', 'Cliente atualizado com sucesso.');
        redirect('admin/clientes.php');
    }

    $statement = db()->prepare(
        'INSERT INTO clientes (
            nome, email, telefone, cep, endereco, cpf, data_nascimento, senha_hash, email_verificado_em, ativo
         ) VALUES (
            :nome, :email, :telefone, :cep, :endereco, :cpf, :data_nascimento, :senha_hash, NOW(), :ativo
         )'
    );

    $statement->execute([
        'nome' => $name,
        'email' => $email,
        'telefone' => $phone,
        'cep' => $cep,
        'endereco' => $address,
        'cpf' => $cpf,
        'data_nascimento' => $birthDate,
        'senha_hash' => $passwordHash,
        'ativo' => $active,
    ]);

    set_flash('success', 'Cliente criado com sucesso.');
    redirect('admin/clientes.php');
}

$currentAdminPage = 'clientes';
$pageTitle = $editing ? 'Editar Cliente' : 'Novo Cliente';

$formData = [
    'nome' => (string) posted_value('nome', $customer['nome'] ?? ''),
    'email' => (string) posted_value('email', $customer['email'] ?? ''),
    'telefone' => (string) posted_value('telefone', $customer['telefone'] ?? ''),
    'cpf' => (string) posted_value('cpf', format_cpf($customer['cpf'] ?? '')),
    'data_nascimento' => (string) posted_value('data_nascimento', $customer['data_nascimento'] ?? ''),
    'cep' => (string) posted_value('cep', format_cep($customer['cep'] ?? '')),
    'endereco' => (string) posted_value('endereco', $customer['endereco'] ?? ''),
    'ativo' => (string) posted_value('ativo', (string) ($customer['ativo'] ?? '1')),
];

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--form">
    <div class="panel-card__header">
        <div>
            <p class="panel-card__eyebrow">clientes</p>
            <h2><?= e($pageTitle); ?></h2>
        </div>
        <a class="button button--ghost" href="<?= e(app_url('admin/clientes.php')); ?>">Voltar</a>
    </div>

    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

        <div class="form-grid">
            <div class="form-row form-row--wide">
                <label for="nome">Nome completo</label>
                <input id="nome" name="nome" type="text" required value="<?= e($formData['nome']); ?>">
            </div>

            <div class="form-row">
                <label for="cpf">CPF</label>
                <input id="cpf" name="cpf" type="text" required value="<?= e($formData['cpf']); ?>" placeholder="000.000.000-00">
            </div>

            <div class="form-row">
                <label for="data_nascimento">Data de nascimento</label>
                <input id="data_nascimento" name="data_nascimento" type="date" required value="<?= e($formData['data_nascimento']); ?>">
            </div>

            <div class="form-row">
                <label for="telefone">Telefone</label>
                <input id="telefone" name="telefone" type="text" required value="<?= e($formData['telefone']); ?>" placeholder="(11) 99999-8888">
            </div>

            <div class="form-row">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required value="<?= e($formData['email']); ?>">
            </div>

            <div class="form-row">
                <label for="cep">CEP</label>
                <input id="cep" name="cep" type="text" required inputmode="tel" maxlength="9" value="<?= e($formData['cep']); ?>" placeholder="00000-000">
            </div>

            <div class="form-row form-row--wide">
                <label for="endereco">Endereco completo</label>
                <textarea id="endereco" name="endereco" rows="5" required><?= e($formData['endereco']); ?></textarea>
            </div>

            <div class="form-row">
                <label for="password">Nova senha<?= $editing ? ' (opcional)' : ''; ?></label>
                <input id="password" name="password" type="password" <?= $editing ? '' : 'required'; ?>>
                <small class="table-subtitle"><?= e(password_rule_message()); ?></small>
            </div>

            <div class="form-row">
                <label for="password_confirmation">Confirmar senha</label>
                <input id="password_confirmation" name="password_confirmation" type="password" <?= $editing ? '' : 'required'; ?>>
            </div>

            <div class="form-row form-row--toggle">
                <label class="checkbox-row">
                    <input name="ativo" type="checkbox" value="1" <?= checked($formData['ativo'], 1); ?>>
                    <span>Conta ativa</span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button class="button button--primary" type="submit"><?= $editing ? 'Salvar alteracoes' : 'Criar cliente'; ?></button>
        </div>
    </form>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
