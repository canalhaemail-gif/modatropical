<?php
declare(strict_types=1);

function customer_message_scene_canvas_defaults(): array
{
    return [
        'width' => 800,
        'height' => 1100,
    ];
}

function customer_message_scene_defaults(): array
{
    $canvas = customer_message_scene_canvas_defaults();

    return [
        'schemaVersion' => 1,
        'canvas' => [
            'width' => $canvas['width'],
            'height' => $canvas['height'],
            'backgroundImage' => '',
        ],
        'actions' => [
            'primaryHrefRaw' => '',
            'imageHrefRaw' => '',
        ],
        'layers' => [],
    ];
}

function customer_message_scene_float($value, float $fallback): float
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    return (float) $value;
}

function customer_message_scene_int($value, int $min, int $max, int $fallback): int
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    $value = (int) round((float) $value);

    return max($min, min($max, $value));
}

function customer_message_scene_bool($value, bool $fallback): bool
{
    if ($value === null) {
        return $fallback;
    }

    if (is_bool($value)) {
        return $value;
    }

    return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
}

function customer_message_scene_string($value, string $fallback = ''): string
{
    $value = is_scalar($value) ? trim((string) $value) : '';

    return $value !== '' ? $value : $fallback;
}

function customer_message_scene_px_from_percent($value, int $axisSize, int $fallback): int
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    $percent = max(0.0, min(100.0, (float) $value));

    return (int) round(($percent / 100) * $axisSize);
}

function customer_message_scene_percent_from_px($value, int $axisSize, int $fallback): int
{
    if ($axisSize <= 0 || !is_numeric($value)) {
        return $fallback;
    }

    return (int) round((((float) $value) / $axisSize) * 100);
}

function customer_message_scene_text_align($value, string $fallback = 'left'): string
{
    $value = trim((string) $value);

    return in_array($value, ['left', 'center', 'right'], true) ? $value : $fallback;
}

function customer_message_scene_shadow($value, string $fallback = 'off'): string
{
    $value = trim((string) $value);

    return in_array($value, ['off', 'soft', 'strong'], true) ? $value : $fallback;
}

function customer_message_scene_color($value, string $fallback): string
{
    $value = trim((string) $value);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
        return strtolower($value);
    }

    return $fallback;
}

function customer_message_scene_normalize_text_content($value, bool $preserveParagraphs = true): string
{
    $normalized = is_scalar($value) ? (string) $value : '';
    $normalized = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $normalized);
    $normalized = preg_replace('/[ \t]+/u', ' ', $normalized) ?? $normalized;

    $lines = explode("\n", $normalized);
    $lines = array_map(
        static function (string $line): string {
            return trim($line);
        },
        $lines
    );

    $normalized = implode("\n", $lines);

    if ($preserveParagraphs) {
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;
    } else {
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    return trim($normalized);
}

function customer_message_scene_background_image(array $context = []): string
{
    return trim((string) ($context['hero_image_path'] ?? ''));
}

function customer_message_scene_append_legacy_layer(array &$layers, array $layer): void
{
    if (($layer['type'] ?? '') === 'text' && trim((string) ($layer['textRaw'] ?? '')) === '') {
        return;
    }

    if (($layer['type'] ?? '') === 'button' && trim((string) ($layer['textRaw'] ?? '')) === '' && trim((string) ($layer['hrefRaw'] ?? '')) === '') {
        return;
    }

    if (($layer['type'] ?? '') === 'hotspot' && trim((string) ($layer['hrefRaw'] ?? '')) === '') {
        return;
    }

    $layers[] = $layer;
}

