<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

logout_admin();
set_flash('success', 'Sessao encerrada.');
redirect('admin/login.php');
