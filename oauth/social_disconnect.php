<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_customer_auth();

if (!is_post() || !verify_csrf_token(posted_value('csrf_token'))) {
    set_flash('error', 'Sessao expirada. Tente novamente.');
    redirect('minha-conta.php#contas-conectadas');
}

$provider = trim(strtolower((string) posted_value('provider')));
$customerId = (int) (current_customer()['id'] ?? 0);

if (!social_provider_exists($provider) || !find_customer_identity_for_customer($customerId, $provider)) {
    set_flash('error', 'Esse vinculo nao foi encontrado.');
    redirect('minha-conta.php#contas-conectadas');
}

if (!customer_can_disconnect_social_identity($customerId, $provider)) {
    set_flash('error', 'Mantenha pelo menos outra forma de acesso antes de remover este vinculo.');
    redirect('minha-conta.php#contas-conectadas');
}

disconnect_customer_social_identity($customerId, $provider);
set_flash('success', social_provider_label($provider) . ' desvinculado com sucesso.');
redirect('minha-conta.php#contas-conectadas');
