<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Domain\Account\AccountRepository;
use App\Domain\Account\AppPassword\AppPasswordRepository;
use App\Domain\Account\EmailToken\EmailTokenRepository;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;
use App\Domain\Actor\ActorRepository;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Did\DidCacheRepository;
use App\Domain\Lexicon\LexiconRepository;
use App\Domain\OAuth\AccountDeviceRepository;
use App\Domain\OAuth\AuthorizationRequestRepository;
use App\Domain\OAuth\AuthorizedClientRepository;
use App\Domain\OAuth\DeviceRepository;
use App\Domain\OAuth\OAuthTokenRepository;
use App\Domain\OAuth\UsedRefreshTokenRepository;
use App\Domain\Pds\Atproto\AppView\AppViewClient;
use App\Domain\Repo\CarReader;
use App\Domain\Repo\CarWriter;
use App\Domain\Repo\DagCborDecoder;
use App\Domain\Repo\DagCborEncoder;
use App\Domain\Repo\RepoRootRepository;
use App\Domain\Sequencer\SequencerRepository;
use App\Infrastructure\Atproto\AppView\GuzzleAppViewClient;
use App\Infrastructure\Persistence\Account\AppPassword\InMemoryAppPasswordRepository;
use App\Infrastructure\Persistence\Account\EmailToken\InMemoryEmailTokenRepository;
use App\Infrastructure\Persistence\Account\InMemoryAccountRepository;
use App\Infrastructure\Persistence\Account\InviteCode\InMemoryInviteCodeRepository;
use App\Infrastructure\Persistence\Account\RefreshToken\InMemoryRefreshTokenRepository;
use App\Infrastructure\Persistence\Actor\InMemoryActorRepository;
use App\Infrastructure\Persistence\ActorStore\InMemoryActorStoreFactory;
use App\Infrastructure\Persistence\Did\InMemoryDidCacheRepository;
use App\Infrastructure\Persistence\Lexicon\InMemoryLexiconRepository;
use App\Infrastructure\Persistence\OAuth\InMemoryAccountDeviceRepository;
use App\Infrastructure\Persistence\OAuth\InMemoryAuthorizationRequestRepository;
use App\Infrastructure\Persistence\OAuth\InMemoryAuthorizedClientRepository;
use App\Infrastructure\Persistence\OAuth\InMemoryDeviceRepository;
use App\Infrastructure\Persistence\OAuth\InMemoryOAuthTokenRepository;
use App\Infrastructure\Persistence\OAuth\InMemoryUsedRefreshTokenRepository;
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
        AccountDeviceRepository::class => autowire(InMemoryAccountDeviceRepository::class),
        AccountRepository::class => autowire(InMemoryAccountRepository::class),
        ActorRepository::class => autowire(InMemoryActorRepository::class),
        ActorStoreFactory::class => autowire(InMemoryActorStoreFactory::class),
        AppPasswordRepository::class => autowire(InMemoryAppPasswordRepository::class),
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
        AuthorizationRequestRepository::class => autowire(InMemoryAuthorizationRequestRepository::class),
        AuthorizedClientRepository::class => autowire(InMemoryAuthorizedClientRepository::class),
        CarReader::class => autowire(NativeCarReader::class),
        CarWriter::class => autowire(NativeCarWriter::class),
        DagCborDecoder::class => autowire(NativeDagCborDecoder::class),
        DagCborEncoder::class => autowire(NativeDagCborEncoder::class),
        DeviceRepository::class => autowire(InMemoryDeviceRepository::class),
        DidCacheRepository::class => autowire(InMemoryDidCacheRepository::class),
        EmailTokenRepository::class => autowire(InMemoryEmailTokenRepository::class),
        InviteCodeRepository::class => autowire(InMemoryInviteCodeRepository::class),
        LexiconRepository::class => autowire(InMemoryLexiconRepository::class),
        OAuthTokenRepository::class => autowire(InMemoryOAuthTokenRepository::class),
        RefreshTokenRepository::class => autowire(InMemoryRefreshTokenRepository::class),
        RepoRootRepository::class => autowire(InMemoryRepoRootRepository::class),
        SequencerRepository::class => autowire(InMemorySequencerRepository::class),
        UsedRefreshTokenRepository::class => autowire(InMemoryUsedRefreshTokenRepository::class),
    ]);
};
