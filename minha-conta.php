<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_customer_auth();

$storeSettings = fetch_store_settings();
$customer = current_customer();
$pageTitle = 'Minha Conta';
$bodyClass = 'storefront-body public-body--customer';
$extraStylesheets = ['assets/css/public-auth.css'];
$customerAreaSection = 'dashboard';
$customerAreaTitle = 'Minha conta';
$customerAreaDescription = 'Acompanhe seus dados, ajuste contato, revise enderecos e volte para a vitrine sem se perder.';
$customerId = (int) ($customer['id'] ?? 0);
$connectedIdentities = fetch_customer_identities_indexed($customerId);

require BASE_PATH . '/includes/header.php';
?>

<main class="page-shell">
    <?php require BASE_PATH . '/includes/customer_area_topbar.php'; ?>

    <section class="account-layout account-layout--single">
        <article class="account-card">
            <div class="account-card__header">
                <span class="auth-form-card__badge">Resumo</span>
                <h2>Dados da conta</h2>
                <p>Seus dados estao ativos para facilitar o contato com <?= e($storeSettings['nome_estabelecimento'] ?? 'a loja'); ?>.</p>
            </div>

            <div class="account-card__grid">
                <div class="account-data">
                    <span>Nome</span>
                    <strong><?= e($customer['nome'] ?? ''); ?></strong>
                </div>
                <div class="account-data">
                    <span>Email</span>
                    <strong><?= e($customer['email'] ?? ''); ?></strong>
                    <a class="btn btn--mini account-data__action" href="<?= e(app_url('editar-contato.php#alterar-email')); ?>">Alterar email</a>
                </div>
                <div class="account-data">
                    <span>Telefone</span>
                    <strong><?= e(format_phone($customer['telefone'] ?? '')); ?></strong>
                    <a class="btn btn--mini account-data__action" href="<?= e(app_url('editar-contato.php#editar-telefone')); ?>">Editar telefone</a>
                </div>
                <div class="account-data">
                    <span>CEP</span>
                    <strong><?= e(format_cep($customer['cep'] ?? '')); ?></strong>
                </div>
                <div class="account-data">
                    <span>CPF</span>
                    <strong><?= e(format_cpf($customer['cpf'] ?? '')); ?></strong>
                </div>
                <div class="account-data">
                    <span>Data de nascimento</span>
                    <strong><?= e(format_birth_date($customer['data_nascimento'] ?? '')); ?></strong>
                </div>
                <div class="account-data">
                    <span>Endereco</span>
                    <strong><?= e($customer['endereco'] ?? ''); ?></strong>
                    <a class="btn btn--mini account-data__action" href="<?= e(app_url('editar-enderecos.php')); ?>">Gerenciar enderecos</a>
                </div>
                <div class="account-data">
                    <span>Membro desde</span>
                    <strong><?= e(date('d/m/Y', strtotime((string) ($customer['criado_em'] ?? 'now')))); ?></strong>
                </div>
            </div>

            <div class="account-card__actions">
                <a class="btn btn--ghost" href="<?= e(app_url()); ?>">Voltar para a vitrine</a>
                <a class="btn btn--ghost" href="<?= e(app_url('meus-pedidos.php')); ?>">Meus pedidos</a>
                <a class="btn btn--ghost" href="<?= e(app_url('itens-salvos.php')); ?>">Itens salvos</a>
                <a class="btn btn--ghost" href="<?= e(app_url('meus-cupons.php')); ?>">Meus cupons</a>
                <a class="btn btn--ghost" href="<?= e(app_url('sair.php')); ?>">Sair da conta</a>
            </div>
        </article>

        <article class="account-card" id="contas-conectadas">
            <div class="account-card__header">
                <span class="auth-form-card__badge">Acessos</span>
                <h2>Contas conectadas</h2>
                <p>Vincule outros provedores para entrar mais rapido depois. Cada conta conectada vira mais uma forma segura de acesso.</p>
            </div>

            <div class="account-identity-list">
                <?php foreach (social_provider_catalog() as $provider => $providerMeta): ?>
                    <?php
                    $identity = $connectedIdentities[$provider] ?? null;
                    $isEnabled = !empty($providerMeta['enabled']);
                    $canDisconnect = $identity ? customer_can_disconnect_social_identity($customerId, $provider) : false;
                    ?>
                    <div class="account-identity-card<?= $identity ? ' is-connected' : ''; ?>">
                        <div class="account-identity-card__brand">
                            <span class="account-identity-card__icon account-identity-card__icon--<?= e($provider); ?>">
                                <?= social_provider_icon_markup($provider); ?>
                            </span>
                            <div class="account-identity-card__copy">
                                <strong><?= e(social_provider_label($provider)); ?></strong>

                                <?php if ($identity): ?>
                                    <span><?= e((string) ($identity['provider_email'] ?? $customer['email'] ?? '')); ?></span>
                                <?php elseif ($isEnabled): ?>
                                    <span>Disponivel para vincular</span>
                                <?php else: ?>
                                    <span>Aguardando configuracao</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="account-identity-card__actions">
                            <?php if ($identity): ?>
                                <span class="account-identity-card__status is-connected">Conectado</span>

                                <?php if ($canDisconnect): ?>
                                    <form method="post" action="<?= e(app_url('oauth/social_disconnect.php')); ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="provider" value="<?= e($provider); ?>">
                                        <button class="btn btn--ghost btn--mini" type="submit">Desvincular</button>
                                    </form>
                                <?php else: ?>
                                    <span class="account-identity-card__hint">Mantenha outra forma de acesso antes de remover este vinculo.</span>
                                <?php endif; ?>
                            <?php elseif ($isEnabled): ?>
                                <?php if ($provider === 'google'): ?>
                                    <div class="account-identity-card__google">
                                        <div
                                            data-google-auth-button
                                            data-google-client-id="<?= e((string) GOOGLE_CLIENT_ID); ?>"
                                            data-google-auth-form="google-link-form"
                                            data-google-button-type="icon"
                                            data-google-button-shape="square"
                                            data-google-button-size="large"
                                            data-google-login-uri="<?= e(absolute_app_url('oauth/google.php')); ?>"
                                        ></div>
                                    </div>

                                    <form method="post" action="<?= e(app_url('oauth/google.php')); ?>" id="google-link-form" class="auth-login-social__hidden-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="credential" value="" data-google-credential-input>
                                        <input type="hidden" name="mode" value="link">
                                    </form>
                                <?php else: ?>
                                    <a class="btn btn--ghost btn--mini" href="<?= e(social_provider_start_url($provider, 'link')); ?>">Vincular</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="account-identity-card__status">Indisponivel</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
</main>

<?php if (google_login_enabled() && !isset($connectedIdentities['google'])): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
<?php endif; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
