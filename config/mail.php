<?php
declare(strict_types=1);

defined('MAIL_DRIVER') || define('MAIL_DRIVER', 'smtp');
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', 'Moda Tropical');
defined('MAIL_FROM_ADDRESS') || define('MAIL_FROM_ADDRESS', 'modatropycal@gmail.com');
defined('MAIL_REPLY_TO_ADDRESS') || define('MAIL_REPLY_TO_ADDRESS', MAIL_FROM_ADDRESS);
defined('MAIL_REPLY_TO_NAME') || define('MAIL_REPLY_TO_NAME', MAIL_FROM_NAME);
defined('MAIL_SMTP_HOST') || define('MAIL_SMTP_HOST', 'smtp.gmail.com');
defined('MAIL_SMTP_PORT') || define('MAIL_SMTP_PORT', 465);
defined('MAIL_SMTP_SECURITY') || define('MAIL_SMTP_SECURITY', 'ssl');
defined('MAIL_SMTP_USERNAME') || define('MAIL_SMTP_USERNAME', 'modatropycal@gmail.com');
defined('MAIL_SMTP_PASSWORD') || define('MAIL_SMTP_PASSWORD', 'zdfaormvfpbakykl');
defined('MAIL_SMTP_TIMEOUT') || define('MAIL_SMTP_TIMEOUT', 20);
defined('MAIL_SMTP_EHLO') || define('MAIL_SMTP_EHLO', 'modatropical.store');
defined('MAIL_FALLBACK_TO_LOG') || define('MAIL_FALLBACK_TO_LOG', true);
defined('MAIL_LOG_DIRECTORY') || define('MAIL_LOG_DIRECTORY', BASE_PATH . '/storage/mail');