function customer_message_scene_from_legacy(array $context = []): array
{
    $defaults = customer_message_editor_defaults();
    $editor = customer_message_editor_settings($context);
    $extraLayers = customer_message_editor_layers($context);
    $canvas = customer_message_scene_canvas_defaults();

    $extraTypes = [];
    foreach ($extraLayers as $layer) {
        if (!is_array($layer)) {
            continue;
        }
        $t = trim((string) ($layer['type'] ?? ''));
        if (in_array($t, ['title', 'body', 'button', 'hotspot'], true)) {
            $extraTypes[$t] = true;
        }
    }

    $scene = customer_message_scene_defaults();
    $scene['canvas']['backgroundImage'] = customer_message_scene_background_image($context);
    $scene['actions']['primaryHrefRaw'] = trim((string) ($context['link_url'] ?? ''));
    $scene['actions']['imageHrefRaw'] = trim((string) ($editor['image_link_url'] ?? ''));

    $titleWidthPx = customer_message_scene_px_from_percent($editor['title_width'] ?? $defaults['title_width'], $canvas['width'], 576);
    $bodyWidthPx = customer_message_scene_px_from_percent($editor['body_width'] ?? $defaults['body_width'], $canvas['width'], 576);
    $buttonWidthPx = customer_message_scene_px_from_percent($editor['button_width'] ?? $defaults['button_width'], $canvas['width'], 208);
    $buttonHeightPx = customer_message_scene_px_from_percent($editor['button_height'] ?? $defaults['button_height'], $canvas['height'], 110);
    $hotspotWidthPx = customer_message_scene_px_from_percent($editor['image_hotspot_width'] ?? $defaults['image_hotspot_width'], $canvas['width'], 208);
    $hotspotHeightPx = customer_message_scene_px_from_percent($editor['image_hotspot_height'] ?? $defaults['image_hotspot_height'], $canvas['height'], 110);

    if (!isset($extraTypes['title'])) {
        customer_message_scene_append_legacy_layer($scene['layers'], [
            'id' => 'base_title',
            'type' => 'text',
            'role' => 'title',
            'visible' => customer_message_scene_bool($editor['show_title'] ?? true, true),
            'textRaw' => trim((string) ($context['title'] ?? '')),
            'x' => customer_message_scene_px_from_percent($editor['title_x'] ?? $defaults['title_x'], $canvas['width'], 64),
            'y' => customer_message_scene_px_from_percent($editor['title_y'] ?? $defaults['title_y'], $canvas['height'], 110),
            'width' => $titleWidthPx,
            'height' => 0,
            'fontFamily' => 'default',
            'fontSize' => customer_message_scene_int($editor['title_size'] ?? $defaults['title_size'], 12, 200, 54),
            'fontWeight' => customer_message_scene_bool($editor['title_bold'] ?? $defaults['title_bold'], true) ? 700 : 400,
            'fontStyle' => customer_message_scene_bool($editor['title_italic'] ?? $defaults['title_italic'], false) ? 'italic' : 'normal',
            'lineHeight' => customer_message_scene_float(($editor['title_line_height'] ?? $defaults['title_line_height']) / 100, 1.04),
            'textAlign' => customer_message_scene_text_align($editor['title_align'] ?? $defaults['title_align'], 'left'),
            'color' => customer_message_scene_color($editor['title_color'] ?? $defaults['title_color'], '#fff7f0'),
            'uppercase' => customer_message_scene_bool($editor['title_uppercase'] ?? $defaults['title_uppercase'], false),
            'shadow' => customer_message_scene_shadow($editor['title_shadow'] ?? $defaults['title_shadow'], 'strong'),
        ]);
    }

    if (!isset($extraTypes['body'])) {
        customer_message_scene_append_legacy_layer($scene['layers'], [
            'id' => 'base_body',
            'type' => 'text',
            'role' => 'body',
            'visible' => customer_message_scene_bool($editor['show_body'] ?? true, true),
            'textRaw' => trim((string) ($context['message'] ?? '')),
            'x' => customer_message_scene_px_from_percent($editor['body_x'] ?? $defaults['body_x'], $canvas['width'], 64),
            'y' => customer_message_scene_px_from_percent($editor['body_y'] ?? $defaults['body_y'], $canvas['height'], 330),
            'width' => $bodyWidthPx,
            'height' => 0,
            'fontFamily' => 'default',
            'fontSize' => customer_message_scene_int($editor['body_size'] ?? $defaults['body_size'], 12, 120, 18),
            'fontWeight' => customer_message_scene_bool($editor['body_bold'] ?? $defaults['body_bold'], false) ? 700 : 400,
            'fontStyle' => customer_message_scene_bool($editor['body_italic'] ?? $defaults['body_italic'], false) ? 'italic' : 'normal',
            'lineHeight' => customer_message_scene_float(($editor['body_line_height'] ?? $defaults['body_line_height']) / 100, 1.75),
            'textAlign' => customer_message_scene_text_align($editor['body_align'] ?? $defaults['body_align'], 'left'),
            'color' => customer_message_scene_color($editor['body_color'] ?? $defaults['body_color'], '#2c1917'),
            'uppercase' => customer_message_scene_bool($editor['body_uppercase'] ?? $defaults['body_uppercase'], false),
            'shadow' => customer_message_scene_shadow($editor['body_shadow'] ?? $defaults['body_shadow'], 'soft'),
        ]);
    }

    if (!isset($extraTypes['button'])) {
        customer_message_scene_append_legacy_layer($scene['layers'], [
            'id' => 'base_button',
            'type' => 'button',
            'role' => 'button',
            'visible' => customer_message_scene_bool($editor['show_button'] ?? true, true),
            'textRaw' => trim((string) ($editor['button_label'] ?? $context['button_label'] ?? '')),
            'hrefRaw' => trim((string) ($context['link_url'] ?? '')),
            'x' => customer_message_scene_px_from_percent($editor['button_x'] ?? $defaults['button_x'], $canvas['width'], 192),
            'y' => customer_message_scene_px_from_percent($editor['button_y'] ?? $defaults['button_y'], $canvas['height'], 902),
            'width' => $buttonWidthPx,
            'height' => $buttonHeightPx,
        ]);
    }

    if (!isset($extraTypes['hotspot'])) {
        customer_message_scene_append_legacy_layer($scene['layers'], [
            'id' => 'base_hotspot',
            'type' => 'hotspot',
            'role' => 'image_hotspot',
            'visible' => customer_message_scene_bool($editor['show_image_hotspot'] ?? false, false),
            'hrefRaw' => trim((string) ($editor['image_link_url'] ?? '')),
            'x' => customer_message_scene_px_from_percent($editor['image_hotspot_x'] ?? $defaults['image_hotspot_x'], $canvas['width'], 192),
            'y' => customer_message_scene_px_from_percent($editor['image_hotspot_y'] ?? $defaults['image_hotspot_y'], $canvas['height'], 858),
            'width' => $hotspotWidthPx,
            'height' => $hotspotHeightPx,
        ]);
    }

    foreach ($extraLayers as $index => $layer) {
        $type = (string) ($layer['type'] ?? '');
        $mappedType = match ($type) {
            'title', 'body' => 'text',
            'button' => 'button',
            'hotspot' => 'hotspot',
            default => '',
        };

        if ($mappedType === '') {
            continue;
        }

        customer_message_scene_append_legacy_layer($scene['layers'], [
            'id' => (string) ($layer['id'] ?? ('legacy_' . $index)),
            'type' => $mappedType,
            'role' => $type === 'hotspot' ? 'image_hotspot' : $type,
            'visible' => true,
            'textRaw' => trim((string) ($layer['content'] ?? '')),
            'hrefRaw' => trim((string) ($layer['link_url'] ?? '')),
            'x' => customer_message_scene_px_from_percent($layer['x'] ?? 0, $canvas['width'], 64),
            'y' => customer_message_scene_px_from_percent($layer['y'] ?? 0, $canvas['height'], 64),
            'width' => customer_message_scene_px_from_percent($layer['width'] ?? 24, $canvas['width'], 240),
            'height' => customer_message_scene_px_from_percent($layer['height'] ?? 10, $canvas['height'], 110),
            'fontFamily' => 'default',
            'fontSize' => customer_message_scene_int($layer['font_size'] ?? 18, 12, 200, 18),
            'fontWeight' => customer_message_scene_bool($layer['bold'] ?? false, false) ? 700 : 400,
            'fontStyle' => customer_message_scene_bool($layer['italic'] ?? false, false) ? 'italic' : 'normal',
            'lineHeight' => customer_message_scene_float(($layer['line_height'] ?? 140) / 100, 1.4),
            'textAlign' => customer_message_scene_text_align($layer['align'] ?? 'left', 'left'),
            'color' => $mappedType === 'text'
                ? customer_message_scene_color(
                    $type === 'title' ? ($editor['title_color'] ?? '#fff7f0') : ($editor['body_color'] ?? '#2c1917'),
                    $type === 'title' ? '#fff7f0' : '#2c1917'
                )
                : '#ffffff',
            'uppercase' => customer_message_scene_bool($layer['uppercase'] ?? false, false),
            'shadow' => customer_message_scene_shadow($layer['shadow'] ?? 'off', 'off'),
        ]);
    }

    return customer_message_scene_normalize($scene);
}

