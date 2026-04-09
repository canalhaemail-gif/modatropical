<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Homologacao PagBank Sandbox';
$result = null;
$errorMessage = '';

if (isset($_GET['download'])) {
    $fileName = trim((string) $_GET['download']);

    if (!pagbank_homologation_log_is_valid_name($fileName)) {
        http_response_code(404);
        echo 'Arquivo invalido.';
        exit;
    }

    $absolutePath = pagbank_homologation_log_absolute_path($fileName);

    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        http_response_code(404);
        echo 'Arquivo nao encontrado.';
        exit;
    }

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . (string) filesize($absolutePath));
    readfile($absolutePath);
    exit;
}

if (is_post()) {
    if (!verify_csrf_token((string) posted_value('csrf_token'))) {
        $errorMessage = 'Sessao expirada. Atualize a pagina e gere o teste novamente.';
    } else {
        try {
            $result = pagbank_run_checkout_homologation();
        } catch (Throwable $exception) {
            $errorMessage = $exception->getMessage();
        }
    }
}

$requestJson = $result !== null
    ? json_encode($result['request_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : '';
$responseJson = $result !== null
    ? json_encode($result['response_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f7f1ea;
            --panel: #fffaf6;
            --panel-border: rgba(217, 122, 108, 0.18);
            --text: #4c342d;
            --muted: #8d756d;
            --primary: #d97a6c;
            --primary-deep: #bd6356;
            --success: #2f9e68;
            --danger: #b93f3f;
            --code: #241726;
            --code-border: rgba(127, 75, 255, 0.15);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top, rgba(255,255,255,0.85), transparent 32%),
                linear-gradient(180deg, #fbf7f3 0%, var(--bg) 100%);
            color: var(--text);
        }

        .shell {
            width: min(1100px, calc(100% - 32px));
            margin: 32px auto;
            display: grid;
            gap: 20px;
        }

        .hero,
        .panel {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: 28px;
            box-shadow: 0 24px 48px rgba(101, 63, 42, 0.08);
        }

        .hero {
            padding: 28px;
            display: grid;
            gap: 16px;
        }

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(217, 122, 108, 0.1);
            color: var(--primary);
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 0.92;
            letter-spacing: -0.03em;
            text-transform: uppercase;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 1.02rem;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .button {
            appearance: none;
            border: none;
            border-radius: 16px;
            padding: 14px 20px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            text-decoration: none;
        }

        .button:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(98, 62, 44, 0.12);
        }

        .button--primary {
            background: linear-gradient(135deg, #d97a6c 0%, #ee9a63 100%);
            color: #fff;
        }

        .button--soft {
            background: #fff;
            color: var(--text);
            border: 1px solid var(--panel-border);
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .status-card {
            padding: 18px;
            border-radius: 20px;
            background: #fff;
            border: 1px solid rgba(76, 52, 45, 0.08);
        }

        .status-card__label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.76rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }

        .status-card__value {
            font-size: 1.08rem;
            font-weight: 700;
            word-break: break-word;
        }

        .status-card__value--success { color: var(--success); }
        .status-card__value--danger { color: var(--danger); }

        .panel {
            padding: 24px;
            display: grid;
            gap: 18px;
        }

        .panel h2 {
            margin: 0;
            font-size: 1.3rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .code-block {
            margin: 0;
            padding: 18px;
            border-radius: 20px;
            background: var(--code);
            color: #f9edf8;
            border: 1px solid var(--code-border);
            overflow: auto;
            font: 500 0.92rem/1.65 Consolas, "Courier New", monospace;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .alert {
            padding: 16px 18px;
            border-radius: 18px;
            font-weight: 600;
        }

        .alert--danger {
            background: rgba(185, 63, 63, 0.1);
            color: var(--danger);
            border: 1px solid rgba(185, 63, 63, 0.18);
        }

        .hint {
            font-size: 0.92rem;
        }

        @media (max-width: 720px) {
            .shell {
                width: min(100% - 18px, 100%);
                margin: 12px auto 24px;
            }

            .hero,
            .panel {
                border-radius: 22px;
                padding: 18px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .button {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <span class="eyebrow">Sandbox</span>
            <h1>Homologacao PagBank no site</h1>
            <p>Esta pagina gera um checkout real em Sandbox a partir do dominio da loja, salva o log com <strong>Request</strong> e <strong>Response</strong> e libera o arquivo para voce anexar no e-mail de homologacao.</p>
            <form method="post" class="actions">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <button class="button button--primary" type="submit">Gerar checkout Sandbox</button>
                <a class="button button--soft" href="<?= e(app_url()); ?>">Voltar para a loja</a>
            </form>
            <p class="hint">O fluxo usa o endpoint <code>/checkouts</code> em Sandbox com <code>CREDIT_CARD</code>, <code>DEBIT_CARD</code> e <code>PIX</code>.</p>
        </section>

        <?php if ($errorMessage !== ''): ?>
            <section class="panel">
                <div class="alert alert--danger"><?= e($errorMessage); ?></div>
            </section>
        <?php endif; ?>

        <?php if ($result !== null): ?>
            <section class="panel">
                <h2>Resultado</h2>
                <div class="status-grid">
                    <article class="status-card">
                        <span class="status-card__label">HTTP</span>
                        <span class="status-card__value <?= !empty($result['ok']) ? 'status-card__value--success' : 'status-card__value--danger'; ?>">
                            <?= e((string) ($result['status'] ?? 0)); ?>
                        </span>
                    </article>
                    <article class="status-card">
                        <span class="status-card__label">Reference ID</span>
                        <span class="status-card__value"><?= e((string) ($result['reference_id'] ?? '')); ?></span>
                    </article>
                    <article class="status-card">
                        <span class="status-card__label">Checkout ID</span>
                        <span class="status-card__value"><?= e((string) ($result['checkout_id'] ?? '')); ?></span>
                    </article>
                    <article class="status-card">
                        <span class="status-card__label">Arquivo</span>
                        <span class="status-card__value"><?= e((string) ($result['log_name'] ?? '')); ?></span>
                    </article>
                </div>

                <div class="actions">
                    <a class="button button--primary" href="<?= e(app_url('pagbank-homologacao.php?download=' . rawurlencode((string) ($result['log_name'] ?? '')))); ?>">Baixar log .txt</a>
                    <?php if (trim((string) ($result['pay_url'] ?? '')) !== ''): ?>
                        <a class="button button--soft" href="<?= e((string) $result['pay_url']); ?>" target="_blank" rel="noopener noreferrer">Abrir checkout Sandbox</a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel">
                <h2>Request</h2>
                <pre class="code-block"><?= e((string) $requestJson); ?></pre>
            </section>

            <section class="panel">
                <h2>Response</h2>
                <pre class="code-block"><?= e((string) $responseJson); ?></pre>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
