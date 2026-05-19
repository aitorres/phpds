<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Level;

return function (ContainerBuilder $containerBuilder) {
    $getRequiredEnv = static function (string $key) {
        $value = $_ENV[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException("$key is required");
        }
        return $value;
    };

    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () use ($getRequiredEnv) {
            return new Settings([
                'displayErrorDetails' => true, // Should be set to false in production
                'logError'            => false,
                'logErrorDetails'     => false,
                'logger' => [
                    'name' => 'slim-app',
                    'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => Level::Debug,
                ],
                // PDS-specific settings
                'pds' => [
                    'hostname' => $getRequiredEnv('PDS_HOSTNAME'),
                    'bskyAppViewUrl' => $getRequiredEnv('PDS_BSKY_APP_VIEW_URL'),
                    'privacyPolicyUrl' => $_ENV['PDS_PRIVACY_POLICY_URL'] ?? null,
                    'termsOfServiceUrl' => $_ENV['PDS_TERMS_OF_SERVICE_URL'] ?? null,
                    'email' => $_ENV['PDS_CONTACT_EMAIL_ADDRESS'] ?? null,
                ],
                // Persistence (SQLite) settings
                'database' => (static function () use ($getRequiredEnv): array {
                    $dataDir = $getRequiredEnv('PDS_DATA_DIRECTORY');
                    $resolve = static function (string $suffix) use ($dataDir): string {
                        if ($dataDir === ':memory:') {
                            return ':memory:';
                        }
                        return rtrim($dataDir, '/') . '/' . $suffix;
                    };

                    return [
                        'accountDb'     => $resolve('account.sqlite'),
                        'sequencerDb'   => $resolve('sequencer.sqlite'),
                        'didCacheDb'    => $resolve('did_cache.sqlite'),
                        'actorStoreDir' => $resolve('actors'),
                        'blobstoreDir'  => $resolve('blocks'),
                    ];
                })(),
            ]);
        }
    ]);
};
