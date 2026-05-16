<?php

require __DIR__ . '/../vendor/autoload.php';

$_ENV['PDS_HOSTNAME'] = $_ENV['PDS_HOSTNAME'] ?? 'localhost';
putenv('PDS_HOSTNAME=' . $_ENV['PDS_HOSTNAME']);
