<?php
declare(strict_types=1);

$storeName = trim((string) ($storeSettings['nome_estabelecimento'] ?? 'Moda Tropical'));
$storeDescription = trim((string) ($storeSettings['descricao_loja'] ?? 'Moda feminina com toque tropical, pecas leves e colecoes pensadas para destacar seu estilo.'));
$storePhone = format_phone((string) ($storeSettings['telefone_whatsapp'] ?? ''));
$currentScript = strtolower((string) basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$showFullStorefrontFooter = $currentScript === 'index.php';
?>
<?php if (!$showFullStorefrontFooter): ?>
<style>
    @media (max-width: 767px) {
        .storefront {
            padding-bottom: 0;
            min-height: auto;
        }

        .storefront-footer-shell--minimal {
            margin-top: 4px;
            align-items: center;
            justify-content: center;
        }

        .storefront-footer-shell--minimal .storefront-footer__legal {
            width: 100%;
            padding: 0;
            text-align: center;
        }
    }

    @media (min-width: 768px) {
        body.storefront-body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .storefront {
            flex: 1 0 auto;
            width: min(calc(100% - 24px), 1280px);
        }

        .storefront-footer-shell--minimal {
            margin-top: auto;
            padding-top: 16px;
        }

        .storefront-footer-shell--minimal .storefront-footer__legal {
            width: 100%;
            text-align: center;
        }
    }
</style>
<div class="storefront-footer-shell storefront-footer-shell--minimal">
    <div class="storefront-footer__legal">TODOS DIREITOS RESERVADOS A MODA TROPICAL</div>
</div>
<?php return; ?>
<?php endif; ?>
<div class="storefront-footer-shell">
    <footer class="storefront-footer">
        <div class="storefront-footer__section storefront-footer__brand">
            <h3 class="storefront-footer__heading"><?= e($storeName); ?></h3>
            <p><?= e($storeDescription); ?></p>
        </div>

        <div class="storefront-footer__section storefront-footer__section--categories">
            <h3 class="storefront-footer__heading">Categorias</h3>
            <?php foreach (array_slice($visibleCategories, 0, 4) as $category): ?>
                <a class="storefront-footer__link" href="<?= e(storefront_category_url((string) $category['slug'])); ?>"><?= e($category['nome']); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="storefront-footer__section storefront-footer__section--support">
            <h3 class="storefront-footer__heading">Atendimento</h3>
            <?php if ($storePhone !== ''): ?>
                <a class="storefront-footer__meta storefront-footer__meta--link" href="<?= e($storeWhatsApp); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="storefront-footer__meta-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12.04 2C6.56 2 2.11 6.45 2.11 11.93c0 1.76.46 3.47 1.34 4.99L2 22l5.22-1.37a9.89 9.89 0 0 0 4.82 1.23h.01c5.48 0 9.93-4.45 9.93-9.93S17.52 2 12.04 2Zm5.79 14.02c-.24.67-1.42 1.31-1.96 1.39-.5.07-1.12.1-1.81-.12-.42-.14-.96-.31-1.66-.61-2.92-1.26-4.82-4.21-4.97-4.41-.15-.2-1.19-1.59-1.19-3.04s.76-2.16 1.03-2.46c.27-.3.59-.37.79-.37.2 0 .4 0 .58.01.19.01.44-.07.69.54.24.59.82 2.03.89 2.17.07.15.12.31.02.51-.1.2-.15.32-.3.49-.15.17-.32.39-.46.52-.15.15-.3.32-.13.62.17.3.77 1.27 1.64 2.05 1.13 1.01 2.09 1.33 2.39 1.48.3.15.47.12.64-.07.17-.2.73-.85.92-1.14.2-.29.39-.24.66-.15.27.1 1.69.8 1.98.94.29.15.49.22.56.34.08.12.08.71-.16 1.38Z"/>
                        </svg>
                    </span>
                    <span><?= e($storePhone); ?></span>
                </a>
            <?php endif; ?>
            <?php if (!empty($storeSettings['horario_funcionamento'])): ?>
                <span class="storefront-footer__meta">
                    <span class="storefront-footer__meta-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 1.75A10.25 10.25 0 1 0 22.25 12 10.26 10.26 0 0 0 12 1.75Zm0 18.5A8.25 8.25 0 1 1 20.25 12 8.26 8.26 0 0 1 12 20.25Zm.75-13h-1.5v5.18l4.04 2.43.77-1.28-3.31-1.99Z"/>
                        </svg>
                    </span>
                    <span><?= e($storeSettings['horario_funcionamento']); ?></span>
                </span>
            <?php endif; ?>
            <?php if (!empty($storeSettings['endereco'])): ?>
                <span class="storefront-footer__meta">
                    <span class="storefront-footer__meta-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 2.25a6.75 6.75 0 0 0-6.75 6.75c0 5.03 6.01 11.88 6.26 12.17a.64.64 0 0 0 .98 0c.25-.29 6.26-7.14 6.26-12.17A6.75 6.75 0 0 0 12 2.25Zm0 9.25A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5Z"/>
                        </svg>
                    </span>
                    <span><?= e($storeSettings['endereco']); ?></span>
                </span>
            <?php endif; ?>
            <?php if ($storeWhatsApp !== '#'): ?>
                <a class="storefront-footer__cta" href="<?= e($storeWhatsApp); ?>" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a>
            <?php endif; ?>
        </div>

        <div class="storefront-footer__section storefront-footer__section--help">
            <h3 class="storefront-footer__heading">Ajuda</h3>
            <a class="storefront-footer__link" href="<?= e(app_url('rastreio.php')); ?>">Rastrear pedido</a>
            <a class="storefront-footer__link" href="<?= e(app_url('entrar.php')); ?>">Entrar</a>
            <a class="storefront-footer__link" href="<?= e(app_url('cadastro.php')); ?>">Criar conta</a>
            <a class="storefront-footer__link" href="<?= e(app_url('promocoes.php')); ?>">Promocoes</a>
            <a class="storefront-footer__link" href="<?= e(app_url('termos-de-servico.php')); ?>">Termos de Servico</a>
            <a class="storefront-footer__link" href="<?= e(app_url('politica-de-privacidade.php')); ?>">Politica de Privacidade</a>
            <a class="storefront-footer__link" href="<?= e(app_url('exclusao-de-dados.php')); ?>">Exclusao de Dados</a>
        </div>
    </footer>

    <div class="storefront-footer__legal">TODOS DIREITOS RESERVADOS A MODA TROPICAL</div>
</div>
