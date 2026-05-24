<?php

require __DIR__ . '/../vendor/autoload.php';

$hostname = is_string($_ENV['PDS_HOSTNAME'] ?? null) ? $_ENV['PDS_HOSTNAME'] : 'localhost';
$_ENV['PDS_HOSTNAME'] = $hostname;
putenv('PDS_HOSTNAME=' . $hostname);

$appViewUrl = is_string($_ENV['PDS_BSKY_APP_VIEW_URL'] ?? null)
    ? $_ENV['PDS_BSKY_APP_VIEW_URL']
    : 'https://api.bsky.app';
$_ENV['PDS_BSKY_APP_VIEW_URL'] = $appViewUrl;
putenv('PDS_BSKY_APP_VIEW_URL=' . $appViewUrl);

$dataDir = ':memory:';
$_ENV['PDS_DATA_DIRECTORY'] = $dataDir;
putenv('PDS_DATA_DIRECTORY=' . $dataDir);

$adminPassword = is_string($_ENV['PDS_ADMIN_PASSWORD'] ?? null) && $_ENV['PDS_ADMIN_PASSWORD'] !== ''
    ? $_ENV['PDS_ADMIN_PASSWORD']
    : 'test-admin-password';
$_ENV['PDS_ADMIN_PASSWORD'] = $adminPassword;
putenv('PDS_ADMIN_PASSWORD=' . $adminPassword);

$jwtSecret = is_string($_ENV['PDS_JWT_SECRET'] ?? null) && $_ENV['PDS_JWT_SECRET'] !== ''
    ? $_ENV['PDS_JWT_SECRET']
    : 'test-jwt-secret';
$_ENV['PDS_JWT_SECRET'] = $jwtSecret;
putenv('PDS_JWT_SECRET=' . $jwtSecret);

$plcRotationHex = is_string($_ENV['PDS_PLC_ROTATION_KEY_K256_PRIVATE_KEY_HEX'] ?? null)
    && $_ENV['PDS_PLC_ROTATION_KEY_K256_PRIVATE_KEY_HEX'] !== ''
    ? $_ENV['PDS_PLC_ROTATION_KEY_K256_PRIVATE_KEY_HEX']
    : str_repeat('a', 64);
$_ENV['PDS_PLC_ROTATION_KEY_K256_PRIVATE_KEY_HEX'] = $plcRotationHex;
putenv('PDS_PLC_ROTATION_KEY_K256_PRIVATE_KEY_HEX=' . $plcRotationHex);
