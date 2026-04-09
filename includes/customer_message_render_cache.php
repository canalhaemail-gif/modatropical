<?php
declare(strict_types=1);

const CUSTOMER_MESSAGE_RENDER_CACHE_NORMALIZER_VERSION = 'cm_scene_norm_v1';

function customer_message_render_cache_token_names_from_string(?string $value): array
{
    $value = (string) $value;

    if ($value === '') {
        return [];
    }

    preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/', $value, $matches);
    $rawNames = isset($matches[1]) && is_array($matches[1]) ? $matches[1] : [];
    $names = [];

    foreach ($rawNames as $name) {
        $normalized = trim((string) $name);
        if ($normalized === '') {
            continue;
        }
        $names[$normalized] = true;
    }

    return array_keys($names);
}

function customer_message_render_cache_visual_token_names(array $scene): array
{
    $scene = customer_message_scene_normalize($scene);
    $names = [];
    $backgroundImage = (string) ($scene['canvas']['backgroundImage'] ?? '');

    foreach (customer_message_render_cache_token_names_from_string($backgroundImage) as $tokenName) {
        $names[$tokenName] = true;
    }

    foreach ((array) ($scene['layers'] ?? []) as $layer) {
        if (!is_array($layer) || ($layer['visible'] ?? true) === false) {
            continue;
        }

        $type = (string) ($layer['type'] ?? '');
        if (!in_array($type, ['text', 'button', 'hotspot'], true)) {
            continue;
        }

        foreach (customer_message_render_cache_token_names_from_string((string) ($layer['textRaw'] ?? '')) as $tokenName) {
            $names[$tokenName] = true;
        }

        foreach (customer_message_render_cache_token_names_from_string((string) ($layer['hrefRaw'] ?? '')) as $tokenName) {
            $names[$tokenName] = true;
        }
    }

    $list = array_keys($names);
    sort($list);

    return $list;
}

function customer_message_render_cache_visual_token_payload(array $tokenNames, array $tokenValues): array
{
    $payload = [];

    foreach ($tokenNames as $tokenName) {
        $tokenName = trim((string) $tokenName);
        if ($tokenName === '') {
            continue;
        }

        $candidateKeys = [
            $tokenName,
            '{{' . $tokenName . '}}',
        ];

        foreach ($candidateKeys as $candidateKey) {
            if (!array_key_exists($candidateKey, $tokenValues)) {
                continue;
            }

            $payload[$tokenName] = is_scalar($tokenValues[$candidateKey]) || $tokenValues[$candidateKey] === null
                ? (string) $tokenValues[$candidateKey]
                : message_queue_json_encode($tokenValues[$candidateKey]);
            continue 2;
        }

        $payload[$tokenName] = '{{' . $tokenName . '}}';
    }

    ksort($payload);

    return $payload;
}

function customer_message_render_cache_pipeline_version(): string
{
    static $version = null;

    if (is_string($version)) {
        return $version;
    }

    $files = [
        BASE_PATH . '/scripts/render_composicao.js',
        BASE_PATH . '/includes/customer_messages.php',
        BASE_PATH . '/includes/customer_message_scene.php',
        __FILE__,
    ];
    $parts = [];

    foreach ($files as $file) {
        $parts[] = is_file($file)
            ? basename($file) . ':' . (int) @filesize($file) . ':' . (int) @filemtime($file)
            : basename($file) . ':missing';
    }

    $version = hash('sha256', implode('|', $parts));

    return $version;
}

function customer_message_render_cache_background_fingerprint(array $scene, array $options = []): string
{
    $scene = customer_message_scene_normalize($scene);
    $backgroundRaw = trim((string) ($scene['canvas']['backgroundImage'] ?? ''));
    $backgroundPath = customer_message_scene_renderer_local_asset_path($backgroundRaw);

    if ($backgroundPath === '') {
        $backgroundPath = customer_message_scene_renderer_local_asset_path((string) ($options['hero_image_path'] ?? ''));
    }

    if ($backgroundPath !== '') {
        $absolutePath = BASE_PATH . '/' . ltrim($backgroundPath, '/');
        if (is_file($absolutePath)) {
            return hash('sha256', implode('|', [
                $backgroundPath,
                (int) @filesize($absolutePath),
                (int) @filemtime($absolutePath),
            ]));
        }
    }

    return hash('sha256', $backgroundRaw . '|' . trim((string) ($options['hero_image_path'] ?? '')));
}

