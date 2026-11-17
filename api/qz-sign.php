<?php
require_once __DIR__ . '/../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'POST is required.'], 405);
}

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$request = json_decode(file_get_contents('php://input'), true);
if (!is_array($request) || !isset($request['data']) || !is_string($request['data'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid signing request.'], 400);
}

if (!verify_csrf()) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
}

if (strlen($request['data']) > 1048576) {
    jsonResponse(['success' => false, 'message' => 'Signing request is too large.'], 413);
}

$qzConfigPath = __DIR__ . '/../config/qz-tray.php';
if (!is_file($qzConfigPath)) {
    jsonResponse(['success' => false, 'message' => 'QZ Tray configuration was not found.'], 503);
}

$qzConfig = require $qzConfigPath;
$qzConfig = is_array($qzConfig) ? $qzConfig : [];
$privateKeyPath = $qzConfig['private_key_path'] ?? '';
if (!$privateKeyPath || !is_readable($privateKeyPath)) {
    jsonResponse(['success' => false, 'message' => 'QZ Tray private key is not available.'], 503);
}

if (!function_exists('openssl_sign')) {
    jsonResponse(['success' => false, 'message' => 'PHP OpenSSL is required for QZ Tray signing.'], 503);
}

$privateKeyContents = file_get_contents($privateKeyPath);
$privateKey = $privateKeyContents === false
    ? false
    : openssl_pkey_get_private($privateKeyContents, $qzConfig['private_key_passphrase'] ?? '');
if ($privateKey === false) {
    jsonResponse(['success' => false, 'message' => 'Could not load the configured QZ Tray private key.'], 503);
}

$signature = '';
if (!openssl_sign($request['data'], $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
    jsonResponse(['success' => false, 'message' => 'Could not sign the QZ Tray request.'], 500);
}

jsonResponse(['success' => true, 'signature' => base64_encode($signature)]);
