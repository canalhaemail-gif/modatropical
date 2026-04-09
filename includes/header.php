<?php
declare(strict_types=1);

$storeSettings = $storeSettings ?? fetch_store_settings();
$pageTitle = $pageTitle ?? $storeSettings['nome_estabelecimento'] ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$flashes = pull_flashes();
$primaryColor = $storeSettings['cor_primaria'] ?? '#D97A6C';
$secondaryColor = $storeSettings['cor_secundaria'] ?? '#97B39B';
$extraStylesheets = $extraStylesheets ?? [];
$showSplash = $showSplash ?? true;
$currentScriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$faviconPath = is_file(BASE_PATH . '/assets/img/favicon.png')
    ? asset_url('assets/img/favicon.png')
    : (is_file(BASE_PATH . '/logo.png')
        ? asset_url('logo.png')
        : asset_url('assets/img/default-logo.svg'));
$storefrontBrandLogoSource = critical_image_relative_path((string) ($storeSettings['logo'] ?? ''));

if ($storefrontBrandLogoSource === null) {
    $storefrontBrandLogoSource = is_file(BASE_PATH . '/logo.png')
        ? 'logo.png'
        : 'assets/img/default-logo.svg';
}

$inlineBrandLogoSource = is_file(BASE_PATH . '/assets/img/logo-fast.webp')
    ? 'assets/img/logo-fast.webp'
    : null;
$inlineBrandLogoDataUri = $inlineBrandLogoSource !== null ? inline_asset_data_uri($inlineBrandLogoSource) : null;
$storefrontBrandLogoUrl = critical_image_url($storefrontBrandLogoSource);
$storefrontBrandLogoRenderUrl = $inlineBrandLogoDataUri ?? $storefrontBrandLogoUrl;

$hostName = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$hostName = preg_replace('/:\\d+$/', '', $hostName) ?? $hostName;
if ($showSplash && ($hostName === 'localhost' || str_ends_with($hostName, '.local'))) {
    $showSplash = false;
}
$splashLogoSource = is_file(BASE_PATH . '/logo.png')
    ? 'logo.png'
    : (is_file(BASE_PATH . '/assets/img/default-logo.svg') ? 'assets/img/default-logo.svg' : null);
$splashLogoPath = $inlineBrandLogoDataUri
    ?? ($splashLogoSource !== null ? critical_image_url($splashLogoSource) : $faviconPath);
$criticalImageCandidates = $criticalImageCandidates ?? [];

if ($inlineBrandLogoDataUri === null) {
    $criticalImageCandidates[] = $storefrontBrandLogoSource;
    $criticalImageCandidates[] = $splashLogoSource;
}

if ($currentScriptName === 'index.php' && is_file(BASE_PATH . '/destaques.png')) {
    $criticalImageCandidates[] = 'destaques.png';
}

if (isset($welcomeTitleImagePath) && (!isset($welcomeTitleImageRenderUrl) || !str_starts_with((string) $welcomeTitleImageRenderUrl, 'data:image/'))) {
    $criticalImageCandidates[] = $welcomeTitleImagePath;
}

if (isset($promotionsHeadingImage) && (!isset($promotionsHeadingImageRenderUrl) || !str_starts_with((string) $promotionsHeadingImageRenderUrl, 'data:image/'))) {
    $criticalImageCandidates[] = $promotionsHeadingImage;
}

if (isset($couponsHeadingImage) && (!isset($couponsHeadingImageRenderUrl) || !str_starts_with((string) $couponsHeadingImageRenderUrl, 'data:image/'))) {
    $criticalImageCandidates[] = $couponsHeadingImage;
}

if (isset($categoryHeadingImagePath) && (!isset($categoryHeadingImageRenderUrl) || !str_starts_with((string) $categoryHeadingImageRenderUrl, 'data:image/'))) {
    $criticalImageCandidates[] = $categoryHeadingImagePath;
}

