<?php
declare(strict_types=1);

defined('GOOGLE_LOGIN_ENABLED') || define('GOOGLE_LOGIN_ENABLED', true);
defined('GOOGLE_CLIENT_ID') || define('GOOGLE_CLIENT_ID', '1082435536917-ced54g9k7utaahdtc41ejf6fdtfpglef.apps.googleusercontent.com');
defined('GOOGLE_CERTS_URL') || define('GOOGLE_CERTS_URL', 'https://www.googleapis.com/oauth2/v1/certs');
defined('GOOGLE_CERTS_CACHE_FILE') || define('GOOGLE_CERTS_CACHE_FILE', BASE_PATH . '/storage/google/google_certs_cache.json');
defined('GOOGLE_CERTS_CACHE_TTL') || define('GOOGLE_CERTS_CACHE_TTL', 3600);
