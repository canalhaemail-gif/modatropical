<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_auth();

$customer = current_customer();
$customerId = (int) ($customer['id'] ?? 0);
$socialProvider = social_auth_pull_last_provider();
$needsEmailCompletion = customer_email_requires_completion((string) ($customer['email'] ?? ''));

if ($customerId <= 0) {
    logout_customer();
    set_flash('error', 'Sua sessao expirou. Entre novamente.');
    redirect('entrar.php');
}

if (customer_profile_is_complete($customer)) {
    redirect('');
}

$primaryAddress = fetch_customer_primary_address($customerId);

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('completar-cadastro.php');
    }

    $name = trim((string) posted_value('nome'));
    $email = normalize_email((string) posted_value('email', $customer['email'] ?? ''));
    $phone = digits_only((string) posted_value('telefone'));
    $cpf = normalize_cpf((string) posted_value('cpf'));
    $birthDate = trim((string) posted_value('data_nascimento'));
    $cep = normalize_cep((string) posted_value('cep'));
    $street = trim((string) posted_value('rua'));
    $district = trim((string) posted_value('bairro'));
    $number = trim((string) posted_value('numero'));
    $complement = trim((string) posted_value('complemento'));
    $city = trim((string) posted_value('cidade'));
    $state = strtoupper(trim((string) posted_value('uf')));
    $address = build_customer_address_string($street, $district, $number, $complement, $city, $state);

    if ($name === '' || $phone === '' || $cpf === '' || $birthDate === '' || $cep === '' || $street === '' || $district === '' || $number === '' || $city === '' || $state === '' || ($needsEmailCompletion && $email === '')) {
        set_flash('error', 'Preencha todos os campos obrigatorios para concluir seu cadastro.');
        redirect('completar-cadastro.php');
    }

    if ($needsEmailCompletion) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Informe um email valido para concluir seu cadastro.');
            redirect('completar-cadastro.php');
        }

        if (customer_email_exists($email, $customerId)) {
            set_flash('error', 'Ja existe outra conta cadastrada com este email.');
            redirect('completar-cadastro.php');
        }
    }

    if (!is_valid_customer_name($name)) {
        set_flash('error', 'Informe seu nome completo corretamente.');
        redirect('completar-cadastro.php');
    }

    if (strlen($phone) < 10) {
        set_flash('error', 'Informe um telefone valido com DDD.');
        redirect('completar-cadastro.php');
    }

    if (!is_valid_cpf($cpf)) {
        set_flash('error', 'Informe um CPF valido.');
        redirect('completar-cadastro.php');
    }

    if (customer_cpf_exists($cpf, $customerId)) {
        set_flash('error', 'Ja existe outra conta cadastrada com este CPF.');
        redirect('completar-cadastro.php');
    }

    if (!is_valid_birth_date($birthDate)) {
        set_flash('error', 'Informe uma data de nascimento valida.');
        redirect('completar-cadastro.php');
    }

    if (!is_valid_cep($cep)) {
        set_flash('error', 'Informe um CEP valido com 8 digitos.');
        redirect('completar-cadastro.php');
    }

    if (strlen($street) < 3 || strlen($district) < 2 || strlen($city) < 2 || strlen($number) < 1 || preg_match('/^[A-Z]{2}$/', $state) !== 1) {
        set_flash('error', 'Informe um endereco valido.');
        redirect('completar-cadastro.php');
    }

    db()->prepare(
        'UPDATE clientes
         SET nome = :nome,
             email = :email,
             telefone = :telefone,
             cpf = :cpf,
             data_nascimento = :data_nascimento,
             cep = :cep,
             endereco = :endereco
         WHERE id = :id'
    )->execute([
        'nome' => normalize_person_name($name),
        'email' => $needsEmailCompletion ? $email : (string) ($customer['email'] ?? ''),
        'telefone' => $phone,
        'cpf' => $cpf,
        'data_nascimento' => $birthDate,
        'cep' => $cep,
        'endereco' => $address,
        'id' => $customerId,
    ]);

    save_customer_address($customerId, [
        'apelido' => $primaryAddress['apelido'] ?? 'Principal',
        'cep' => $cep,
        'rua' => $street,
        'bairro' => $district,
        'numero' => $number,
        'complemento' => $complement,
        'cidade' => $city,
        'uf' => $state,
        'principal' => 1,
    ], $primaryAddress ? (int) $primaryAddress['id'] : null);

    set_flash('success', 'Cadastro concluido com sucesso.');
    redirect('');
}

