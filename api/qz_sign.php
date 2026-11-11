<?php
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/qz_tray.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, private');

if (!isLoggedIn()) {
    http_response_code(401);
    echo 'Log in to sign QZ Tray requests.';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed.';
    exit;
}

$request = $_POST['request'] ?? null;
if (!is_string($request) || $request === '' || strlen($request) > 2 * 1024 * 1024) {
    http_response_code(400);
    echo 'Invalid QZ Tray signing request.';
    exit;
}

if (QZ_PRIVATE_KEY_PATH === '' || !is_readable(QZ_PRIVATE_KEY_PATH)) {
    error_log('QZ Tray private key is not configured or readable.');
    http_response_code(503);
    echo 'QZ Tray signing key is not configured on the server.';
    exit;
}

if (!extension_loaded('openssl')) {
    error_log('QZ Tray signing requires the PHP OpenSSL extension.');
    http_response_code(503);
    echo 'PHP OpenSSL is required for QZ Tray signing.';
    exit;
}

$privateKeyContents = file_get_contents(QZ_PRIVATE_KEY_PATH);
$privateKey = $privateKeyContents === false
    ? false
    : openssl_pkey_get_private($privateKeyContents, QZ_PRIVATE_KEY_PASSPHRASE);

if ($privateKey === false) {
    error_log('QZ Tray private key could not be loaded.');
    http_response_code(503);
    echo 'QZ Tray signing key could not be loaded.';
    exit;
}

$signature = '';
if (!openssl_sign($request, $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
    error_log('QZ Tray request signing failed.');
    http_response_code(500);
    echo 'QZ Tray request signing failed.';
    exit;
}

echo base64_encode($signature);
