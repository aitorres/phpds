<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Domain\Account\AccountRepository;
use App\Domain\Account\AppPassword\AppPasswordRepository;
use App\Domain\Account\EmailToken\EmailTokenRepository;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;
use App\Domain\Actor\ActorRepository;
use App\Domain\Pds\Atproto\AppView\AppViewClient;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Repo\CarReader;
use App\Domain\Repo\CarWriter;
use App\Domain\Repo\DagCborDecoder;
use App\Domain\Repo\DagCborEncoder;
use App\Domain\Repo\RepoRootRepository;
use App\Domain\Sequencer\SequencerRepository;
use App\Infrastructure\Atproto\AppView\GuzzleAppViewClient;
use App\Infrastructure\Persistence\Account\AppPassword\InMemoryAppPasswordRepository;
use App\Infrastructure\Persistence\Account\EmailToken\InMemoryEmailTokenRepository;
use App\Infrastructure\Persistence\Account\RefreshToken\InMemoryRefreshTokenRepository;
use App\Infrastructure\Persistence\Account\InviteCode\InMemoryInviteCodeRepository;
use App\Infrastructure\Persistence\Account\InMemoryAccountRepository;
use App\Infrastructure\Persistence\Actor\InMemoryActorRepository;
use App\Infrastructure\Persistence\ActorStore\InMemoryActorStoreFactory;
use App\Infrastructure\Persistence\Repo\InMemoryRepoRootRepository;
use App\Infrastructure\Persistence\Sequencer\InMemorySequencerRepository;
use App\Infrastructure\Repo\NativeCarReader;
use App\Infrastructure\Repo\NativeCarWriter;
use App\Infrastructure\Repo\NativeDagCborDecoder;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use DI\ContainerBuilder;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Container\ContainerInterface;

use function DI\autowire;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        ActorStoreFactory::class => autowire(InMemoryActorStoreFactory::class),
        ActorRepository::class => autowire(InMemoryActorRepository::class),
        AccountRepository::class => autowire(InMemoryAccountRepository::class),
        AppPasswordRepository::class => autowire(InMemoryAppPasswordRepository::class),
        DagCborEncoder::class => autowire(NativeDagCborEncoder::class),
        DagCborDecoder::class => autowire(NativeDagCborDecoder::class),
        CarWriter::class => autowire(NativeCarWriter::class),
        CarReader::class => autowire(NativeCarReader::class),
        SequencerRepository::class => autowire(InMemorySequencerRepository::class),
        InviteCodeRepository::class => autowire(InMemoryInviteCodeRepository::class),
        RefreshTokenRepository::class => autowire(InMemoryRefreshTokenRepository::class),
        EmailTokenRepository::class => autowire(InMemoryEmailTokenRepository::class),
        RepoRootRepository::class => autowire(InMemoryRepoRootRepository::class),
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
