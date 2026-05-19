<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/constants.php';

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
