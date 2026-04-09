<?php
declare(strict_types=1);

function ensure_upload_directories(): void
{
    $directories = [
        BASE_PATH . '/uploads',
        BASE_PATH . '/uploads/brands',
        BASE_PATH . '/uploads/products',
        BASE_PATH . '/uploads/store',
        BASE_PATH . '/storage',
        BASE_PATH . '/storage/apple',
        BASE_PATH . '/storage/google',
        BASE_PATH . '/storage/mail',
    ];

    foreach ($directories as $directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }
}

function app_url(string $path = ''): string
{
    $baseUrl = rtrim(APP_URL, '/');

    if ($path === '') {
        return $baseUrl !== '' ? $baseUrl : '/';
    }

    return ($baseUrl !== '' ? $baseUrl : '') . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    $normalizedPath = ltrim($path, '/');
    $absolutePath = BASE_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    $url = app_url($normalizedPath);

    if (!is_file($absolutePath)) {
        return $url;
    }

    $version = filemtime($absolutePath);

    if ($version === false) {
        return $url;
    }

    return $url . '?v=' . rawurlencode((string) $version);
}

function critical_image_relative_path(?string $path): ?string
{
    $path = trim((string) $path);

    if ($path === '') {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $path) === 1) {
        $parsedPath = (string) parse_url($path, PHP_URL_PATH);
        $basePath = (string) parse_url(app_url(), PHP_URL_PATH);

        $path = $parsedPath;

        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath) + 1);
        }
    }

    $path = str_replace('\\', '/', $path);

    if (str_starts_with($path, str_replace('\\', '/', BASE_PATH) . '/')) {
        $path = substr($path, strlen(str_replace('\\', '/', BASE_PATH)) + 1);
    }

    $path = ltrim(rawurldecode((string) parse_url($path, PHP_URL_PATH) ?: $path), '/');

    if ($path === '' || str_contains($path, "\0")) {
        return null;
    }

    $segments = array_values(array_filter(
        explode('/', $path),
        static fn(string $segment): bool => $segment !== '' && $segment !== '.'
    ));

    if ($segments === []) {
        return null;
    }

    foreach ($segments as $segment) {
        if ($segment === '..') {
            return null;
        }
    }

    $normalizedPath = implode('/', $segments);
    $normalizedPath = preg_replace('#/+#', '/', $normalizedPath) ?? $normalizedPath;
    $normalizedPath = ltrim($normalizedPath, '/');

    $allowedRootFiles = [
        'logo.png',
        'bemvindo.png',
        'destaques.png',
        'mega.png',
        'cupons.png',
        'blusinhas.png',
        'macacoes.png',
        'inverno.png',
        'calÃ§as.png',
        'calcas.png',
    ];
    $allowedPrefixes = [
        'assets/img/',
        'uploads/',
    ];

    $allowed = in_array($normalizedPath, $allowedRootFiles, true);

    if (!$allowed) {
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($normalizedPath, $prefix)) {
                $allowed = true;
                break;
            }
        }
    }

    if (!$allowed) {
        return null;
    }

    $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif'];

    if (!in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    $absolutePath = BASE_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

    return is_file($absolutePath) ? $normalizedPath : null;
}

function critical_image_url(string $path): string
{
    $normalizedPath = critical_image_relative_path($path);

    if ($normalizedPath === null) {
        return asset_url($path);
    }

    $absolutePath = BASE_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    $version = is_file($absolutePath) ? (int) (filemtime($absolutePath) ?: 0) : 0;
    $query = 'src=' . rawurlencode($normalizedPath);

    if ($version > 0) {
        $query .= '&v=' . rawurlencode((string) $version);
    }

    return app_url('imagem-critica.php?' . $query);
}

function critical_image_preload_entries(array $candidates): array
{
    $entries = [];
    $seen = [];

    foreach ($candidates as $candidate) {
        $path = '';
        $media = null;

        if (is_array($candidate)) {
            $path = (string) ($candidate['path'] ?? '');
            $media = isset($candidate['media']) ? trim((string) $candidate['media']) : null;
            $media = $media !== '' ? $media : null;
        } else {
            $path = (string) $candidate;
        }

        $normalizedPath = critical_image_relative_path($path);

        if ($normalizedPath === null) {
            continue;
        }

        $key = $normalizedPath . '|' . ($media ?? '');

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $entries[] = [
            'path' => $normalizedPath,
            'url' => critical_image_url($normalizedPath),
            'media' => $media,
        ];
    }

    return $entries;
}

