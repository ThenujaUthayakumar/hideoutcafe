<?php
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/qz_tray.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, private');

if (!isLoggedIn()) {
    http_response_code(401);
    echo 'Log in to retrieve the QZ Tray certificate.';
    exit;
}

if (QZ_CERTIFICATE_PATH === '' || !is_readable(QZ_CERTIFICATE_PATH)) {
    error_log('QZ Tray certificate is not configured or readable.');
    http_response_code(503);
    echo 'QZ Tray certificate is not configured on the server.';
    exit;
}

$certificate = file_get_contents(QZ_CERTIFICATE_PATH);
if ($certificate === false || trim($certificate) === '') {
    error_log('QZ Tray certificate could not be read or is empty.');
    http_response_code(503);
    echo 'QZ Tray certificate could not be loaded.';
    exit;
}

echo $certificate;
