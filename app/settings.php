<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Level;

return function (ContainerBuilder $containerBuilder) {

    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
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
                    'hostname' => $_ENV['PDS_HOSTNAME'] ?? throw new \RuntimeException('PDS_HOSTNAME is required'),
                    'privacyPolicyUrl' => $_ENV['PDS_PRIVACY_POLICY_URL'] ?? null,
                    'termsOfServiceUrl' => $_ENV['PDS_TERMS_OF_SERVICE_URL'] ?? null,
                    'email' => $_ENV['PDS_CONTACT_EMAIL_ADDRESS'] ?? null,
                ]
            ]);
        }
    ]);
};