function inline_asset_data_uri(string $path): ?string
{
    $normalizedPath = critical_image_relative_path($path);

    if ($normalizedPath === null) {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
    }

    if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
        return null;
    }

    $absolutePath = BASE_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        return null;
    }

    $fileSize = (int) (filesize($absolutePath) ?: 0);

    if ($fileSize <= 0 || $fileSize > 262144) {
        return null;
    }

    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'avif' => 'image/avif',
    ];
    $mimeType = $mimeTypes[$extension] ?? null;

    if ($mimeType === null) {
        return null;
    }

    $contents = file_get_contents($absolutePath);

    if ($contents === false || $contents === '') {
        return null;
    }

    return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
}

function preferred_critical_image_render_url(string $path, array $inlineCandidates = []): string
{
    $candidates = [];

    foreach ($inlineCandidates as $candidate) {
        $candidate = trim((string) $candidate);

        if ($candidate === '') {
            continue;
        }

        $candidates[] = $candidate;
    }

    $candidates[] = $path;
    $seen = [];

    foreach ($candidates as $candidate) {
        if (isset($seen[$candidate])) {
            continue;
        }

        $seen[$candidate] = true;
        $inlineDataUri = inline_asset_data_uri($candidate);

        if ($inlineDataUri !== null) {
            return $inlineDataUri;
        }
    }

    return critical_image_url($path);
}

function redirect(string $path): never
{
    $location = trim($path);

    if ($location === '') {
        $location = app_url();
    } elseif (
        preg_match('/^https?:\/\//i', $location) !== 1
        && !str_starts_with($location, '/')
    ) {
        $location = app_url($location);
    }

    header('Location: ' . $location);
    exit;
}

function current_app_path_with_query(): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $appBase = app_url();

    if ($requestUri === '') {
        return '';
    }

    if (str_starts_with($requestUri, $appBase)) {
        return ltrim(substr($requestUri, strlen($appBase)), '/');
    }

    return ltrim($requestUri, '/');
}

function configured_public_base_url(): string
{
    if (!defined('PAGBANK_PUBLIC_BASE_URL')) {
        return '';
    }

    return rtrim(trim((string) PAGBANK_PUBLIC_BASE_URL), '/');
}

function request_host(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));

    return $host !== '' ? $host : 'localhost';
}

function request_uses_https(): bool
{
    $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));

    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }

    $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    if ($forwardedProto !== '') {
        $proto = strtolower(trim(explode(',', $forwardedProto)[0]));

        if ($proto === 'https') {
            return true;
        }
    }

    $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));

    if (in_array($forwardedSsl, ['on', '1', 'https'], true)) {
        return true;
    }

    $requestScheme = strtolower(trim((string) ($_SERVER['REQUEST_SCHEME'] ?? '')));

    if ($requestScheme === 'https') {
        return true;
    }

    $cfVisitor = strtolower((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''));

    if ($cfVisitor !== '' && str_contains($cfVisitor, '"scheme":"https"')) {
        return true;
    }

    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function host_is_local(string $host): bool
{
    $host = strtolower(trim($host));

    return $host === ''
        || str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1');
}

function absolute_app_url(string $path = ''): string
{
    $host = request_host();
    $configuredBase = configured_public_base_url();

    if ($configuredBase !== '') {
        if ($path === '') {
            return $configuredBase;
        }

        return $configuredBase . '/' . ltrim($path, '/');
    }

    $scheme = request_uses_https() ? 'https' : 'http';

    return $scheme . '://' . $host . app_url($path);
}

function db_future_datetime(int $secondsFromNow = 0): string
{
    $secondsFromNow = max(0, $secondsFromNow);
    $query = $secondsFromNow === 0
        ? 'SELECT NOW()'
        : 'SELECT DATE_ADD(NOW(), INTERVAL ' . $secondsFromNow . ' SECOND)';

    $value = db()->query($query)->fetchColumn();

    if (is_string($value) && trim($value) !== '') {
        return $value;
    }

    return date('Y-m-d H:i:s', time() + $secondsFromNow);
}

function table_exists(string $table): bool
{
    $statement = db()->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table
         LIMIT 1'
    );
    $statement->execute(['table' => $table]);

    return (bool) $statement->fetchColumn();
}

