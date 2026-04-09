<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_auth();

$customer = current_customer();
$customerId = (int) ($customer['id'] ?? 0);
$editingAddressId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editingAddress = $editingAddressId ? find_customer_address($customerId, $editingAddressId) : null;

if ($editingAddressId && !$editingAddress) {
    set_flash('error', 'Endereco nao encontrado.');
    redirect('editar-enderecos.php');
}

if (is_post()) {
    if (!verify_csrf_token(posted_value('csrf_token'))) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('editar-enderecos.php');
    }

    $action = (string) posted_value('action');

    if ($action === 'save_address') {
        $addressId = posted_value('id') !== '' ? (int) posted_value('id') : null;
        $nickname = trim((string) posted_value('apelido'));
        $cep = normalize_cep((string) posted_value('cep'));
        $street = trim((string) posted_value('rua'));
        $district = trim((string) posted_value('bairro'));
        $number = trim((string) posted_value('numero'));
        $complement = trim((string) posted_value('complemento'));
        $city = trim((string) posted_value('cidade'));
        $state = strtoupper(trim((string) posted_value('uf')));
        $principal = posted_value('principal') ? 1 : 0;

        if (!is_valid_cep($cep)) {
            set_flash('error', 'Informe um CEP valido com 8 digitos, com ou sem traco.');
            redirect('editar-enderecos.php' . ($addressId ? '?id=' . $addressId : ''));
        }

        if (strlen($street) < 3 || strlen($district) < 2 || strlen($number) < 1 || strlen($city) < 2 || preg_match('/^[A-Z]{2}$/', $state) !== 1) {
            set_flash('error', 'Preencha rua, bairro, numero, cidade e UF corretamente.');
            redirect('editar-enderecos.php' . ($addressId ? '?id=' . $addressId : ''));
        }

        save_customer_address($customerId, [
            'apelido' => $nickname !== '' ? $nickname : 'Endereco',
            'cep' => $cep,
            'rua' => $street,
            'bairro' => $district,
            'numero' => $number,
            'complemento' => $complement,
            'cidade' => $city,
            'uf' => $state,
            'principal' => $principal,
        ], $addressId);

        set_flash('success', $addressId ? 'Endereco atualizado com sucesso.' : 'Endereco adicionado com sucesso.');
        redirect('editar-enderecos.php');
    }

    if ($action === 'set_primary') {
        $addressId = (int) posted_value('id');

        if (!set_customer_address_as_primary($customerId, $addressId)) {
            set_flash('error', 'Endereco nao encontrado.');
            redirect('editar-enderecos.php');
        }

        set_flash('success', 'Endereco principal atualizado.');
        redirect('editar-enderecos.php');
    }

    if ($action === 'delete_address') {
        $addressId = (int) posted_value('id');

        if (!delete_customer_address($customerId, $addressId)) {
            set_flash('error', 'Endereco nao encontrado.');
            redirect('editar-enderecos.php');
        }

        set_flash('success', 'Endereco removido com sucesso.');
        redirect('editar-enderecos.php');
    }
}

$addresses = fetch_customer_addresses($customerId);
$customer = current_customer();
$storeSettings = fetch_store_settings();
$pageTitle = 'Gerenciar Enderecos';
$bodyClass = 'storefront-body public-body--customer';
$extraStylesheets = ['assets/css/public-auth.css'];
$customerAreaSection = 'addresses';
$customerAreaTitle = 'Enderecos da conta';
$customerAreaDescription = 'Edite seus locais de entrega, escolha o principal e volte para a vitrine sem perder o contexto.';
$showAddressForm = $editingAddress !== null;

