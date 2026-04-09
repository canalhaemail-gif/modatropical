<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

try {
    $result = pagbank_run_checkout_homologation();
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "Arquivo gerado: " . (string) ($result['log_file'] ?? '') . "\n");
fwrite(STDOUT, "HTTP status: " . (string) ($result['status'] ?? 0) . "\n");

if (!empty($result['ok'])) {
    fwrite(STDOUT, "Homologacao Sandbox criada com sucesso.\n");
    exit(0);
}

fwrite(STDERR, "Falha ao criar checkout em Sandbox: " . (string) (($result['response']['error_message'] ?? 'erro desconhecido')) . "\n");
exit(2);