function table_column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = strtolower(trim($table)) . '.' . strtolower(trim($column));

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = db()->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column
         LIMIT 1'
    );
    $statement->execute([
        'table' => $table,
        'column' => $column,
    ]);

    $cache[$key] = (bool) $statement->fetchColumn();

    return $cache[$key];
}

function flavor_tables_available(): bool
{
    static $available = null;

    if (is_bool($available)) {
        return $available;
    }

    $available = table_exists('sabores') && table_exists('produto_sabores');

    return $available;
}

function product_gallery_table_available(): bool
{
    static $available = null;

    if (is_bool($available)) {
        return $available;
    }

    $available = table_exists('produto_imagens');

    return $available;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return $messages;
}

function pull_session_value(string $key, mixed $default = null): mixed
{
    $value = $_SESSION[$key] ?? $default;
    unset($_SESSION[$key]);

    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function posted_value(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function checked(mixed $value, mixed $expected = 1): string
{
    return (string) $value === (string) $expected ? 'checked' : '';
}

function selected(mixed $value, mixed $expected): string
{
    return (string) $value === (string) $expected ? 'selected' : '';
}

function slugify(string $text): string
{
    $text = trim($text);

    if ($text === '') {
        return 'item-' . bin2hex(random_bytes(3));
    }

    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $normalized = $normalized !== false ? $normalized : $text;
    $normalized = strtolower($normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'item-' . bin2hex(random_bytes(3));
}

function generate_unique_slug(string $table, string $text, ?int $ignoreId = null): string
{
    $allowedTables = ['categorias', 'marcas', 'produtos', 'sabores'];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Tabela invalida para geracao de slug.');
    }

    $baseSlug = slugify($text);
    $slug = $baseSlug;
    $suffix = 2;

    while (true) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        $statement = db()->prepare($sql);
        $statement->execute($params);

        if ((int) $statement->fetchColumn() === 0) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function format_currency(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function format_datetime_br(?string $value): string
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return '';
    }

    $timestamp = strtotime($raw);

    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y H:i', $timestamp);
}

function format_date_br(?string $value): string
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return '';
    }

    $timestamp = strtotime($raw);

    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y', $timestamp);
}

function normalize_money_input(string $value): float
{
    $value = trim($value);
    $value = str_replace(['R$', ' '], '', $value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? (float) $value : 0.0;
}

function normalize_percentage_input(mixed $value): float
{
    $raw = trim((string) $value);
    $raw = str_replace('%', '', $raw);
    $raw = str_replace(' ', '', $raw);
    $raw = str_replace(',', '.', $raw);

    if (!is_numeric($raw)) {
        return 0.0;
    }

    return max(0.0, min(100.0, (float) $raw));
}

function digits_only(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function normalize_email(?string $value): string
{
    return trim(strtolower((string) $value));
}

function normalize_person_name(?string $value): string
{
    $value = trim((string) $value);

    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function is_valid_customer_name(string $name): bool
{
    $normalized = normalize_person_name($name);

    if (strlen($normalized) < 5) {
        return false;
    }

    if (!preg_match("/^[\\p{L}][\\p{L}\\s'\\-]+$/u", $normalized)) {
        return false;
    }

    $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    return count($parts) >= 2;
}

function is_strong_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
}

function password_rule_message(): string
{
    return 'A senha precisa ter pelo menos 8 caracteres, uma letra maiuscula e um caractere especial.';
}

function normalize_cpf(?string $value): string
{
    return digits_only($value);
}

function normalize_cep(?string $value): string
{
    return digits_only($value);
}

function is_valid_cep(string $cep): bool
{
    return preg_match('/^\d{8}$/', normalize_cep($cep)) === 1;
}

function format_cep(?string $value): string
{
    $cep = normalize_cep($value);

    if (strlen($cep) !== 8) {
        return $cep;
    }

    return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
}

function format_cpf(?string $value): string
{
    $cpf = normalize_cpf($value);

    if (strlen($cpf) !== 11) {
        return $cpf;
    }

    return substr($cpf, 0, 3) . '.'
        . substr($cpf, 3, 3) . '.'
        . substr($cpf, 6, 3) . '-'
        . substr($cpf, 9, 2);
}

function format_phone(?string $value): string
{
    $phone = digits_only($value);

    if (strlen($phone) === 11) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7, 4);
    }

    if (strlen($phone) === 10) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6, 4);
    }

    return $phone;
}