$formData = [
    'id' => (string) posted_value('id', $editingAddress['id'] ?? ''),
    'apelido' => (string) posted_value('apelido', $editingAddress['apelido'] ?? ''),
    'cep' => (string) posted_value('cep', format_cep($editingAddress['cep'] ?? '')),
    'rua' => (string) posted_value('rua', $editingAddress['rua'] ?? ''),
    'bairro' => (string) posted_value('bairro', $editingAddress['bairro'] ?? ''),
    'numero' => (string) posted_value('numero', $editingAddress['numero'] ?? ''),
    'complemento' => (string) posted_value('complemento', $editingAddress['complemento'] ?? ''),
    'cidade' => (string) posted_value('cidade', $editingAddress['cidade'] ?? ''),
    'uf' => (string) posted_value('uf', $editingAddress['uf'] ?? ''),
    'principal' => (string) posted_value('principal', (string) ($editingAddress['principal'] ?? (!$addresses ? '1' : '0'))),
];

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <?php require BASE_PATH . '/includes/customer_area_topbar.php'; ?>

    <section class="account-layout account-layout--single">
        <article class="account-card">
            <div class="account-edit-grid">
                <section class="signup-section">
                    <div class="signup-section__header">
                        <span>1</span>
                        <div>
                            <strong>Enderecos salvos</strong>
                            <p>Seu endereco principal continua refletido na conta e nos contatos da loja.</p>
                        </div>
                    </div>

                    <?php if (!$addresses): ?>
                        <div class="status-card">
                            <h2>Nenhum endereco salvo ainda</h2>
                            <p>Seu endereco atual na conta e: <?= e((string) ($customer['endereco'] ?? 'Nao informado')); ?></p>
                            <p>Adicione um endereco estruturado abaixo para gerenciar varios locais com mais facilidade.</p>
                        </div>
                    <?php else: ?>
                        <div class="address-list">
                            <?php foreach ($addresses as $address): ?>
                                <article class="address-card">
                                    <div class="address-card__header">
                                        <div>
                                            <strong><?= e($address['apelido'] !== '' ? (string) $address['apelido'] : 'Endereco'); ?></strong>
                                            <?php if ((int) $address['principal'] === 1): ?>
                                                <span class="address-badge">Principal</span>
                                            <?php endif; ?>
                                        </div>
                                        <span><?= e(format_cep($address['cep'] ?? '')); ?></span>
                                    </div>

                                    <p><?= e(build_customer_address_string(
                                        (string) $address['rua'],
                                        (string) $address['bairro'],
                                        (string) $address['numero'],
                                        (string) $address['complemento'],
                                        (string) $address['cidade'],
                                        (string) $address['uf']
                                    )); ?></p>

                                    <div class="address-card__actions">
                                        <a class="btn btn--mini" href="<?= e(app_url('editar-enderecos.php?id=' . $address['id'])); ?>">Editar</a>

                                        <?php if ((int) $address['principal'] !== 1): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="set_primary">
                                                <input type="hidden" name="id" value="<?= e((string) $address['id']); ?>">
                                                <button class="btn btn--mini" type="submit">Tornar principal</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" onsubmit="return confirm('Remover este endereco?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete_address">
                                            <input type="hidden" name="id" value="<?= e((string) $address['id']); ?>">
                                            <button class="btn btn--mini" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <details class="address-form-shell"<?= $showAddressForm ? ' open' : ''; ?>>
                    <summary class="btn btn--primary address-form-shell__toggle">
                        <?= $editingAddress ? 'Editar endereco' : 'Adicionar novo endereco'; ?>
                    </summary>

                    <section class="signup-section address-form-shell__panel">
                        <div class="signup-section__header">
                            <span>2</span>
                            <div>
                                <strong><?= $editingAddress ? 'Editar endereco' : 'Adicionar endereco'; ?></strong>
                                <p>Voce pode cadastrar casa, trabalho ou qualquer outro local de entrega.</p>
                            </div>
                        </div>

                        <form method="post" class="admin-form" data-address-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="save_address">
                            <input type="hidden" name="id" value="<?= e($formData['id']); ?>">

                            <div class="form-row">
                                <label for="apelido">Apelido do endereco</label>
                                <input id="apelido" name="apelido" type="text" placeholder="Casa, trabalho, loja..." value="<?= e($formData['apelido']); ?>">
                            </div>

                            <div class="form-row">
                                <label for="cep">CEP</label>
                                <input id="cep" name="cep" type="text" required inputmode="tel" maxlength="9" placeholder="00000-000" value="<?= e($formData['cep']); ?>" data-cep-input>
                            </div>

                            <div class="signup-cep-status" data-cep-status aria-live="polite">
                                Digite um CEP valido para buscar rua, bairro, cidade e estado.
                            </div>

                            <div class="form-row">
                                <label for="rua">Rua</label>
                                <input id="rua" name="rua" type="text" required value="<?= e($formData['rua']); ?>" data-address-street>
                            </div>

                            <div class="form-row">
                                <label for="bairro">Bairro</label>
                                <input id="bairro" name="bairro" type="text" required value="<?= e($formData['bairro']); ?>" data-address-district>
                            </div>

                            <div class="account-inline-grid">
                                <div class="form-row">
                                    <label for="numero">Numero</label>
                                    <input id="numero" name="numero" type="text" required value="<?= e($formData['numero']); ?>">
                                </div>

                                <div class="form-row">
                                    <label for="complemento">Complemento</label>
                                    <input id="complemento" name="complemento" type="text" value="<?= e($formData['complemento']); ?>">
                                </div>
                            </div>

                            <div class="account-inline-grid">
                                <div class="form-row">
                                    <label for="cidade">Cidade</label>
                                    <input id="cidade" name="cidade" type="text" required value="<?= e($formData['cidade']); ?>" data-address-city>
                                </div>

                                <div class="form-row">
                                    <label for="uf">UF</label>
                                    <input id="uf" name="uf" type="text" required maxlength="2" value="<?= e($formData['uf']); ?>" data-address-state>
                                </div>
                            </div>

                            <label class="checkbox-row">
                                <input name="principal" type="checkbox" value="1" <?= checked($formData['principal'], 1); ?>>
                                <span>Definir como endereco principal</span>
                            </label>

                            <div class="account-inline-actions">
                                <button class="btn btn--primary" type="submit"><?= $editingAddress ? 'Salvar endereco' : 'Adicionar endereco'; ?></button>
                                <?php if ($editingAddress): ?>
                                    <a class="btn btn--ghost" href="<?= e(app_url('editar-enderecos.php')); ?>">Cancelar edicao</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </section>
                </details>
            </div>

        </article>
    </section>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
