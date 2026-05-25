<?php

/**
 * Container repository/service definitions.
 */

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Domain\Account\AccountRepository;
use App\Domain\Account\AppPassword\AppPasswordRepository;
use App\Domain\Account\Auth\AccountAuthenticator;
use App\Domain\Account\Auth\RepositoryAccountAuthenticator;
use App\Domain\Account\EmailToken\EmailTokenRepository;
use App\Domain\Account\HandleValidator;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\InviteCode\InviteCodeGenerator;
use App\Domain\Account\Password\PasswordHasher;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;
use App\Domain\Actor\ActorRepository;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Auth\AuthTokenIssuer;
use App\Domain\Crypto\Keypair;
use App\Domain\Crypto\KeypairFactory;
use App\Domain\Did\DidCacheRepository;
use App\Domain\Did\DidResolver;
use App\Domain\Did\PlcDirectoryClient;
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
use App\Domain\Repo\RepoInitializer;
use App\Domain\Repo\RepoRootRepository;
use App\Domain\Sequencer\SequencerRepository;
use App\Domain\Sequencer\SubscribeReposEventFactory;
use App\Infrastructure\Account\Password\ScryptPasswordHasher;
use App\Infrastructure\Atproto\AppView\HttpAppViewClient;
use App\Infrastructure\Auth\JwtAuthTokenIssuer;
use App\Infrastructure\Crypto\Secp256k1KeypairFactory;
use App\Infrastructure\Did\HttpPlcDirectoryClient;
use App\Infrastructure\Did\HttpDidResolver;
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
        'db.account' => function (ContainerInterface $c) use ($dbSettings): Database {
            $db = new Database($dbSettings($c)['accountDb']);
            AccountSchema::apply($db);
            return $db;
        },
        'db.sequencer' => function (ContainerInterface $c) use ($dbSettings): Database {
            $db = new Database($dbSettings($c)['sequencerDb']);
            SequencerSchema::apply($db);
            return $db;
        },
        'db.didCache' => function (ContainerInterface $c) use ($dbSettings): Database {
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

            return new HttpAppViewClient($httpClient);
        },
        AccountRepository::class => fn (ContainerInterface $c): SqliteAccountRepository =>
            new SqliteAccountRepository($getDb($c, 'db.account')),
        AccountAuthenticator::class => autowire(RepositoryAccountAuthenticator::class),
        AccountDeviceRepository::class => fn (ContainerInterface $c): SqliteAccountDeviceRepository =>
            new SqliteAccountDeviceRepository($getDb($c, 'db.account')),
        ActorRepository::class => fn (ContainerInterface $c): SqliteActorRepository =>
            new SqliteActorRepository($getDb($c, 'db.account')),
        ActorStoreFactory::class => function (ContainerInterface $c) use ($dbSettings): SqliteActorStoreFactory {
            $settings = $dbSettings($c);
            return new SqliteActorStoreFactory(
                actorsDirectory: $settings['actorStoreDir'],
                blobsDirectory: $settings['blobstoreDir'],
            );
        },
        AuthorizationRequestRepository::class => fn (ContainerInterface $c): SqliteAuthorizationRequestRepository =>
            new SqliteAuthorizationRequestRepository($getDb($c, 'db.account')),
        AuthorizedClientRepository::class => fn (ContainerInterface $c): SqliteAuthorizedClientRepository =>
            new SqliteAuthorizedClientRepository($getDb($c, 'db.account')),
        CarReader::class       => autowire(NativeCarReader::class),
        CarWriter::class       => autowire(NativeCarWriter::class),
        DagCborDecoder::class  => autowire(NativeDagCborDecoder::class),
        DagCborEncoder::class  => autowire(NativeDagCborEncoder::class),
        DeviceRepository::class => fn (ContainerInterface $c): SqliteDeviceRepository =>
            new SqliteDeviceRepository($getDb($c, 'db.account')),
        DidCacheRepository::class => fn (ContainerInterface $c): SqliteDidCacheRepository =>
            new SqliteDidCacheRepository($getDb($c, 'db.didCache')),
        DidResolver::class => function (ContainerInterface $c): HttpDidResolver {
            $settings = $c->get(SettingsInterface::class);
            assert($settings instanceof SettingsInterface);
            /** @var array{plcDirectoryUrl: string} $pdsSettings */
            $pdsSettings = $settings->get('pds');
            $httpClient = new GuzzleClient(['timeout' => 10.0]);
            $cache = $c->get(DidCacheRepository::class);
            assert($cache instanceof SqliteDidCacheRepository);
            return new HttpDidResolver($httpClient, $cache, $pdsSettings['plcDirectoryUrl']);
        },
        AppPasswordRepository::class => fn (ContainerInterface $c): SqliteAppPasswordRepository =>
            new SqliteAppPasswordRepository($getDb($c, 'db.account')),
        AuthTokenIssuer::class => function (ContainerInterface $c): JwtAuthTokenIssuer {
            $settings = $c->get(SettingsInterface::class);
            assert($settings instanceof SettingsInterface);
            /** @var array{hostname: string, jwtSecret: string} $pdsSettings */
            $pdsSettings = $settings->get('pds');
            return new JwtAuthTokenIssuer(
                secret: $pdsSettings['jwtSecret'],
                issuer: $pdsSettings['hostname'],
            );
        },
        EmailTokenRepository::class => fn (ContainerInterface $c): SqliteEmailTokenRepository =>
            new SqliteEmailTokenRepository($getDb($c, 'db.account')),
        HandleValidator::class => function (ContainerInterface $c): HandleValidator {
            $settings = $c->get(SettingsInterface::class);
            assert($settings instanceof SettingsInterface);
            /** @var array{hostname: string} $pdsSettings */
            $pdsSettings = $settings->get('pds');
            $actors = $c->get(ActorRepository::class);
            assert($actors instanceof ActorRepository);
            return new HandleValidator($actors, [".{$pdsSettings['hostname']}"]);
        },
        InviteCodeRepository::class => fn (ContainerInterface $c): SqliteInviteCodeRepository =>
            new SqliteInviteCodeRepository($getDb($c, 'db.account')),
        InviteCodeGenerator::class => function (ContainerInterface $c): InviteCodeGenerator {
            $settings = $c->get(SettingsInterface::class);
            assert($settings instanceof SettingsInterface);
            /** @var array{hostname: string} $pdsSettings */
            $pdsSettings = $settings->get('pds');
            return new InviteCodeGenerator($pdsSettings['hostname']);
        },
        Keypair::class => function (ContainerInterface $c): Keypair {
            $settings = $c->get(SettingsInterface::class);
            assert($settings instanceof SettingsInterface);
            /** @var array{plcRotationKeyHex: string} $pdsSettings */
            $pdsSettings = $settings->get('pds');
            $factory = $c->get(KeypairFactory::class);
            assert($factory instanceof KeypairFactory);
            return $factory->fromPrivateKeyHex($pdsSettings['plcRotationKeyHex']);
        },
        KeypairFactory::class => autowire(Secp256k1KeypairFactory::class),
        LexiconRepository::class => fn (ContainerInterface $c): SqliteLexiconRepository =>
            new SqliteLexiconRepository($getDb($c, 'db.account')),
        OAuthTokenRepository::class => fn (ContainerInterface $c): SqliteOAuthTokenRepository =>
            new SqliteOAuthTokenRepository($getDb($c, 'db.account')),
        PlcDirectoryClient::class => function (ContainerInterface $c): HttpPlcDirectoryClient {
            $settings = $c->get(SettingsInterface::class);
            assert($settings instanceof SettingsInterface);
            /** @var array{plcDirectoryUrl: string} $pdsSettings */
            $pdsSettings = $settings->get('pds');
            $http = new GuzzleClient(['timeout' => 15.0]);
            $cbor = $c->get(DagCborEncoder::class);
            assert($cbor instanceof DagCborEncoder);
            return new HttpPlcDirectoryClient($http, $cbor, $pdsSettings['plcDirectoryUrl']);
        },
        PasswordHasher::class => autowire(ScryptPasswordHasher::class),
        RefreshTokenRepository::class => fn (ContainerInterface $c): SqliteRefreshTokenRepository =>
            new SqliteRefreshTokenRepository($getDb($c, 'db.account')),
        RepoInitializer::class => autowire(RepoInitializer::class),
        RepoRootRepository::class => fn (ContainerInterface $c): SqliteRepoRootRepository =>
            new SqliteRepoRootRepository($getDb($c, 'db.account')),
        SequencerRepository::class => fn (ContainerInterface $c): SqliteSequencerRepository =>
            new SqliteSequencerRepository($getDb($c, 'db.sequencer')),
        SubscribeReposEventFactory::class => autowire(SubscribeReposEventFactory::class),
        UsedRefreshTokenRepository::class => fn (ContainerInterface $c): SqliteUsedRefreshTokenRepository =>
            new SqliteUsedRefreshTokenRepository($getDb($c, 'db.account')),
    ]);
};
