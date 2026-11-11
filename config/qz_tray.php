<?php

$qzLocalConfigPath = __DIR__ . '/qz-tray.local.php';
$qzLocalConfig = [];

if (is_file($qzLocalConfigPath)) {
    $loadedQzConfig = require $qzLocalConfigPath;
    if (is_array($loadedQzConfig)) {
        $qzLocalConfig = $loadedQzConfig;
    } else {
        error_log('QZ Tray local configuration must return an array.');
    }
}

$qzCertificatePath = getenv('QZ_CERTIFICATE_PATH') ?: ($qzLocalConfig['certificate_path'] ?? '');
$qzPrivateKeyPath = getenv('QZ_PRIVATE_KEY_PATH') ?: ($qzLocalConfig['private_key_path'] ?? '');
$qzPrivateKeyPassphrase = getenv('QZ_PRIVATE_KEY_PASSPHRASE') ?: ($qzLocalConfig['private_key_passphrase'] ?? '');

if (!defined('QZ_CERTIFICATE_PATH')) {
    define('QZ_CERTIFICATE_PATH', $qzCertificatePath);
}
if (!defined('QZ_PRIVATE_KEY_PATH')) {
    define('QZ_PRIVATE_KEY_PATH', $qzPrivateKeyPath);
}
if (!defined('QZ_PRIVATE_KEY_PASSPHRASE')) {
    define('QZ_PRIVATE_KEY_PASSPHRASE', $qzPrivateKeyPassphrase);
}
