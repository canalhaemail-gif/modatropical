<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/includes/functions.php';

$relativePath = critical_image_relative_path((string) ($_GET['src'] ?? ''));

if ($relativePath === null) {
    http_response_code(404);
    exit('Imagem nao encontrada.');
}

$absolutePath = BASE_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

if (!is_file($absolutePath) || !is_readable($absolutePath)) {
    http_response_code(404);
    exit('Imagem nao encontrada.');
}

$lastModifiedTimestamp = (int) (filemtime($absolutePath) ?: time());
$fileSize = (int) (filesize($absolutePath) ?: 0);
$etag = '"' . sha1($relativePath . '|' . $lastModifiedTimestamp . '|' . $fileSize) . '"';
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
$contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
$ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$ifModifiedSince = trim((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
$sendNotModified = false;

if ($ifNoneMatch !== '') {
    $requestedEtags = array_map(
        static fn(string $value): string => trim(str_replace('W/', '', trim($value)), " \t\n\r\0\x0B\""),
        explode(',', $ifNoneMatch)
    );

    if (in_array(trim($etag, '"'), array_filter($requestedEtags), true)) {
        $sendNotModified = true;
    }
} elseif ($ifModifiedSince !== '') {
    $ifModifiedSinceTimestamp = strtotime($ifModifiedSince);

    if ($ifModifiedSinceTimestamp !== false && $ifModifiedSinceTimestamp >= $lastModifiedTimestamp) {
        $sendNotModified = true;
    }
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModifiedTimestamp) . ' GMT');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');

if ($sendNotModified) {
    http_response_code(304);
    exit;
}

if ($fileSize > 0) {
    header('Content-Length: ' . (string) $fileSize);
}

readfile($absolutePath);
exit;
