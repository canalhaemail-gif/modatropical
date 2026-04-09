<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$storeSettings = fetch_store_settings();
$storeName = trim((string) ($storeSettings['nome_estabelecimento'] ?? APP_NAME));
$pageTitle = 'Exclusao de Dados | ' . $storeName;
$bodyClass = 'legal-page';
$showSplash = false;
$extraStylesheets = ['assets/css/public-legal.css'];
$contactPhone = '24998592033';
$contactPhoneLabel = format_phone($contactPhone);
$contactWhatsApp = whatsapp_link($contactPhone, 'Ola! Quero solicitar exclusao de dados na ' . $storeName . '.');
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
                <span class="legal-hero__eyebrow">Exclusao de Dados</span>
                <h1>Solicitacao de Exclusao de Dados</h1>
                <p>
                    Esta pagina explica como o usuario pode pedir exclusao de dados pessoais e contas vinculadas,
                    inclusive quando o cadastro foi criado com Facebook Login, Google ou TikTok.
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
            <a class="btn btn--primary" href="<?= e($contactWhatsApp); ?>" target="_blank" rel="noopener noreferrer">Solicitar pelo WhatsApp</a>
        </div>

        <div class="legal-hero__summary">
            <div class="legal-summary-card">
                <strong>Canal oficial</strong>
                <span><a href="<?= e($contactWhatsApp); ?>" target="_blank" rel="noopener noreferrer"><?= e($contactPhoneLabel); ?></a></span>
            </div>

            <div class="legal-summary-card">
                <strong>Endereco</strong>
                <span><?= e($contactAddress); ?></span>
            </div>

            <div class="legal-summary-card">
                <strong>Versao publicada</strong>
                <span><?= e($documentUpdatedAt); ?></span>
            </div>

            <div class="legal-summary-card">
                <strong>URL publica</strong>
                <span><?= e(absolute_app_url('exclusao-de-dados.php')); ?></span>
            </div>
        </div>
    </section>

    <div class="legal-grid">
        <article class="catalog-section legal-card">
            <div class="legal-card__header">
                <div>
                    <h2>Como pedir a exclusao</h2>
                    <p>Seguimos um fluxo simples para confirmar identidade e remover os dados que puderem ser apagados de forma segura.</p>
                </div>
                <span class="legal-chip">Passo a passo</span>
            </div>

            <ol class="legal-steps">
                <li>Envie sua solicitacao pelo WhatsApp <?= e($contactPhoneLabel); ?> informando nome completo e email usado na conta.</li>
                <li>Se o cadastro foi criado com Facebook Login, Google ou TikTok, informe tambem qual provedor foi utilizado.</li>
                <li>Podemos pedir confirmacao adicional para validar que o pedido realmente partiu do titular da conta.</li>
                <li>Depois da validacao, a conta e os dados elegiveis para exclusao serao removidos ou anonimizados no prazo operacional aplicavel.</li>
            </ol>
        </article>

        <article class="catalog-section legal-card">
            <div class="legal-card__header">
                <div>
                    <h2>O que sera removido</h2>
                    <p>Quando a solicitacao for validada, removeremos ou desvincularemos os dados que nao precisem ser mantidos por obrigacao legal.</p>
                </div>
                <span class="legal-chip">Remocao</span>
            </div>

            <ul class="legal-list">
                <li>Conta de cliente e dados cadastrais associados, quando a exclusao total for possivel.</li>
                <li>Identidades vinculadas de login social, como Facebook, Google e TikTok.</li>
                <li>Dados complementares mantidos para autenticacao, favoritos, preferencias e historico interno nao obrigatorio.</li>
                <li>Informacoes de atendimento relacionadas ao pedido de exclusao, apos encerramento do processo, salvo necessidade de registro minimo.</li>
            </ul>
        </article>

        <article class="catalog-section legal-card">
            <div class="legal-card__header">
                <div>
                    <h2>O que pode ser mantido</h2>
                    <p>Alguns registros podem continuar armazenados pelo periodo exigido por lei ou por necessidade legitima de seguranca e auditoria.</p>
                </div>
                <span class="legal-chip">Retencao legal</span>
            </div>

            <ul class="legal-list">
                <li>Registros fiscais, financeiros e de pagamento ligados a pedidos concluidos.</li>
                <li>Historico minimo necessario para prevencao a fraude, defesa em processos ou cumprimento de obrigacoes legais.</li>
                <li>Logs tecnicos essenciais para seguranca da plataforma durante o prazo operacional cabivel.</li>
            </ul>

            <div class="legal-note">
                <strong>Atencao</strong>
                A exclusao de dados nao apaga automaticamente registros que a lei obriga a manter. Nesses casos, o dado pode ser bloqueado, isolado ou minimizado para uso restrito.
            </div>
        </article>

        <article class="catalog-section legal-card">
            <div class="legal-card__header">
                <div>
                    <h2>Atalhos uteis</h2>
                    <p>Se voce ainda consegue acessar sua conta, alguns ajustes podem ser resolvidos antes da exclusao completa.</p>
                </div>
                <span class="legal-chip">Conta</span>
            </div>

            <div class="legal-cross-links">
                <div class="legal-link-card">
                    <strong>Minha conta</strong>
                    <p>Usuarios autenticados podem revisar dados e gerenciar contas conectadas antes de pedir exclusao total.</p>
                    <a href="<?= e(app_url('minha-conta.php#contas-conectadas')); ?>">Abrir area da conta</a>
                </div>

                <div class="legal-link-card">
                    <strong>Politica de Privacidade</strong>
                    <p>Consulte as regras completas de coleta, uso, compartilhamento e armazenamento de dados da loja.</p>
                    <a href="<?= e(app_url('politica-de-privacidade.php')); ?>">Ler politica</a>
                </div>
            </div>
        </article>
    </div>

    <footer class="legal-footer">
        <p>
            Esta pagina pode ser usada como URL publica de instrucoes de exclusao de dados em integracoes com Facebook Login e outras formas de autenticacao social da <?= e($storeName); ?>.
        </p>
    </footer>
</main>

<?php require BASE_PATH . '/includes/footer.php'; ?>