function customer_message_render_cache_descriptor(array $scene, array $tokenValues, array $options = []): array
{
    $normalizedScene = customer_message_scene_normalize($scene);
    $tokenNames = customer_message_render_cache_visual_token_names($normalizedScene);
    $visualTokenPayload = customer_message_render_cache_visual_token_payload($tokenNames, $tokenValues);
    $sceneHash = message_queue_hash_payload($normalizedScene);
    $visualTokenHash = $visualTokenPayload === []
        ? 'static'
        : message_queue_hash_payload($visualTokenPayload);
    $normalizerVersion = trim((string) ($options['normalizer_version'] ?? CUSTOMER_MESSAGE_RENDER_CACHE_NORMALIZER_VERSION))
        ?: CUSTOMER_MESSAGE_RENDER_CACHE_NORMALIZER_VERSION;
    $sceneVersion = trim((string) ($options['scene_version'] ?? ('scene-schema-' . (int) ($normalizedScene['schemaVersion'] ?? 1))))
        ?: 'scene-schema-1';
    $backgroundFingerprint = customer_message_render_cache_background_fingerprint($normalizedScene, $options);
    $rendererVersion = customer_message_render_cache_pipeline_version();
    $cacheKey = hash('sha256', implode('|', [
        'scene:' . $sceneHash,
        'visual:' . $visualTokenHash,
        'renderer:' . $rendererVersion,
        'normalizer:' . $normalizerVersion,
        'scene_version:' . $sceneVersion,
        'background:' . $backgroundFingerprint,
    ]));

    return [
        'eligible' => true,
        'cache_key' => $cacheKey,
        'scene_hash' => $sceneHash,
        'visual_token_hash' => $visualTokenHash,
        'visual_token_names' => $tokenNames,
        'has_visual_tokens' => $tokenNames !== [],
        'normalizer_version' => $normalizerVersion,
        'scene_version' => $sceneVersion,
        'renderer_version' => $rendererVersion,
        'background_fingerprint' => $backgroundFingerprint,
    ];
}

function customer_message_render_cache_meta_dir(): string
{
    return BASE_PATH . '/storage/messages/render-cache';
}

function customer_message_render_cache_public_dir(): string
{
    return BASE_PATH . '/uploads/messages/render-cache';
}

function customer_message_render_cache_prepare_dirs(): bool
{
    return customer_message_scene_renderer_prepare_dir(customer_message_render_cache_meta_dir())
        && customer_message_scene_renderer_prepare_dir(customer_message_render_cache_public_dir());
}

function customer_message_render_cache_manifest_path(string $cacheKey): string
{
    return customer_message_render_cache_meta_dir() . '/' . $cacheKey . '.json';
}

function customer_message_render_cache_lock_path(string $cacheKey): string
{
    return customer_message_render_cache_meta_dir() . '/' . $cacheKey . '.lock';
}

function customer_message_render_cache_public_relative_path(string $cacheKey, string $extension): string
{
    $extension = strtolower(trim($extension));
    $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'png';

    return 'uploads/messages/render-cache/' . $cacheKey . '.' . $extension;
}

function customer_message_render_cache_public_absolute_path(string $cacheKey, string $extension): string
{
    return BASE_PATH . '/' . customer_message_render_cache_public_relative_path($cacheKey, $extension);
}

function customer_message_render_cache_acquire_lock(string $cacheKey)
{
    if (!customer_message_render_cache_prepare_dirs()) {
        return null;
    }

    $handle = @fopen(customer_message_render_cache_lock_path($cacheKey), 'c+');
    if (!is_resource($handle)) {
        return null;
    }

    if (!@flock($handle, LOCK_EX)) {
        fclose($handle);
        return null;
    }

    return $handle;
}

function customer_message_render_cache_release_lock($handle): void
{
    if (!is_resource($handle)) {
        return;
    }

    @flock($handle, LOCK_UN);
    @fclose($handle);
}

function customer_message_render_cache_read_result(array $cache): ?array
{
    $manifestPath = customer_message_render_cache_manifest_path((string) ($cache['cache_key'] ?? ''));

    if (!is_file($manifestPath)) {
        return null;
    }

    $decoded = json_decode((string) @file_get_contents($manifestPath), true);
    if (!is_array($decoded)) {
        return null;
    }

    $relativePath = trim((string) ($decoded['path'] ?? ''));
    if ($relativePath === '') {
        return null;
    }

    $absolutePath = BASE_PATH . '/' . ltrim($relativePath, '/');
    if (!is_file($absolutePath)) {
        @unlink($manifestPath);
        return null;
    }

    $width = (int) ($decoded['width'] ?? 0);
    $height = (int) ($decoded['height'] ?? 0);
    if ($width <= 0 || $height <= 0) {
        $dimensions = customer_message_scene_render_image_dimensions($absolutePath);
        $width = max($width, (int) ($dimensions['width'] ?? 0));
        $height = max($height, (int) ($dimensions['height'] ?? 0));
    }

    return [
        'path' => $relativePath,
        'absolute_url' => absolute_app_url($relativePath),
        'width' => $width,
        'height' => $height,
        'hotspots' => is_array($decoded['hotspots'] ?? null) ? $decoded['hotspots'] : [],
        'renderer' => trim((string) ($decoded['renderer'] ?? 'cache')) ?: 'cache',
    ];
}

