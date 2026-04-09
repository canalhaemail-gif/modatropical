<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$storeSettings = fetch_store_settings();
$storeName = trim((string) ($storeSettings['nome_estabelecimento'] ?? APP_NAME));
$pageTitle = 'Termos de Servico | ' . $storeName;
$bodyClass = 'legal-page';
$showSplash = false;
$extraStylesheets = ['assets/css/public-legal.css'];
$contactPhone = '24998592033';
$contactPhoneLabel = format_phone($contactPhone);
$contactWhatsApp = whatsapp_link($contactPhone, 'Ola! Quero falar sobre os termos de servico da ' . $storeName . '.');
$contactAddress = 'Avenida Waldir Sobreira Pires, 1189, Belo Horizonte, Volta Redonda/RJ, CEP 27279-071';
$documentUpdatedAt = '05/04/2026';
$storeLogo = trim((string) ($storeSettings['logo'] ?? ''));

if ($storeLogo !== '') {
    $storeLogoUrl = app_url($storeLogo);
} elseif (is_file(BASE_PATH . '/logo.png')) {
    $storeLogoUrl = app_url('logo.png');
} else {
    $storeLogoUrl = app_url('assets/img/default-logo.svg');
}

require BASE_PATH . '/includes/header.php';
?>

<main class="legal-shell">
    <section class="hero-card legal-hero">
        <div class="legal-hero__top">
            <div class="legal-hero__title">
                <span class="legal-hero__eyebrow">Termos de Servico</span>
                <h1>Termos e Condicoes de Uso</h1>
                <p>
                    Estes termos regulam o acesso e o uso da <?= e($storeName); ?>, incluindo cadastro,
                    login social, pedidos, pagamentos, entrega, atendimento e recursos da conta do cliente.
                </p>
            </div>

            <div class="legal-hero__brand">
                <div class="legal-hero__brand-media">
                    <img src="<?= e($storeLogoUrl); ?>" alt="Logo de <?= e($storeName); ?>">
                </div>
                <p class="legal-hero__brand-caption"><?= e($storeName); ?></p>
            </div>
        </div>

        <div class="legal-hero__actions">
            <a class="btn btn--ghost" href="<?= e(app_url()); ?>">Voltar para a loja</a>
            <a class="btn btn--ghost" href="<?= e(app_url('politica-de-privacidade.php')); ?>">Politica de Privacidade</a>
            <a class="btn btn--primary" href="<?= e($contactWhatsApp); ?>" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a>
        </div>

        <div class="legal-hero__summary">
            <div class="legal-summary-card">
                <strong>Contato</strong>
                <span><a href="<?= e($contactWhatsApp); ?>" target="_blank" rel="noopener noreferrer"><?= e($contactPhoneLabel); ?></a></span>
            </div>

            <div class="legal-summary-card">
                <strong>Endereco</strong>
                <span><?= e($contactAddress); ?></span>
            </div>

            <div class="legal-summary-card">
                <strong>Ultima atualizacao</strong>
                <span><?= e($documentUpdatedAt); ?></span>
            </div>
        </div>
    </section>

    <div class="legal-grid">
        <article class="catalog-section legal-card">
            <div class="legal-card__header">
                <div>
                    <h2>Aceitacao e uso da plataforma</h2>
                    <p>Ao acessar ou usar a loja, o cliente concorda com estas regras e com a Politica de Privacidade vigente.</p>
                </div>
                <span class="legal-chip">Uso</span>
            </div>

            <ul class="legal-list">
                <li>O usuario deve fornecer dados verdadeiros, atuais e completos ao criar conta ou finalizar pedidos.</li>
                <li>O acesso a areas autenticadas e recursos de login social depende da guarda adequada das credenciais do proprio usuario.</li>
                <li>O uso da loja para fraude, abuso, tentativa de invasao, revenda indevida ou atividade ilicita pode gerar bloqueio da conta.</li>
            </ul>
        </article>

        <article class="catalog-section legal-card">
            <div class="legal-card__header">
                <div>
                    <h2>Pedidos, estoque e pagamento</h2>
                    <p>Os pedidos dependem de disponibilidade de estoque, confirmacao de dados e aprovacao do pagamento quando aplicavel.</p>
                </div>
                <span class="legal-chip">Pedidos</span>
            </div>

            <ul class="legal-list">
                <li>Precos, condicoes promocionais, frete e prazo podem mudar sem aviso previo ate a conclusao do pedido.</li>
                <li>O pedido pode ser recusado, ajustado ou cancelado em caso de falta de estoque, erro evidente de cadastro, suspeita de fraude ou impossibilidade operacional.</li>
                <li>Dados fiscais, financeiros e operacionais do pedido podem ser mantidos pelo prazo exigido por lei.</li>
            </ul>
        </article>

        <article class="catalog-section legal-card">
            <div class="legal-card__header">
                <div>
                    <h2>Conta, login social e atendimento</h2>
                    <p>A loja pode usar provedores externos para autenticar e vincular contas quando o cliente optar por login social.</p>
                </div>
                <span class="legal-chip">Conta</span>
            </div>

            <ul class="legal-list">
                <li>Google, Facebook, TikTok e futuras integracoes autorizadas podem ser usados para identificar o titular e facilitar o cadastro.</li>
                <li>O cliente e responsavel por revisar os dados da conta e manter email, telefone e endereco corretos.</li>
                <li>Solicitacoes de suporte, alteracao cadastral e exclusao de dados podem ser feitas pelos canais oficiais da loja.</li>
            </ul>
        </article>

        <article class="catalog-section legal-card">
            <div class="legal-card__header">
                <div>
                    <h2>Limites de responsabilidade</h2>
                    <p>A loja atua com esforco comercial razoavel, mas nao garante funcionamento ininterrupto de servicos de terceiros ou da propria internet.</p>
                </div>
                <span class="legal-chip">Responsabilidade</span>
            </div>

            <ul class="legal-list">
                <li>Falhas de operadoras, bancos, gateways, redes sociais, navegadores ou dispositivos podem afetar o acesso e a conclusao de pedidos.</li>
                <li>Links externos e servicos de terceiros seguem regras e politicas proprias, sem controle integral da loja.</li>
                <li>Quando houver obrigacao legal ou necessidade operacional, estes termos poderao ser atualizados, com a versao vigente publicada nesta pagina.</li>
            </ul>

            <div class="legal-cross-links">
                <div class="legal-link-card">
                    <strong>Politica de Privacidade</strong>
                    <p>Veja como os dados pessoais sao coletados, usados, protegidos e compartilhados durante o uso da loja.</p>
                    <a href="<?= e(app_url('politica-de-privacidade.php')); ?>">Ler politica</a>
                </div>

                <div class="legal-link-card">
                    <strong>Exclusao de Dados</strong>
                    <p>Se precisar solicitar remocao da conta ou de informacoes pessoais, use nossa pagina publica de exclusao.</p>
                    <a href="<?= e(app_url('exclusao-de-dados.php')); ?>">Abrir pagina de exclusao</a>
                </div>
            </div>
        </article>
    </div>

    <footer class="legal-footer">
        <p>
            Estes termos se aplicam ao uso da <?= e($storeName); ?> em navegadores, integracoes de autenticacao e canais oficiais de atendimento.
        </p>
    </footer>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
