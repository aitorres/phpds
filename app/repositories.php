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
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Database\Schema\DidCacheSchema;
use App\Infrastructure\Database\Schema\SequencerSchema;
use App\Infrastructure\Persistence\Account\AppPassword\SqliteAppPasswordRepository;
use App\Infrastructure\Persistence\Account\EmailToken\SqliteEmailTokenRepository;
use App\Infrastructure\Persistence\Account\InviteCode\SqliteInviteCodeRepository;
use App\Infrastructure\Persistence\Account\RefreshToken\SqliteRefreshTokenRepository;
use App\Infrastructure\Persistence\Account\SqliteAccountRepository;
use App\Infrastructure\Persistence\Actor\SqliteActorRepository;
use App\Infrastructure\Persistence\ActorStore\SqliteActorStoreFactory;
use App\Infrastructure\Persistence\Did\SqliteDidCacheRepository;
use App\Infrastructure\Persistence\Lexicon\SqliteLexiconRepository;
use App\Infrastructure\Persistence\OAuth\SqliteAccountDeviceRepository;
use App\Infrastructure\Persistence\OAuth\SqliteAuthorizationRequestRepository;
use App\Infrastructure\Persistence\OAuth\SqliteAuthorizedClientRepository;
use App\Infrastructure\Persistence\OAuth\SqliteDeviceRepository;
use App\Infrastructure\Persistence\OAuth\SqliteOAuthTokenRepository;
use App\Infrastructure\Persistence\OAuth\SqliteUsedRefreshTokenRepository;
use App\Infrastructure\Persistence\Repo\SqliteRepoRootRepository;
use App\Infrastructure\Persistence\Sequencer\SqliteSequencerRepository;
use App\Infrastructure\Repo\NativeCarReader;
use App\Infrastructure\Repo\NativeCarWriter;
use App\Infrastructure\Repo\NativeDagCborDecoder;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use DI\ContainerBuilder;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Container\ContainerInterface;

use function DI\autowire;

require_once __DIR__ . '/constants.php';

/**
 * @param ContainerInterface $c
 * @return array{accountDb:string,sequencerDb:string,didCacheDb:string,actorStoreDir:string,blobstoreDir:string}
 */
$dbSettings = static function (ContainerInterface $c): array {
    $settingsService = $c->get(SettingsInterface::class);
    assert($settingsService instanceof SettingsInterface);
    /** @var array{accountDb:string,sequencerDb:string,didCacheDb:string,actorStoreDir:string,blobstoreDir:string}|null $settings */
    $settings = $settingsService->get('database');
    if (!is_array($settings)) {
        throw new \RuntimeException('Database settings are missing.');
    }
    return $settings;
};

/**
 * @param ContainerInterface $c
 * @param string $key
 * @return Database
 */
$getDb = static function (ContainerInterface $c, string $key): Database {
    $db = $c->get($key);
    assert($db instanceof Database);
    return $db;
};

