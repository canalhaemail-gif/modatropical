<?php
declare(strict_types=1);

defined('APPLE_LOGIN_ENABLED') || define('APPLE_LOGIN_ENABLED', false);
defined('APPLE_TEAM_ID') || define('APPLE_TEAM_ID', '');
defined('APPLE_CLIENT_ID') || define('APPLE_CLIENT_ID', '');
defined('APPLE_KEY_ID') || define('APPLE_KEY_ID', '');
defined('APPLE_PRIVATE_KEY') || define('APPLE_PRIVATE_KEY', '');
defined('APPLE_PRIVATE_KEY_PATH') || define('APPLE_PRIVATE_KEY_PATH', BASE_PATH . '/storage/apple/AuthKey.p8');
defined('APPLE_KEYS_URL') || define('APPLE_KEYS_URL', 'https://appleid.apple.com/auth/keys');
defined('APPLE_KEYS_CACHE_FILE') || define('APPLE_KEYS_CACHE_FILE', BASE_PATH . '/storage/apple/apple_keys_cache.json');
defined('APPLE_KEYS_CACHE_TTL') || define('APPLE_KEYS_CACHE_TTL', 3600);