function customer_message_scene_normalize($scene): array
{
    $defaults = customer_message_scene_defaults();
    $canvasDefaults = customer_message_scene_canvas_defaults();

    if (is_string($scene)) {
        $decoded = json_decode($scene, true);
        $scene = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($scene)) {
        $scene = [];
    }

    $canvas = is_array($scene['canvas'] ?? null) ? $scene['canvas'] : [];
    $layers = is_array($scene['layers'] ?? null) ? $scene['layers'] : [];
    $actions = is_array($scene['actions'] ?? null) ? $scene['actions'] : [];

    $normalized = [
        'schemaVersion' => customer_message_scene_int($scene['schemaVersion'] ?? null, 1, 1, 1),
        'canvas' => [
            'width' => customer_message_scene_int($canvas['width'] ?? null, 240, 2000, $canvasDefaults['width']),
            'height' => customer_message_scene_int($canvas['height'] ?? null, 160, 4000, $canvasDefaults['height']),
            'backgroundImage' => customer_message_scene_string($canvas['backgroundImage'] ?? null, ''),
        ],
        'actions' => [
            'primaryHrefRaw' => customer_message_scene_string($actions['primaryHrefRaw'] ?? null, ''),
            'imageHrefRaw' => customer_message_scene_string($actions['imageHrefRaw'] ?? null, ''),
        ],
        'layers' => [],
    ];

    foreach ($layers as $index => $layer) {
        if (!is_array($layer)) {
            continue;
        }

        $type = trim((string) ($layer['type'] ?? ''));
        if (!in_array($type, ['text', 'button', 'hotspot'], true)) {
            continue;
        }

        $normalized['layers'][] = [
            'id' => preg_replace('/[^a-z0-9_-]/i', '', (string) ($layer['id'] ?? ('layer_' . $index))) ?: ('layer_' . $index),
            'type' => $type,
            'role' => customer_message_scene_string($layer['role'] ?? null, ''),
            'visible' => customer_message_scene_bool($layer['visible'] ?? null, true),
            'textRaw' => customer_message_scene_normalize_text_content(
                customer_message_scene_string($layer['textRaw'] ?? ($layer['content'] ?? null), ''),
                customer_message_scene_string($layer['role'] ?? null, '') !== 'title'
            ),
            'hrefRaw' => customer_message_scene_string($layer['hrefRaw'] ?? ($layer['link_url'] ?? null), ''),
            'x' => customer_message_scene_int($layer['x'] ?? null, 0, $normalized['canvas']['width'], 0),
            'y' => customer_message_scene_int($layer['y'] ?? null, 0, $normalized['canvas']['height'], 0),
            'width' => customer_message_scene_int($layer['width'] ?? null, 0, $normalized['canvas']['width'], 0),
            'height' => customer_message_scene_int($layer['height'] ?? null, 0, $normalized['canvas']['height'], 0),
            'fontFamily' => customer_message_scene_string($layer['fontFamily'] ?? null, 'default'),
            'fontSize' => customer_message_scene_int($layer['fontSize'] ?? ($layer['font_size'] ?? null), 10, 220, 18),
            'fontWeight' => customer_message_scene_int($layer['fontWeight'] ?? null, 100, 900, 400),
            'fontStyle' => in_array((string) ($layer['fontStyle'] ?? ''), ['normal', 'italic'], true) ? (string) $layer['fontStyle'] : 'normal',
            'lineHeight' => max(0.6, min(4.0, customer_message_scene_float($layer['lineHeight'] ?? null, 1.4))),
            'textAlign' => customer_message_scene_text_align($layer['textAlign'] ?? ($layer['align'] ?? null), 'left'),
            'color' => customer_message_scene_color($layer['color'] ?? null, '#ffffff'),
            'uppercase' => customer_message_scene_bool($layer['uppercase'] ?? null, false),
            'shadow' => customer_message_scene_shadow($layer['shadow'] ?? null, 'off'),
        ];
    }

    return $normalized;
}