function parse_product_flavor_entries(mixed $value): array
{
    if (is_array($value)) {
        $entries = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $entries[] = [
                    'nome' => trim((string) ($item['nome'] ?? '')),
                    'estoque' => max(0, (int) ($item['estoque'] ?? 0)),
                ];
                continue;
            }

            if (is_string($item)) {
                $entries[] = [
                    'nome' => trim($item),
                    'estoque' => 0,
                ];
            }
        }

        $value = json_encode($entries, JSON_UNESCAPED_UNICODE);
    }

    $raw = trim((string) $value);

    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    $items = [];

    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = [
                'nome' => trim((string) ($item['nome'] ?? '')),
                'estoque' => max(0, (int) ($item['estoque'] ?? 0)),
            ];
        }
    } else {
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $chunks = preg_split('/[\n,;]+/u', $normalized) ?: [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            [$name, $stockText] = array_pad(explode('|', $chunk, 2), 2, '');

            $items[] = [
                'nome' => trim($name),
                'estoque' => max(0, (int) trim($stockText)),
            ];
        }
    }

    $normalizedEntries = [];

    foreach ($items as $item) {
        $name = trim((string) ($item['nome'] ?? ''));

        if ($name === '') {
            continue;
        }

        $key = function_exists('mb_strtolower')
            ? mb_strtolower($name, 'UTF-8')
            : strtolower($name);

        if (isset($normalizedEntries[$key])) {
            $normalizedEntries[$key]['estoque'] += max(0, (int) ($item['estoque'] ?? 0));
            continue;
        }

        $normalizedEntries[$key] = [
            'nome' => $name,
            'estoque' => max(0, (int) ($item['estoque'] ?? 0)),
        ];
    }

    return array_values($normalizedEntries);
}

function parse_product_flavors(?string $value): array
{
    return array_values(array_map(
        static fn(array $entry): string => (string) ($entry['nome'] ?? ''),
        parse_product_flavor_entries($value)
    ));
}

function serialize_product_flavor_entries(array $entries): string
{
    $normalizedEntries = [];

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $name = trim((string) ($entry['nome'] ?? ''));

        if ($name === '') {
            continue;
        }

        $normalizedEntries[] = [
            'nome' => $name,
            'estoque' => max(0, (int) ($entry['estoque'] ?? 0)),
        ];
    }

    return json_encode($normalizedEntries, JSON_UNESCAPED_UNICODE) ?: '[]';
}

function calculate_product_flavor_stock(array $entries): int
{
    $total = 0;

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $total += max(0, (int) ($entry['estoque'] ?? 0));
    }

    return $total;
}

function find_flavor(int $id): ?array
{
    if (!flavor_tables_available()) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM sabores WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $flavor = $statement->fetch();

    return $flavor ?: null;
}

function fetch_flavors(bool $includeInactive = true): array
{
    if (!flavor_tables_available()) {
        return [];
    }

    $sql = 'SELECT * FROM sabores';

    if (!$includeInactive) {
        $sql .= ' WHERE ativo = 1';
    }

    $sql .= ' ORDER BY ordem ASC, nome ASC';

    return db()->query($sql)->fetchAll();
}

function fetch_product_flavor_ids(int $productId): array
{
    if (!flavor_tables_available()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT sabor_id
         FROM produto_sabores
         WHERE produto_id = :produto_id
         ORDER BY sabor_id ASC'
    );
    $statement->execute(['produto_id' => $productId]);

    return array_map(
        static fn(mixed $value): int => (int) $value,
        $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
    );
}

function fetch_product_flavors(int $productId, bool $activeOnly = true): array
{
    if (!flavor_tables_available()) {
        return [];
    }

    $sql = 'SELECT s.*, ps.estoque AS estoque
            FROM sabores s
            INNER JOIN produto_sabores ps ON ps.sabor_id = s.id
            WHERE ps.produto_id = :produto_id';

    if ($activeOnly) {
        $sql .= ' AND s.ativo = 1';
    }

    $sql .= ' ORDER BY s.ordem ASC, s.nome ASC';

    $statement = db()->prepare($sql);
    $statement->execute(['produto_id' => $productId]);

    return $statement->fetchAll();
}

