<?php
declare(strict_types=1);

$storeSettings = fetch_store_settings();
$pageTitle = $pageTitle ?? 'Painel Administrativo';
$currentAdminPage = $currentAdminPage ?? '';
$admin = current_admin();
$flashes = pull_flashes();
$primaryColor = $storeSettings['cor_primaria'] ?? '#D97A6C';
$secondaryColor = $storeSettings['cor_secundaria'] ?? '#97B39B';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/admin.css')); ?>">
    <style>
        :root {
            --primary: <?= e($primaryColor); ?>;
            --secondary: <?= e($secondaryColor); ?>;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-mobile-header">
        <button
            class="admin-menu-toggle"
            type="button"
            aria-label="Abrir menu do painel"
            aria-expanded="false"
            aria-controls="admin-sidebar"
            data-admin-menu-toggle
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <a class="admin-mobile-header__brand" href="<?= e(app_url('admin/index.php')); ?>" aria-label="Ir para o dashboard">
            <?php if (!empty($storeSettings['logo'])): ?>
                <img src="<?= e(app_url($storeSettings['logo'])); ?>" alt="Logo da loja">
            <?php else: ?>
                <strong><?= e($storeSettings['nome_estabelecimento'] ?? 'Sua Loja'); ?></strong>
            <?php endif; ?>
        </a>

        <span class="admin-mobile-header__spacer" aria-hidden="true"></span>
    </div>

    <div class="admin-sidebar-backdrop" data-admin-backdrop></div>

    <div class="admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar" data-admin-sidebar>
            <div class="admin-sidebar__header">
                <a class="admin-brand admin-brand--sidebar" href="<?= e(app_url('admin/index.php')); ?>">
                    <?php if (!empty($storeSettings['logo'])): ?>
                        <span class="admin-brand__logo">
                            <img src="<?= e(app_url($storeSettings['logo'])); ?>" alt="Logo da loja">
                        </span>
                    <?php else: ?>
                        <span class="admin-brand__badge">CD</span>
                    <?php endif; ?>
                    <div>
                        <strong><?= e($storeSettings['nome_estabelecimento'] ?? 'Sua Loja'); ?></strong>
                        <small>Painel administrativo</small>
                    </div>
                </a>

                <button
                    class="admin-sidebar__close"
                    type="button"
                    aria-label="Fechar menu do painel"
                    data-admin-menu-close
                >
                    <span></span>
                    <span></span>
                </button>
            </div>

            <nav class="admin-nav">
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'pedidos')); ?>" href="<?= e(app_url('admin/pedidos.php')); ?>">Pedidos</a>
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'dashboard')); ?>" href="<?= e(app_url('admin/index.php')); ?>">Dashboard</a>
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'categorias')); ?>" href="<?= e(app_url('admin/categorias.php')); ?>">Categorias</a>
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'mensagens')); ?>" href="<?= e(app_url('admin/mensagens.php')); ?>">Mensagens</a>
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'produtos')); ?>" href="<?= e(app_url('admin/produtos.php')); ?>">Produtos</a>
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'sabores')); ?>" href="<?= e(app_url('admin/tamanhos.php')); ?>">Tamanhos</a>
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'cupons')); ?>" href="<?= e(app_url('admin/cupons.php')); ?>">Cupons</a>
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'clientes')); ?>" href="<?= e(app_url('admin/clientes.php')); ?>">Clientes</a>
                <a data-admin-nav-link class="<?= e(admin_nav_active($currentAdminPage, 'configuracoes')); ?>" href="<?= e(app_url('admin/configuracoes.php')); ?>">Configuracoes</a>
                <a data-admin-nav-link href="<?= e(app_url('admin/logout.php')); ?>">Sair</a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <p class="admin-topbar__eyebrow">Painel</p>
                    <h1><?= e($pageTitle); ?></h1>
                </div>
                <div class="admin-user">
                    <strong><?= e($admin['nome'] ?? 'Administrador'); ?></strong>
                    <span><?= e($admin['email'] ?? ''); ?></span>
                </div>
            </header>

            <?php if ($flashes): ?>
                <div class="flash-stack flash-stack--admin">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash flash--<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
