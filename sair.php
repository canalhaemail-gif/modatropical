<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

logout_customer();
set_flash('success', 'Voce saiu da sua conta.');
redirect('index.php');