function customer_message_render_cache_store(array $cache, array $renderedArt): ?array
{
    $sourceRelativePath = trim((string) ($renderedArt['path'] ?? ''));
    if ($sourceRelativePath === '') {
        return null;
    }

    $sourceAbsolutePath = BASE_PATH . '/' . ltrim($sourceRelativePath, '/');
    if (!is_file($sourceAbsolutePath) || !customer_message_render_cache_prepare_dirs()) {
        return null;
    }

    $extension = strtolower(pathinfo($sourceAbsolutePath, PATHINFO_EXTENSION) ?: 'png');
    $targetRelativePath = customer_message_render_cache_public_relative_path((string) $cache['cache_key'], $extension);
    $targetAbsolutePath = customer_message_render_cache_public_absolute_path((string) $cache['cache_key'], $extension);

    if ($sourceAbsolutePath !== $targetAbsolutePath) {
        if (!@copy($sourceAbsolutePath, $targetAbsolutePath)) {
            return null;
        }
    }

    $width = max(0, (int) ($renderedArt['width'] ?? 0));
    $height = max(0, (int) ($renderedArt['height'] ?? 0));
    if ($width <= 0 || $height <= 0) {
        $dimensions = customer_message_scene_render_image_dimensions($targetAbsolutePath);
        $width = max($width, (int) ($dimensions['width'] ?? 0));
        $height = max($height, (int) ($dimensions['height'] ?? 0));
    }

    $manifest = [
        'cache_key' => (string) $cache['cache_key'],
        'path' => $targetRelativePath,
        'renderer' => trim((string) ($renderedArt['renderer'] ?? 'cache_store')) ?: 'cache_store',
        'width' => $width,
        'height' => $height,
        'hotspots' => is_array($renderedArt['hotspots'] ?? null) ? $renderedArt['hotspots'] : [],
        'scene_hash' => (string) ($cache['scene_hash'] ?? ''),
        'visual_token_hash' => (string) ($cache['visual_token_hash'] ?? ''),
        'background_fingerprint' => (string) ($cache['background_fingerprint'] ?? ''),
        'renderer_version' => (string) ($cache['renderer_version'] ?? ''),
        'normalizer_version' => (string) ($cache['normalizer_version'] ?? ''),
        'scene_version' => (string) ($cache['scene_version'] ?? ''),
        'created_at' => message_queue_now(),
    ];

    if (@file_put_contents(
        customer_message_render_cache_manifest_path((string) $cache['cache_key']),
        json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) === false) {
        return null;
    }

    return [
        'path' => $targetRelativePath,
        'absolute_url' => absolute_app_url($targetRelativePath),
        'width' => $width,
        'height' => $height,
        'hotspots' => $manifest['hotspots'],
        'renderer' => (string) $manifest['renderer'],
    ];
}

function customer_message_scene_render_with_cache(
    array $scene,
    array $tokenValues,
    array $options,
    callable $renderer,
    ?array &$cacheDebug = null
): ?array {
    $cache = customer_message_render_cache_descriptor($scene, $tokenValues, $options);
    $cacheDebug = [
        'eligible' => (bool) ($cache['eligible'] ?? false),
        'cache_key' => (string) ($cache['cache_key'] ?? ''),
        'scene_hash' => (string) ($cache['scene_hash'] ?? ''),
        'visual_token_hash' => (string) ($cache['visual_token_hash'] ?? ''),
        'has_visual_tokens' => !empty($cache['has_visual_tokens']),
        'visual_token_names' => (array) ($cache['visual_token_names'] ?? []),
        'background_fingerprint' => (string) ($cache['background_fingerprint'] ?? ''),
        'renderer_version' => (string) ($cache['renderer_version'] ?? ''),
        'normalizer_version' => (string) ($cache['normalizer_version'] ?? ''),
        'scene_version' => (string) ($cache['scene_version'] ?? ''),
        'status' => 'starting',
    ];

    if (empty($cache['eligible'])) {
        $cacheDebug['status'] = 'bypass_ineligible';
        return $renderer();
    }

    $lockHandle = customer_message_render_cache_acquire_lock((string) $cache['cache_key']);
    if (!is_resource($lockHandle)) {
        $cacheDebug['status'] = 'bypass_lock_unavailable';
        return $renderer();
    }

    try {
        $cachedResult = customer_message_render_cache_read_result($cache);
        if (is_array($cachedResult)) {
            $cacheDebug['status'] = 'hit';
            return $cachedResult;
        }

        $cacheDebug['status'] = 'miss';
        $renderedArt = $renderer();
        if (!is_array($renderedArt)) {
            $cacheDebug['status'] = 'miss_render_failed';
            return $renderedArt;
        }

        $stored = customer_message_render_cache_store($cache, $renderedArt);
        if (is_array($stored)) {
            $cacheDebug['status'] = 'stored';
            return $stored;
        }

        $cacheDebug['status'] = 'store_failed';
        return $renderedArt;
    } finally {
        customer_message_render_cache_release_lock($lockHandle);
    }
}
