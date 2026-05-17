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