$criticalImagePreloads = critical_image_preload_entries(array_values(array_filter(
    $criticalImageCandidates,
    static fn($candidate): bool => $candidate !== null && $candidate !== ''
)));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    <meta name="description" content="<?= e($storeSettings['descricao_loja'] ?? 'Vitrine digital'); ?>">
    <link rel="icon" type="image/png" href="<?= e($faviconPath); ?>">
    <link rel="shortcut icon" type="image/png" href="<?= e($faviconPath); ?>">
    <link rel="apple-touch-icon" href="<?= e($faviconPath); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php foreach ($criticalImagePreloads as $preload): ?>
        <link
            rel="preload"
            as="image"
            href="<?= e((string) $preload['url']); ?>"
            <?= !empty($preload['media']) ? ' media="' . e((string) $preload['media']) . '"' : ''; ?>
        >
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/public.css')); ?>">
    <?php foreach ($extraStylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= e(asset_url((string) $stylesheet)); ?>">
    <?php endforeach; ?>
    <style>
        :root {
            --primary: <?= e($primaryColor); ?>;
            --secondary: <?= e($secondaryColor); ?>;
        }
        <?php if ($showSplash): ?>
        .app-splash {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
            padding: 24px;
            overflow: hidden;
            background: rgba(248, 245, 242, 0.58);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: opacity 0.45s ease, visibility 0.45s ease;
        }
        .app-splash__backdrop {
            display: none;
        }
        .app-splash__content {
            position: relative;
            z-index: 1;
            display: grid;
            justify-items: center;
            text-align: center;
        }
        .app-splash-loader {
            position: relative;
            display: grid;
            place-items: center;
            width: min(180px, 44vw);
            height: min(180px, 44vw);
            border-radius: 50%;
            user-select: none;
        }
        .app-splash-loader__ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            animation: appSplashRotate 2s linear infinite;
            z-index: 0;
        }
        .app-splash-loader__logo-shell {
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
            width: min(144px, 34vw);
            height: min(144px, 34vw);
            background: transparent;
        }
        .app-splash-loader__logo-shell img {
            width: min(138px, 32vw);
            height: auto;
            max-height: min(84px, 20vw);
            object-fit: contain;
            filter: drop-shadow(0 8px 18px rgba(41, 28, 76, 0.18));
        }
        @keyframes appSplashRotate {
            0% {
                transform: rotate(90deg);
                box-shadow:
                    0 10px 20px 0 rgba(255, 255, 255, 0.85) inset,
                    0 20px 30px 0 rgba(173, 95, 255, 0.78) inset,
                    0 60px 60px 0 rgba(71, 30, 236, 0.82) inset;
            }
            50% {
                transform: rotate(270deg);
                box-shadow:
                    0 10px 20px 0 rgba(255, 255, 255, 0.85) inset,
                    0 20px 10px 0 rgba(214, 10, 71, 0.76) inset,
                    0 40px 60px 0 rgba(49, 30, 128, 0.82) inset;
            }
            100% {
                transform: rotate(450deg);
                box-shadow:
                    0 10px 20px 0 rgba(255, 255, 255, 0.85) inset,
                    0 20px 30px 0 rgba(173, 95, 255, 0.78) inset,
                    0 60px 60px 0 rgba(71, 30, 236, 0.82) inset;
            }
        }
        <?php endif; ?>
    </style>
</head>
<body class="public-body <?= e($bodyClass); ?>">
    <?php if ($criticalImagePreloads): ?>
        <div
            aria-hidden="true"
            style="position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;white-space:nowrap;"
        >
            <?php foreach ($criticalImagePreloads as $preload): ?>
                <img
                    src="<?= e((string) $preload['url']); ?>"
                    alt=""
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                    width="1"
                    height="1"
                >
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($showSplash): ?>
        <div class="app-splash" data-splash>
            <div class="app-splash__backdrop"></div>
            <div class="app-splash__content app-splash__content--loader" aria-label="Carregando loja">
                <div class="app-splash-loader" aria-hidden="true">
                    <div class="app-splash-loader__ring"></div>
                    <div class="app-splash-loader__logo-shell">
                        <img src="<?= e($splashLogoPath); ?>" alt="Moda Tropical">
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($flashes): ?>
        <div class="flash-stack" data-flash-stack>
            <?php foreach ($flashes as $flash): ?>
                <div class="flash flash--<?= e($flash['type']); ?>" data-flash><?= e($flash['message']); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