function ensure_flavor_records_for_entries(array $entries): array
{
    if (!flavor_tables_available()) {
        return [];
    }

    $resolvedEntries = [];
    $selectStatement = db()->prepare('SELECT id FROM sabores WHERE LOWER(nome) = LOWER(:nome) LIMIT 1');
    $insertStatement = db()->prepare(
        'INSERT INTO sabores (nome, slug, ordem, ativo)
         VALUES (:nome, :slug, :ordem, 1)'
    );
    $orderStatement = db()->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM sabores');

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $name = trim((string) ($entry['nome'] ?? ''));
        $stock = max(0, (int) ($entry['estoque'] ?? 0));

        if ($name === '') {
            continue;
        }

        $selectStatement->execute(['nome' => $name]);
        $existingId = $selectStatement->fetchColumn();

        if ($existingId) {
            $resolvedEntries[] = [
                'id' => (int) $existingId,
                'nome' => $name,
                'estoque' => $stock,
            ];
            continue;
        }

        $orderStatement->execute();
        $nextOrder = (int) $orderStatement->fetchColumn();

        $insertStatement->execute([
            'nome' => $name,
            'slug' => generate_unique_slug('sabores', $name),
            'ordem' => $nextOrder,
        ]);

        $resolvedEntries[] = [
            'id' => (int) db()->lastInsertId(),
            'nome' => $name,
            'estoque' => $stock,
        ];
    }

    return $resolvedEntries;
}

function ensure_flavor_ids_for_names(array $flavors): array
{
    return array_values(array_map(
        static fn(array $entry): int => (int) ($entry['id'] ?? 0),
        ensure_flavor_records_for_entries(array_map(
            static fn(mixed $flavor): array => [
                'nome' => trim((string) $flavor),
                'estoque' => 0,
            ],
            $flavors
        ))
    ));
}

function sync_product_flavor_cache(int $productId): void
{
    if (!flavor_tables_available()) {
        return;
    }

    $flavorNames = array_map(
        static fn(array $flavor): string => (string) ($flavor['nome'] ?? ''),
        array_values(array_filter(
            fetch_product_flavors($productId, false),
            static fn(array $flavor): bool => max(0, (int) ($flavor['estoque'] ?? 0)) > 0
        ))
    );
    $flavorNames = array_values(array_filter($flavorNames, static fn(string $name): bool => trim($name) !== ''));
    $cachedFlavors = $flavorNames !== [] ? implode("\n", $flavorNames) : null;

    $statement = db()->prepare('UPDATE produtos SET sabores = :sabores WHERE id = :id');
    $statement->execute([
        'sabores' => $cachedFlavors,
        'id' => $productId,
    ]);
}

function sync_product_flavors(int $productId, array $flavorIds): void
{
    if (!flavor_tables_available()) {
        return;
    }

    $normalizedIds = array_values(array_unique(array_filter(
        array_map(static fn(mixed $value): int => (int) $value, $flavorIds),
        static fn(int $value): bool => $value > 0
    )));

    $deleteStatement = db()->prepare('DELETE FROM produto_sabores WHERE produto_id = :produto_id');
    $deleteStatement->execute(['produto_id' => $productId]);

    if ($normalizedIds !== []) {
        $insertStatement = db()->prepare(
            'INSERT INTO produto_sabores (produto_id, sabor_id, estoque)
             VALUES (:produto_id, :sabor_id, 0)'
        );

        foreach ($normalizedIds as $flavorId) {
            $insertStatement->execute([
                'produto_id' => $productId,
                'sabor_id' => $flavorId,
            ]);
        }
    }

    sync_product_flavor_cache($productId);
}

function sync_product_flavor_entries(int $productId, array $entries): void
{
    if (!flavor_tables_available()) {
        return;
    }

    $normalizedEntries = array_values(array_filter(
        ensure_flavor_records_for_entries($entries),
        static fn(array $entry): bool => trim((string) ($entry['nome'] ?? '')) !== ''
    ));

    $deleteStatement = db()->prepare('DELETE FROM produto_sabores WHERE produto_id = :produto_id');
    $deleteStatement->execute(['produto_id' => $productId]);

    if ($normalizedEntries !== []) {
        $insertStatement = db()->prepare(
            'INSERT INTO produto_sabores (produto_id, sabor_id, estoque)
             VALUES (:produto_id, :sabor_id, :estoque)'
        );

        foreach ($normalizedEntries as $entry) {
            $insertStatement->execute([
                'produto_id' => $productId,
                'sabor_id' => (int) $entry['id'],
                'estoque' => max(0, (int) ($entry['estoque'] ?? 0)),
            ]);
        }
    }

    sync_product_flavor_cache($productId);
}