return function (ContainerBuilder $containerBuilder) use ($dbSettings, $getDb) {
    $containerBuilder->addDefinitions([
        // Database singletons
        DB_ACCOUNT => function (ContainerInterface $c) use ($dbSettings): Database {
            $db = new Database($dbSettings($c)['accountDb']);
            AccountSchema::apply($db);
            return $db;
        },
        DB_SEQUENCER => function (ContainerInterface $c) use ($dbSettings): Database {
            $db = new Database($dbSettings($c)['sequencerDb']);
            SequencerSchema::apply($db);
            return $db;
        },
        DB_DID_CACHE => function (ContainerInterface $c) use ($dbSettings): Database {
            $db = new Database($dbSettings($c)['didCacheDb']);
            DidCacheSchema::apply($db);
            return $db;
        },

        // Clients and services
        AppViewClient::class => function (ContainerInterface $container): AppViewClient {
            $settings = $container->get(SettingsInterface::class);
            assert($settings instanceof SettingsInterface);
            /** @var array{bskyAppViewUrl?: string} $pdsSettings */
            $pdsSettings = $settings->get('pds');
            $baseUrl = $pdsSettings['bskyAppViewUrl']
                ?? throw new \RuntimeException('PDS_BSKY_APP_VIEW_URL is required to use the AppView client.');

            $httpClient = new GuzzleClient([
                'base_uri' => rtrim($baseUrl, '/') . '/',
                'timeout' => 10.0,
                'headers' => ['Accept' => 'application/json'],
            ]);

            return new GuzzleAppViewClient($httpClient);
        },
        AccountRepository::class => fn (ContainerInterface $c): SqliteAccountRepository =>
            new SqliteAccountRepository($getDb($c, DB_ACCOUNT)),
        AccountDeviceRepository::class => fn (ContainerInterface $c): SqliteAccountDeviceRepository =>
            new SqliteAccountDeviceRepository($getDb($c, DB_ACCOUNT)),
        ActorRepository::class => fn (ContainerInterface $c): SqliteActorRepository =>
            new SqliteActorRepository($getDb($c, DB_ACCOUNT)),
        ActorStoreFactory::class => function (ContainerInterface $c) use ($dbSettings): SqliteActorStoreFactory {
            $settings = $dbSettings($c);
            return new SqliteActorStoreFactory(
                actorsDirectory: $settings['actorStoreDir'],
                blobsDirectory: $settings['blobstoreDir'],
            );
        },
        AuthorizationRequestRepository::class => fn (ContainerInterface $c): SqliteAuthorizationRequestRepository =>
            new SqliteAuthorizationRequestRepository($getDb($c, DB_ACCOUNT)),
        AuthorizedClientRepository::class => fn (ContainerInterface $c): SqliteAuthorizedClientRepository =>
            new SqliteAuthorizedClientRepository($getDb($c, DB_ACCOUNT)),
        CarReader::class       => autowire(NativeCarReader::class),
        CarWriter::class       => autowire(NativeCarWriter::class),
        DagCborDecoder::class  => autowire(NativeDagCborDecoder::class),
        DagCborEncoder::class  => autowire(NativeDagCborEncoder::class),
        DeviceRepository::class => fn (ContainerInterface $c): SqliteDeviceRepository =>
            new SqliteDeviceRepository($getDb($c, DB_ACCOUNT)),
        DidCacheRepository::class => fn (ContainerInterface $c): SqliteDidCacheRepository =>
            new SqliteDidCacheRepository($getDb($c, DB_DID_CACHE)),
        AppPasswordRepository::class => fn (ContainerInterface $c): SqliteAppPasswordRepository =>
            new SqliteAppPasswordRepository($getDb($c, DB_ACCOUNT)),
        EmailTokenRepository::class => fn (ContainerInterface $c): SqliteEmailTokenRepository =>
            new SqliteEmailTokenRepository($getDb($c, DB_ACCOUNT)),
        InviteCodeRepository::class => fn (ContainerInterface $c): SqliteInviteCodeRepository =>
            new SqliteInviteCodeRepository($getDb($c, DB_ACCOUNT)),
        LexiconRepository::class => fn (ContainerInterface $c): SqliteLexiconRepository =>
            new SqliteLexiconRepository($getDb($c, DB_ACCOUNT)),
        OAuthTokenRepository::class => fn (ContainerInterface $c): SqliteOAuthTokenRepository =>
            new SqliteOAuthTokenRepository($getDb($c, DB_ACCOUNT)),
        RefreshTokenRepository::class => fn (ContainerInterface $c): SqliteRefreshTokenRepository =>
            new SqliteRefreshTokenRepository($getDb($c, DB_ACCOUNT)),
        RepoRootRepository::class => fn (ContainerInterface $c): SqliteRepoRootRepository =>
            new SqliteRepoRootRepository($getDb($c, DB_ACCOUNT)),
        SequencerRepository::class => fn (ContainerInterface $c): SqliteSequencerRepository =>
            new SqliteSequencerRepository($getDb($c, DB_SEQUENCER)),
        UsedRefreshTokenRepository::class => fn (ContainerInterface $c): SqliteUsedRefreshTokenRepository =>
            new SqliteUsedRefreshTokenRepository($getDb($c, DB_ACCOUNT)),
    ]);
};
