<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Domain\Account\AccountRepository;
use App\Domain\Account\AppPassword\AppPasswordRepository;
use App\Domain\Actor\ActorRepository;
use App\Domain\Pds\Atproto\AppView\AppViewClient;
use App\Infrastructure\Atproto\AppView\GuzzleAppViewClient;
use App\Infrastructure\Persistence\Account\AppPassword\InMemoryAppPasswordRepository;
use App\Infrastructure\Persistence\Account\InMemoryAccountRepository;
use App\Infrastructure\Persistence\Actor\InMemoryActorRepository;
use DI\ContainerBuilder;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Container\ContainerInterface;

use function DI\autowire;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        ActorRepository::class => autowire(InMemoryActorRepository::class),
        AccountRepository::class => autowire(InMemoryAccountRepository::class),
        AppPasswordRepository::class      => autowire(InMemoryAppPasswordRepository::class),
        AppViewClient::class => function (ContainerInterface $container): AppViewClient {
            $settings = $container->get(SettingsInterface::class);
            $baseUrl = $settings->get('pds')['bskyAppViewUrl']
                ?? throw new \RuntimeException('PDS_BSKY_APP_VIEW_URL is required to use the AppView client.');

            $httpClient = new GuzzleClient([
                'base_uri' => rtrim($baseUrl, '/') . '/',
                'timeout' => 10.0,
                'headers' => ['Accept' => 'application/json'],
            ]);

            return new GuzzleAppViewClient($httpClient);
        },
    ]);
};
