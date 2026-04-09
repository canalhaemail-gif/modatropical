<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

$currentAdminPage = 'dashboard';
$pageTitle = 'Dashboard';

$dashboard = [
    'pedidos_pendentes' => (int) db()->query("SELECT COUNT(*) FROM pedidos WHERE status = 'pending'")->fetchColumn(),
    'pedidos_ativos' => (int) db()->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('approved', 'ready_pickup', 'out_for_delivery')")->fetchColumn(),
    'pedidos_cancelados' => (int) db()->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('cancelled', 'rejected')")->fetchColumn(),
    'clientes' => (int) db()->query('SELECT COUNT(*) FROM clientes')->fetchColumn(),
    'categorias' => (int) db()->query('SELECT COUNT(*) FROM categorias')->fetchColumn(),
    'mensagens' => table_exists('cliente_notificacoes')
        ? (int) db()->query("SELECT COUNT(*) FROM cliente_notificacoes WHERE tipo = 'mensagem'")->fetchColumn()
        : 0,
    'produtos' => (int) db()->query('SELECT COUNT(*) FROM produtos')->fetchColumn(),
    'ativos' => (int) db()->query('SELECT COUNT(*) FROM produtos WHERE ativo = 1')->fetchColumn(),
    'destaques' => (int) db()->query('SELECT COUNT(*) FROM produtos WHERE destaque = 1')->fetchColumn(),
    'promocoes' => (int) db()->query('SELECT COUNT(*) FROM produtos WHERE promocao = 1')->fetchColumn(),
];

$recentProducts = db()->query(
    'SELECT p.nome, p.preco, p.ativo, c.nome AS categoria_nome, COALESCE(m.nome, \'Sem marca\') AS marca_nome
     FROM produtos p
     INNER JOIN categorias c ON c.id = p.categoria_id
     LEFT JOIN marcas m ON m.id = p.marca_id
     ORDER BY p.criado_em DESC
     LIMIT 6'
)->fetchAll();

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="admin-grid admin-grid--metrics">
    <article class="metric-card">
        <span>Pedidos novos</span>
        <strong><?= e((string) $dashboard['pedidos_pendentes']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Pedidos ativos</span>
        <strong><?= e((string) $dashboard['pedidos_ativos']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Cancelados / recusados</span>
        <strong><?= e((string) $dashboard['pedidos_cancelados']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Total de clientes</span>
        <strong><?= e((string) $dashboard['clientes']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Total de categorias</span>
        <strong><?= e((string) $dashboard['categorias']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Mensagens enviadas</span>
        <strong><?= e((string) $dashboard['mensagens']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Total de produtos</span>
        <strong><?= e((string) $dashboard['produtos']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Produtos ativos</span>
        <strong><?= e((string) $dashboard['ativos']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Em destaque</span>
        <strong><?= e((string) $dashboard['destaques']); ?></strong>
    </article>
    <article class="metric-card">
        <span>Em promocao</span>
        <strong><?= e((string) $dashboard['promocoes']); ?></strong>
    </article>
</section>

<section class="admin-grid admin-grid--content">
    <article class="panel-card">
        <div class="panel-card__header">
            <div>
                <p class="panel-card__eyebrow">atalhos</p>
                <h2>Gestao rapida</h2>
            </div>
        </div>

        <div class="shortcut-grid">
            <a class="shortcut-card" href="<?= e(app_url('admin/categoria_form.php')); ?>">
                <strong>Nova categoria</strong>
                <span>Organize a ordem da vitrine.</span>
            </a>
            <a class="shortcut-card" href="<?= e(app_url('admin/produto_form.php')); ?>">
                <strong>Novo produto</strong>
                <span>Cadastre item com preco, imagem, destaque e promocao.</span>
            </a>
            <a class="shortcut-card" href="<?= e(app_url('admin/pedidos.php')); ?>">
                <strong>Gerenciar pedidos</strong>
                <span>Aceite, recuse e acompanhe o rastreio dos pedidos.</span>
            </a>
            <a class="shortcut-card" href="<?= e(app_url('admin/mensagens.php')); ?>">
                <strong>Nova mensagem</strong>
                <span>Notifique clientes no site e envie email de uma vez.</span>
            </a>
            <a class="shortcut-card" href="<?= e(app_url('admin/clientes.php')); ?>">
                <strong>Gerenciar clientes</strong>
                <span>Edite dados, senha, status ou remova contas.</span>
            </a>
            <a class="shortcut-card" href="<?= e(app_url('admin/configuracoes.php')); ?>">
                <strong>Configurar loja</strong>
                <span>Atualize logo, cores e informacoes da marca.</span>
            </a>
            <a class="shortcut-card" href="<?= e(app_url('index.php')); ?>" target="_blank" rel="noopener noreferrer">
                <strong>Abrir vitrine</strong>
                <span>Visualize a experiencia publica em nova aba.</span>
            </a>
        </div>
    </article>

    <article class="panel-card">
        <div class="panel-card__header">
            <div>
                <p class="panel-card__eyebrow">ultimos itens</p>
                <h2>Produtos recentes</h2>
            </div>
        </div>

        <?php if (!$recentProducts): ?>
            <p class="muted-text">Nenhum produto cadastrado ainda.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Marca</th>
                            <th>Preco</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentProducts as $product): ?>
                            <tr>
                                <td><?= e($product['nome']); ?></td>
                                <td><?= e($product['categoria_nome']); ?></td>
                                <td><?= e($product['marca_nome']); ?></td>
                                <td><?= e(format_currency((float) $product['preco'])); ?></td>
                                <td>
                                    <span class="status-pill <?= (int) $product['ativo'] === 1 ? 'status-pill--success' : 'status-pill--neutral'; ?>">
                                        <?= (int) $product['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