function customer_message_scene_to_editor_layers($scene): array
{
    $scene = customer_message_scene_normalize($scene);
    $canvasDefaults = customer_message_scene_canvas_defaults();
    $canvas = is_array($scene['canvas'] ?? null) ? $scene['canvas'] : [];
    $canvasWidth = max(1, (int) ($canvas['width'] ?? ($canvasDefaults['width'] ?? 800)));
    $canvasHeight = max(1, (int) ($canvas['height'] ?? ($canvasDefaults['height'] ?? 1100)));
    $layers = [];

    foreach ((array) ($scene['layers'] ?? []) as $index => $layer) {
        if (!is_array($layer)) {
            continue;
        }

        $type = (string) ($layer['type'] ?? '');
        $role = (string) ($layer['role'] ?? '');
        $item = null;

        if ($type === 'text' && in_array($role, ['title', 'body'], true)) {
            $item = [
                'id' => (string) ($layer['id'] ?? ('layer_' . $index)),
                'type' => $role,
                'content' => customer_message_scene_normalize_text_content(
                    (string) ($layer['textRaw'] ?? ''),
                    $role !== 'title'
                ),
                'link_url' => null,
                'x' => customer_message_scene_percent_from_px($layer['x'] ?? 0, $canvasWidth, 0),
                'y' => customer_message_scene_percent_from_px($layer['y'] ?? 0, $canvasHeight, 0),
                'width' => customer_message_scene_percent_from_px($layer['width'] ?? 0, $canvasWidth, 0),
                'height' => customer_message_scene_percent_from_px($layer['height'] ?? 0, $canvasHeight, 0),
                'font_size' => customer_message_scene_int($layer['fontSize'] ?? null, 12, 220, $role === 'title' ? 54 : 18),
                'line_height' => customer_message_scene_int(
                    round(customer_message_scene_float($layer['lineHeight'] ?? 1.4, 1.4) * 100),
                    $role === 'title' ? 80 : 100,
                    $role === 'title' ? 220 : 260,
                    $role === 'title' ? 104 : 175
                ),
                'align' => customer_message_scene_text_align($layer['textAlign'] ?? null, 'left'),
                'bold' => customer_message_scene_int($layer['fontWeight'] ?? null, 100, 900, 400) >= 700,
                'italic' => ((string) ($layer['fontStyle'] ?? 'normal')) === 'italic',
                'uppercase' => customer_message_scene_bool($layer['uppercase'] ?? null, false),
                'shadow' => customer_message_scene_shadow($layer['shadow'] ?? null, 'off'),
            ];
        } elseif ($type === 'button') {
            $item = [
                'id' => (string) ($layer['id'] ?? ('layer_' . $index)),
                'type' => 'button',
                'content' => trim((string) ($layer['textRaw'] ?? '')),
                'link_url' => customer_message_scene_string($layer['hrefRaw'] ?? null, ''),
                'x' => customer_message_scene_percent_from_px($layer['x'] ?? 0, $canvasWidth, 0),
                'y' => customer_message_scene_percent_from_px($layer['y'] ?? 0, $canvasHeight, 0),
                'width' => customer_message_scene_percent_from_px($layer['width'] ?? 0, $canvasWidth, 0),
                'height' => customer_message_scene_percent_from_px($layer['height'] ?? 0, $canvasHeight, 0),
                'font_size' => customer_message_scene_int($layer['fontSize'] ?? null, 12, 220, 18),
                'line_height' => customer_message_scene_int(
                    round(customer_message_scene_float($layer['lineHeight'] ?? 1.2, 1.2) * 100),
                    100,
                    260,
                    120
                ),
                'align' => 'left',
                'bold' => false,
                'italic' => false,
                'uppercase' => false,
                'shadow' => 'off',
            ];
        } elseif ($type === 'hotspot') {
            $item = [
                'id' => (string) ($layer['id'] ?? ('layer_' . $index)),
                'type' => 'hotspot',
                'content' => '',
                'link_url' => customer_message_scene_string($layer['hrefRaw'] ?? null, ''),
                'x' => customer_message_scene_percent_from_px($layer['x'] ?? 0, $canvasWidth, 0),
                'y' => customer_message_scene_percent_from_px($layer['y'] ?? 0, $canvasHeight, 0),
                'width' => customer_message_scene_percent_from_px($layer['width'] ?? 0, $canvasWidth, 0),
                'height' => customer_message_scene_percent_from_px($layer['height'] ?? 0, $canvasHeight, 0),
                'font_size' => 18,
                'line_height' => 175,
                'align' => 'left',
                'bold' => false,
                'italic' => false,
                'uppercase' => false,
                'shadow' => 'off',
            ];
        }

        if ($item !== null) {
            $layers[] = $item;
        }
    }

    return customer_message_editor_layers(['email_editor_layers' => $layers]);
}

