<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$storeSettings = fetch_store_settings();
$storeName = trim((string) ($storeSettings['nome_estabelecimento'] ?? APP_NAME));
$pageTitle = 'Politica de Privacidade | ' . $storeName;
$bodyClass = 'legal-page';
$showSplash = false;
$extraStylesheets = ['assets/css/public-legal.css'];
$contactPhone = '24998592033';
$contactPhoneLabel = format_phone($contactPhone);
$contactWhatsApp = whatsapp_link($contactPhone, 'Ola! Quero falar sobre privacidade e dados na ' . $storeName . '.');
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
                <span class="legal-hero__eyebrow">Politica de Privacidade</span>
                <h1>Privacidade e Protecao de Dados</h1>
                <p>
                    Esta politica explica como a <?= e($storeName); ?> coleta, usa, protege e compartilha dados pessoais
                    quando voce acessa a loja, cria conta, faz pedidos, utiliza login social ou entra em contato com nosso atendimento.
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
            <a class="btn btn--ghost" href="<?= e(app_url('exclusao-de-dados.php')); ?>">Exclusao de dados</a>
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
        <article class="catalog-section legal-card" id="dados-coletados">
            <div class="legal-card__header">
                <div>
                    <h2>Quais dados podemos coletar</h2>
                    <p>Coletamos apenas os dados necessarios para operar a conta, processar pedidos e prestar atendimento.</p>
                </div>
                <span class="legal-chip">Coleta</span>
            </div>

            <ul class="legal-list">
                <li>Dados de cadastro, como nome, email, telefone, CPF, data de nascimento e endereco.</li>
                <li>Dados de pedidos, entrega, cupons, carrinho, favoritos, historico e acompanhamento de compras.</li>
                <li>Dados tecnicos basicos, como paginas acessadas, horario de uso e identificadores de sessao.</li>
                <li>Dados vindos de login social, quando autorizado pelo usuario, como email, nome e identificador da conta no Google, Facebook ou TikTok.</li>
                <li>Mensagens enviadas para nosso atendimento, incluindo solicitacoes por WhatsApp relacionadas a pedidos, suporte, privacidade ou exclusao de dados.</li>
            </ul>
        </article>

        <article class="catalog-section legal-card" id="uso-dos-dados">
            <div class="legal-card__header">
                <div>
                    <h2>Como usamos seus dados</h2>
                    <p>Os dados sao utilizados para operacao da conta e cumprimento das obrigacoes comerciais e legais da loja.</p>
                </div>
                <span class="legal-chip">Uso</span>
            </div>

            <ul class="legal-list">
                <li>Criar, autenticar e proteger a conta do cliente.</li>
                <li>Receber, confirmar, separar, entregar e acompanhar pedidos.</li>
                <li>Enviar comunicacoes sobre conta, pedido, pagamento, entrega e suporte.</li>
                <li>Prevenir fraude, abuso de acesso e uso indevido dos meios de pagamento.</li>
                <li>Cumprir obrigacoes fiscais, contabeis, regulatorias e de seguranca.</li>
                <li>Melhorar a experiencia da loja, corrigir falhas e evoluir recursos do sistema.</li>
            </ul>

            <div class="legal-note">
                <strong>Login social</strong>
                Quando voce entra com Google, Facebook ou TikTok, usamos apenas os dados liberados por esse provedor para identificar sua conta, confirmar email e facilitar o cadastro.
            </div>
        </article>

        <article class="catalog-section legal-card" id="compartilhamento">
            <div class="legal-card__header">
                <div>
                    <h2>Compartilhamento e armazenamento</h2>
                    <p>Seus dados nao sao vendidos. O compartilhamento ocorre somente quando necessario para operar a loja ou cumprir a lei.</p>
                </div>
                <span class="legal-chip">Seguranca</span>
            </div>

            <ul class="legal-list">
                <li>Podemos compartilhar dados com provedores de pagamento, ferramentas de autenticacao e parceiros operacionais usados no funcionamento da loja.</li>
                <li>Podemos compartilhar dados com autoridades competentes quando houver obrigacao legal, decisao judicial ou necessidade de defesa de direitos.</li>
                <li>Os dados ficam armazenados pelo tempo necessario para executar servicos, manter historico da conta e cumprir prazos legais e fiscais aplicaveis.</li>
                <li>Adotamos medidas administrativas e tecnicas razoaveis para reduzir risco de acesso nao autorizado, perda ou uso indevido das informacoes.</li>
            </ul>
        </article>

        <article class="catalog-section legal-card" id="direitos">
            <div class="legal-card__header">
                <div>
                    <h2>Seus direitos e solicitacoes</h2>
                    <p>Voce pode pedir acesso, atualizacao, correcao ou exclusao dos seus dados pessoais, respeitadas as obrigacoes legais de retencao.</p>
                </div>
                <span class="legal-chip">Direitos</span>
            </div>

            <p>
                Para tratar qualquer assunto de privacidade, entre em contato pelo WhatsApp
                <a href="<?= e($contactWhatsApp); ?>" target="_blank" rel="noopener noreferrer"><?= e($contactPhoneLabel); ?></a>.
                Para solicitacoes de remocao de dados, consulte tambem nossa pagina de
                <a href="<?= e(app_url('exclusao-de-dados.php')); ?>">Exclusao de Dados</a>.
            </p>

            <div class="legal-cross-links">
                <div class="legal-link-card">
                    <strong>Exclusao de dados</strong>
                    <p>Veja o passo a passo para solicitar remocao de conta, dados cadastrais e identidades vinculadas por login social.</p>
                    <a href="<?= e(app_url('exclusao-de-dados.php')); ?>">Abrir pagina de exclusao</a>
                </div>

                <div class="legal-link-card">
                    <strong>Entrar na conta</strong>
                    <p>Se voce ainda tem acesso ao seu perfil, use sua conta para revisar informacoes, acompanhar pedidos e ajustar dados pessoais.</p>
                    <a href="<?= e(app_url('entrar.php')); ?>">Ir para o login</a>
                </div>
            </div>
        </article>
    </div>

    <footer class="legal-footer">
        <p>
            Ao continuar usando a <?= e($storeName); ?>, voce concorda com esta politica na extensao necessaria para cadastro, autenticacao,
            atendimento, pedidos, pagamento, entrega e cumprimento das exigencias legais aplicaveis.
        </p>
    </footer>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
