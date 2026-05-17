<?php

require __DIR__ . '/../vendor/autoload.php';

$_ENV['PDS_HOSTNAME'] = $_ENV['PDS_HOSTNAME'] ?? 'localhost';
putenv('PDS_HOSTNAME=' . $_ENV['PDS_HOSTNAME']);

$_ENV['PDS_BSKY_APP_VIEW_URL'] = $_ENV['PDS_BSKY_APP_VIEW_URL'] ?? 'https://api.bsky.app';
putenv('PDS_BSKY_APP_VIEW_URL=' . $_ENV['PDS_BSKY_APP_VIEW_URL']);