function customer_message_scene_layers_by_role(array $scene): array
{
    $indexed = [];

    foreach ((array) ($scene['layers'] ?? []) as $layer) {
        if (!is_array($layer)) {
            continue;
        }

        $role = trim((string) ($layer['role'] ?? ''));
        if ($role === '') {
            $role = match ((string) ($layer['type'] ?? '')) {
                'hotspot' => 'image_hotspot',
                default => trim((string) ($layer['type'] ?? '')),
            };
        }

        if ($role === '' || isset($indexed[$role])) {
            continue;
        }

        $indexed[$role] = $layer;
    }

    return $indexed;
}

function customer_message_scene_percent_delta($valueA, int $axisA, $valueB, int $axisB): float
{
    if ($axisA <= 0 || $axisB <= 0) {
        return 0.0;
    }

    return abs((((float) $valueA) / $axisA) * 100.0 - (((float) $valueB) / $axisB) * 100.0);
}

function customer_message_scene_layer_overflows_canvas(array $layer, array $canvas): bool
{
    $canvasWidth = (int) ($canvas['width'] ?? 0);
    $canvasHeight = (int) ($canvas['height'] ?? 0);
    $x = (int) ($layer['x'] ?? 0);
    $y = (int) ($layer['y'] ?? 0);
    $width = (int) ($layer['width'] ?? 0);
    $height = (int) ($layer['height'] ?? 0);
    $type = (string) ($layer['type'] ?? '');
    $overflowAllowance = $type === 'text' ? 24 : 8;

    if ($canvasWidth > 0 && $width > 0 && ($x + $width) > ($canvasWidth + $overflowAllowance)) {
        return true;
    }

    if ($canvasHeight > 0 && $height > 0 && ($y + $height) > ($canvasHeight + $overflowAllowance)) {
        return true;
    }

    return false;
}