function fetch_product_gallery_images(int $productId): array
{
    if (!product_gallery_table_available()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT *
         FROM produto_imagens
         WHERE produto_id = :produto_id
         ORDER BY ordem ASC, id ASC'
    );
    $statement->execute(['produto_id' => $productId]);

    return $statement->fetchAll();
}

function remove_product_gallery_images(int $productId, array $imageIds): void
{
    if (!product_gallery_table_available()) {
        return;
    }

    $normalizedIds = array_values(array_unique(array_filter(
        array_map(static fn(mixed $value): int => (int) $value, $imageIds),
        static fn(int $value): bool => $value > 0
    )));

    if ($normalizedIds === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
    $params = array_merge([$productId], $normalizedIds);
    $statement = db()->prepare(
        "SELECT id, imagem
         FROM produto_imagens
         WHERE produto_id = ?
           AND id IN ({$placeholders})"
    );
    $statement->execute($params);
    $images = $statement->fetchAll();

    foreach ($images as $image) {
        delete_uploaded_file($image['imagem'] ?? null);
    }

    $deleteStatement = db()->prepare(
        "DELETE FROM produto_imagens
         WHERE produto_id = ?
           AND id IN ({$placeholders})"
    );
    $deleteStatement->execute($params);
}

function delete_product_gallery_files(int $productId): void
{
    if (!product_gallery_table_available()) {
        return;
    }

    $images = fetch_product_gallery_images($productId);

    foreach ($images as $image) {
        delete_uploaded_file($image['imagem'] ?? null);
    }
}

function save_product_gallery_uploads(int $productId, string $fieldName = 'galeria'): void
{
    if (!product_gallery_table_available()) {
        return;
    }

    if (
        !isset($_FILES[$fieldName])
        || !is_array($_FILES[$fieldName]['name'] ?? null)
        || ($_FILES[$fieldName]['name'] ?? []) === []
    ) {
        return;
    }

    $orderStatement = db()->prepare('SELECT COALESCE(MAX(ordem), 0) FROM produto_imagens WHERE produto_id = :produto_id');
    $orderStatement->execute(['produto_id' => $productId]);
    $nextOrder = (int) $orderStatement->fetchColumn();

    $names = $_FILES[$fieldName]['name'] ?? [];
    $tmpNames = $_FILES[$fieldName]['tmp_name'] ?? [];
    $errors = $_FILES[$fieldName]['error'] ?? [];

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $insertStatement = db()->prepare(
        'INSERT INTO produto_imagens (produto_id, imagem, ordem)
         VALUES (:produto_id, :imagem, :ordem)'
    );

    foreach ($names as $index => $name) {
        $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE || trim((string) $name) === '') {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nao foi possivel enviar uma das imagens da galeria.');
        }

        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Envie apenas imagens JPG, PNG ou WEBP na galeria.');
        }

        $tmpName = (string) ($tmpNames[$index] ?? '');
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('Uma imagem da galeria nao e valida.');
        }

        $targetDirectory = BASE_PATH . '/uploads/products';

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $fileName = 'products-gallery-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetFile = $targetDirectory . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $targetFile)) {
            throw new RuntimeException('Falha ao mover uma imagem da galeria.');
        }

        $nextOrder++;

        $insertStatement->execute([
            'produto_id' => $productId,
            'imagem' => 'uploads/products/' . $fileName,
            'ordem' => $nextOrder,
        ]);
    }
}

function is_valid_cpf(string $cpf): bool
{
    $cpf = normalize_cpf($cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
        return false;
    }

    for ($digit = 9; $digit < 11; $digit++) {
        $sum = 0;

        for ($index = 0; $index < $digit; $index++) {
            $sum += (int) $cpf[$index] * (($digit + 1) - $index);
        }

        $checkDigit = ((10 * $sum) % 11) % 10;

        if ($checkDigit !== (int) $cpf[$digit]) {
            return false;
        }
    }

    return true;
}

function is_valid_birth_date(?string $value): bool
{
    $value = trim((string) $value);

    if ($value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$date
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
    ) {
        return false;
    }

    $today = new DateTimeImmutable('today');
    $minimum = new DateTimeImmutable('1900-01-01');

    return $date <= $today && $date >= $minimum;
}

