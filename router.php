<?php
/**
 * Clean-URL router for PHP's built-in development server.
 */
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$relativePath = trim($requestPath, '/');

if ($relativePath === '' || strpos($relativePath, '/') !== false) {
    return false;
}

$requestedFile = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (
    in_array($method, ['GET', 'HEAD'], true)
    && preg_match('/\.php$/i', $relativePath)
    && is_file($requestedFile)
) {
    $cleanPath = '/' . substr($relativePath, 0, -4);
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $cleanPath . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

if (is_file($requestedFile) || is_dir($requestedFile)) {
    return false;
}

$pageFile = $requestedFile . '.php';
if (is_file($pageFile)) {
    $_SERVER['SCRIPT_FILENAME'] = $pageFile;
    $_SERVER['SCRIPT_NAME'] = '/' . $relativePath . '.php';
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    require $pageFile;
    return true;
}

return false;