$storeSettings = fetch_store_settings();
$pageTitle = 'Completar Cadastro';
$bodyClass = 'public-body--auth';
$extraStylesheets = ['assets/css/public-auth.css'];
$socialProvider = $socialProvider !== '' ? $socialProvider : detect_primary_social_provider($customerId);
$socialProviderLabel = $socialProvider !== '' ? social_provider_label($socialProvider) : 'Conta social';
$connectedLabel = $needsEmailCompletion ? $socialProviderLabel . ' conectado' : 'Email conectado';
$leadCopy = $needsEmailCompletion
    ? 'Seu ' . $socialProviderLabel . ' foi conectado. Agora informe seu email, telefone, CPF, nascimento e endereco para liberar pedidos e entrega em ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME) . '.'
    : 'Seu email do ' . $socialProviderLabel . ' ja foi confirmado. Agora complete telefone, CPF, nascimento e endereco para liberar pedidos e entrega em ' . ($storeSettings['nome_estabelecimento'] ?? APP_NAME) . '.';

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <section class="auth-layout auth-layout--signup">
        <article class="auth-form-card auth-form-card--signup">
            <div class="auth-form-card__header">
                <span class="auth-form-card__badge"><?= e($socialProviderLabel); ?></span>
                <h2>Complete seu cadastro</h2>
            </div>

            <p class="auth-form-card__lead"><?= e($leadCopy); ?></p>

            <div class="status-card">
                <h2><?= e($connectedLabel); ?></h2>
                <p><?= e($needsEmailCompletion ? 'Finalize o email da sua conta para continuar.' : (string) ($customer['email'] ?? '')); ?></p>
            </div>

            <form method="post" class="admin-form signup-form" data-signup-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                <section class="signup-section">
                    <div class="signup-section__header">
                        <span>1</span>
                        <div>
                            <strong>Dados principais</strong>
                            <p>Esses dados sao usados para identificar sua conta e concluir pagamentos.</p>
                        </div>
                    </div>

                    <div class="signup-grid signup-grid--double">
                        <div class="form-row signup-grid__wide">
                            <label for="nome">Nome completo</label>
                            <input id="nome" name="nome" type="text" required value="<?= e((string) posted_value('nome', $customer['nome'] ?? '')); ?>">
                        </div>

                        <?php if ($needsEmailCompletion): ?>
                            <div class="form-row signup-grid__wide">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" required autocomplete="email" placeholder="nome@dominio.com" value="<?= e((string) posted_value('email')); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="form-row signup-grid__wide">
                            <label for="cpf">CPF</label>
                            <input id="cpf" name="cpf" type="text" required inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= e((string) posted_value('cpf', format_cpf($customer['cpf'] ?? ''))); ?>">
                        </div>

                        <div class="form-row signup-grid__wide">
                            <label for="data_nascimento">Data de nascimento</label>
                            <input id="data_nascimento" name="data_nascimento" type="date" required value="<?= e((string) posted_value('data_nascimento', $customer['data_nascimento'] ?? '')); ?>">
                        </div>

                        <div class="form-row signup-grid__wide">
                            <label for="telefone">Telefone</label>
                            <input id="telefone" name="telefone" type="text" required inputmode="numeric" placeholder="(11) 99999-8888" value="<?= e((string) posted_value('telefone', format_phone($customer['telefone'] ?? ''))); ?>" data-phone-mask>
                        </div>
                    </div>
                </section>

                <section class="signup-section">
                    <div class="signup-section__header">
                        <span>2</span>
                        <div>
                            <strong>Endereco</strong>
                            <p>Adicione um endereco principal para entrega.</p>
                        </div>
                    </div>

                    <div class="signup-grid signup-grid--double">
                        <div class="form-row">
                            <label for="cep">CEP</label>
                            <input id="cep" name="cep" type="text" required inputmode="tel" maxlength="9" placeholder="00000-000" value="<?= e((string) posted_value('cep', format_cep($primaryAddress['cep'] ?? $customer['cep'] ?? ''))); ?>" data-cep-input>
                        </div>

                        <div class="signup-cep-status" data-cep-status aria-live="polite">
                            Digite um CEP valido para buscar rua, bairro, cidade e estado.
                        </div>

                        <div class="form-row signup-grid__wide">
                            <label for="rua">Rua</label>
                            <input id="rua" name="rua" type="text" required placeholder="Rua, avenida ou travessa" value="<?= e((string) posted_value('rua', $primaryAddress['rua'] ?? '')); ?>" data-address-street>
                        </div>

                        <div class="form-row signup-grid__wide">
                            <label for="bairro">Bairro</label>
                            <input id="bairro" name="bairro" type="text" required placeholder="Bairro" value="<?= e((string) posted_value('bairro', $primaryAddress['bairro'] ?? '')); ?>" data-address-district>
                        </div>

                        <div class="form-row">
                            <label for="numero">Numero</label>
                            <input id="numero" name="numero" type="text" required placeholder="123" value="<?= e((string) posted_value('numero', $primaryAddress['numero'] ?? '')); ?>" data-address-number>
                        </div>

                        <div class="form-row">
                            <label for="complemento">Complemento</label>
                            <input id="complemento" name="complemento" type="text" placeholder="Apartamento, bloco, referencia" value="<?= e((string) posted_value('complemento', $primaryAddress['complemento'] ?? '')); ?>">
                        </div>

                        <div class="form-row">
                            <label for="cidade">Cidade</label>
                            <input id="cidade" name="cidade" type="text" required placeholder="Cidade" value="<?= e((string) posted_value('cidade', $primaryAddress['cidade'] ?? '')); ?>" data-address-city>
                        </div>

                        <div class="form-row">
                            <label for="uf">UF</label>
                            <input id="uf" name="uf" type="text" required maxlength="2" placeholder="PR" value="<?= e((string) posted_value('uf', $primaryAddress['uf'] ?? '')); ?>" data-address-state>
                        </div>
                    </div>
                </section>

                <button class="btn btn--primary" type="submit">Concluir cadastro</button>
            </form>
        </article>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