function customer_message_scene_should_prefer_legacy(array $scene, array $legacyScene): bool
{
    $sceneLayers = customer_message_scene_layers_by_role($scene);
    $legacyLayers = customer_message_scene_layers_by_role($legacyScene);

    if ($sceneLayers === [] || $legacyLayers === []) {
        return false;
    }

    $sceneCanvas = is_array($scene['canvas'] ?? null) ? $scene['canvas'] : [];
    $legacyCanvas = is_array($legacyScene['canvas'] ?? null) ? $legacyScene['canvas'] : [];
    $canvasDefaults = customer_message_scene_canvas_defaults();
    $sceneCanvasWidth = (int) ($sceneCanvas['width'] ?? 0);
    $sceneCanvasHeight = (int) ($sceneCanvas['height'] ?? 0);
    $sceneUsesCustomCanvas = abs($sceneCanvasWidth - (int) ($canvasDefaults['width'] ?? 800)) > 2
        || abs($sceneCanvasHeight - (int) ($canvasDefaults['height'] ?? 1100)) > 2;

    if ($sceneUsesCustomCanvas && $sceneCanvasWidth > 0 && $sceneCanvasHeight > 0) {
        $sceneHasOverflow = false;
        foreach ($sceneLayers as $sceneLayer) {
            if (customer_message_scene_layer_overflows_canvas($sceneLayer, $sceneCanvas)) {
                $sceneHasOverflow = true;
                break;
            }
        }

        if (!$sceneHasOverflow) {
            return false;
        }
    }

    $roles = ['title', 'body', 'button', 'image_hotspot'];
    $compared = 0;
    $strongMismatch = false;

    foreach ($roles as $role) {
        if (!isset($sceneLayers[$role], $legacyLayers[$role])) {
            continue;
        }

        $compared++;
        $sceneLayer = $sceneLayers[$role];
        $legacyLayer = $legacyLayers[$role];

        if (
            customer_message_scene_layer_overflows_canvas($sceneLayer, $sceneCanvas)
            && !customer_message_scene_layer_overflows_canvas($legacyLayer, $legacyCanvas)
        ) {
            return true;
        }

        $xDelta = customer_message_scene_percent_delta(
            $sceneLayer['x'] ?? 0,
            (int) ($sceneCanvas['width'] ?? 0),
            $legacyLayer['x'] ?? 0,
            (int) ($legacyCanvas['width'] ?? 0)
        );
        $yDelta = customer_message_scene_percent_delta(
            $sceneLayer['y'] ?? 0,
            (int) ($sceneCanvas['height'] ?? 0),
            $legacyLayer['y'] ?? 0,
            (int) ($legacyCanvas['height'] ?? 0)
        );
        $widthDelta = customer_message_scene_percent_delta(
            $sceneLayer['width'] ?? 0,
            (int) ($sceneCanvas['width'] ?? 0),
            $legacyLayer['width'] ?? 0,
            (int) ($legacyCanvas['width'] ?? 0)
        );
        $heightDelta = customer_message_scene_percent_delta(
            $sceneLayer['height'] ?? 0,
            max(1, (int) ($sceneCanvas['height'] ?? 0)),
            $legacyLayer['height'] ?? 0,
            max(1, (int) ($legacyCanvas['height'] ?? 0))
        );

        if ($xDelta >= 18.0 || $yDelta >= 18.0 || $widthDelta >= 22.0 || $heightDelta >= 22.0) {
            $strongMismatch = true;
        }
    }

    return $compared > 0 && $strongMismatch;
}

function customer_message_scene_from_context(array $context = []): array
{
    $rawScene = $context['scene_json'] ?? $context['scene'] ?? null;
    $legacyScene = customer_message_scene_from_legacy($context);

    if (is_string($rawScene) && trim($rawScene) !== '') {
        $decoded = json_decode($rawScene, true);
        if (is_array($decoded)) {
            $normalized = customer_message_scene_normalize($decoded);
            return customer_message_scene_should_prefer_legacy($normalized, $legacyScene)
                ? $legacyScene
                : $normalized;
        }
    }

    if (is_array($rawScene)) {
        $normalized = customer_message_scene_normalize($rawScene);
        return customer_message_scene_should_prefer_legacy($normalized, $legacyScene)
            ? $legacyScene
            : $normalized;
    }

    return $legacyScene;
}

function customer_message_scene_json(array $context = []): string
{
    return json_encode(customer_message_scene_from_context($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: json_encode(customer_message_scene_defaults(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