function format_birth_date(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '' || !is_valid_birth_date($value)) {
        return '';
    }

    return date('d/m/Y', strtotime($value));
}

function is_local_environment(): bool
{
    return host_is_local(request_host());
}

function whatsapp_link(?string $phone, string $message = ''): string
{
    $phone = digits_only($phone);

    if ($phone === '') {
        return '#';
    }

    $query = $message !== '' ? '?text=' . rawurlencode($message) : '';

    return 'https://wa.me/' . $phone . $query;
}

function fetch_store_settings(): array
{
    static $settings = null;

    if (is_array($settings)) {
        return $settings;
    }

    $statement = db()->query('SELECT * FROM configuracoes ORDER BY id ASC LIMIT 1');
    $settings = $statement->fetch() ?: [
        'nome_estabelecimento' => 'Sua Loja',
        'descricao_loja' => 'Atualize as configuracoes da loja no painel administrativo.',
        'logo' => '',
        'telefone_whatsapp' => '',
        'endereco' => '',
        'horario_funcionamento' => '',
        'cor_primaria' => '#D97A6C',
        'cor_secundaria' => '#97B39B',
    ];

    return $settings;
}

function store_setting(string $key, string $default = ''): string
{
    $settings = fetch_store_settings();

    return isset($settings[$key]) && $settings[$key] !== '' ? (string) $settings[$key] : $default;
}

function delete_uploaded_file(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) {
        return;
    }

    $absolutePath = BASE_PATH . '/' . ltrim($path, '/');

    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

function handle_image_upload(string $fieldName, string $folder, ?string $currentPath = null): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $currentPath;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nao foi possivel enviar a imagem.');
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $extension = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Envie apenas imagens JPG, PNG ou WEBP.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$fieldName]['tmp_name']);

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        throw new RuntimeException('O arquivo enviado nao e uma imagem valida.');
    }

    $targetDirectory = BASE_PATH . '/uploads/' . trim($folder, '/');
    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('Nao foi possivel preparar a pasta de upload.');
    }

    if (!is_writable($targetDirectory)) {
        @chmod($targetDirectory, 0775);
    }

    if (!is_writable($targetDirectory)) {
        throw new RuntimeException('A pasta de upload nao esta com permissao de escrita.');
    }

    $fileName = trim($folder, '/') . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetFile = $targetDirectory . '/' . $fileName;

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetFile)) {
        throw new RuntimeException('Falha ao mover a imagem enviada.');
    }

    if ($currentPath && $currentPath !== $fileName) {
        delete_uploaded_file($currentPath);
    }

    return 'uploads/' . trim($folder, '/') . '/' . $fileName;
}

function category_exists_by_slug(string $slug, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM categorias WHERE slug = :slug';
    $params = ['slug' => $slug];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function brand_exists_by_slug(string $slug, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM marcas WHERE slug = :slug';
    $params = ['slug' => $slug];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function product_exists_by_slug(string $slug, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM produtos WHERE slug = :slug';
    $params = ['slug' => $slug];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function find_category(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM categorias WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $category = $statement->fetch();

    return $category ?: null;
}

function find_brand(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM marcas WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $brand = $statement->fetch();

    return $brand ?: null;
}

function find_product(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM produtos WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();

    return $product ?: null;
}

function product_discount_percent(array $product): float
{
    return normalize_percentage_input($product['desconto_percentual'] ?? 0);
}

function product_has_discount(array $product): bool
{
    return product_discount_percent($product) > 0;
}

function product_original_price(array $product): float
{
    return max(0.0, (float) ($product['preco'] ?? 0));
}

function product_final_price(array $product): float
{
    $originalPrice = product_original_price($product);
    $discountPercent = product_discount_percent($product);

    if ($discountPercent <= 0) {
        return $originalPrice;
    }

    return round($originalPrice * (1 - ($discountPercent / 100)), 2);
}

function product_discount_amount(array $product): float
{
    return max(0.0, product_original_price($product) - product_final_price($product));
}

function product_discount_badge_label(array $product): string
{
    $percent = product_discount_percent($product);

    if ($percent <= 0) {
        return '';
    }

    $formatted = rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',');
    return '-' . $formatted . '%';
}

function admin_nav_active(string $currentPage, string $expectedPage): string
{
    return $currentPage === $expectedPage ? 'is-active' : '';
}
