<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\Atproto\Server\CreateAccountAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\Settings;
use App\Domain\Account\Account;
use App\Domain\Account\AccountCreator;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\AccountRepository;
use App\Domain\Account\HandleValidator;
use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\InviteCode\InviteCodeUse;
use App\Domain\Account\Password\PasswordHasher;
use App\Domain\Account\RefreshToken\RefreshToken;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use App\Domain\ActorStore\ActorStore;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Auth\AuthTokenIssuer;
use App\Domain\Auth\AuthTokenPair;
use App\Domain\Crypto\Keypair;
use App\Domain\Crypto\KeypairFactory;
use App\Domain\Crypto\SigningKeyRepository;
use App\Domain\Crypto\StoredSigningKey;
use App\Domain\Did\DidResolver;
use App\Domain\Did\PlcDirectoryClient;
use App\Domain\Repo\CarWriter;
use App\Domain\Repo\DagCborEncoder;
use App\Domain\Repo\RepoBlock;
use App\Domain\Repo\RepoBlockRepository;
use App\Domain\Repo\RepoInitializer;
use App\Domain\Repo\RepoRoot;
use App\Domain\Repo\RepoRootRepository;
use App\Domain\Sequencer\RepoSeqEvent;
use App\Domain\Sequencer\SequencerRepository;
use App\Domain\Sequencer\SubscribeReposEventFactory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

class CreateAccountActionTest extends TestCase
{
    /** @var ObjectProphecy<ActorRepository> */
    private ObjectProphecy $actors;
    /** @var ObjectProphecy<AccountRepository> */
    private ObjectProphecy $accounts;
    /** @var ObjectProphecy<InviteCodeRepository> */
    private ObjectProphecy $inviteCodes;
    /** @var ObjectProphecy<ActorStoreFactory> */
    private ObjectProphecy $actorStores;
    /** @var ObjectProphecy<KeypairFactory> */
    private ObjectProphecy $keypairs;
    /** @var ObjectProphecy<PasswordHasher> */
    private ObjectProphecy $passwords;
    /** @var ObjectProphecy<PlcDirectoryClient> */
    private ObjectProphecy $plc;
    /** @var ObjectProphecy<DidResolver> */
    private ObjectProphecy $didResolver;
    /** @var ObjectProphecy<DagCborEncoder> */
    private ObjectProphecy $repoEncoder;
    /** @var ObjectProphecy<DagCborEncoder> */
    private ObjectProphecy $eventEncoder;
    /** @var ObjectProphecy<CarWriter> */
    private ObjectProphecy $carWriter;
    /** @var ObjectProphecy<SequencerRepository> */
    private ObjectProphecy $sequencer;
    /** @var ObjectProphecy<AuthTokenIssuer> */
    private ObjectProphecy $tokens;
    /** @var ObjectProphecy<RefreshTokenRepository> */
    private ObjectProphecy $refreshTokens;
    /** @var ObjectProphecy<Keypair> */
    private ObjectProphecy $plcRotationKey;
    /** @var ObjectProphecy<ActorStore> */
    private ObjectProphecy $actorStore;
    /** @var ObjectProphecy<SigningKeyRepository> */
    private ObjectProphecy $signingKeys;
    /** @var ObjectProphecy<RepoBlockRepository> */
    private ObjectProphecy $repoBlocks;
    /** @var ObjectProphecy<RepoRootRepository> */
    private ObjectProphecy $repoRoot;
    private RepoInitializer $repoInit;
    private SubscribeReposEventFactory $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actors = $this->prophesize(ActorRepository::class);
        $this->accounts = $this->prophesize(AccountRepository::class);
        $this->inviteCodes = $this->prophesize(InviteCodeRepository::class);
        $this->actorStores = $this->prophesize(ActorStoreFactory::class);
        $this->keypairs = $this->prophesize(KeypairFactory::class);
        $this->passwords = $this->prophesize(PasswordHasher::class);
        $this->plc = $this->prophesize(PlcDirectoryClient::class);
        $this->didResolver = $this->prophesize(DidResolver::class);
        $this->repoEncoder = $this->prophesize(DagCborEncoder::class);
        $this->eventEncoder = $this->prophesize(DagCborEncoder::class);
        $this->carWriter = $this->prophesize(CarWriter::class);
        $this->sequencer = $this->prophesize(SequencerRepository::class);
        $this->tokens = $this->prophesize(AuthTokenIssuer::class);
        $this->refreshTokens = $this->prophesize(RefreshTokenRepository::class);
        $this->plcRotationKey = $this->prophesize(Keypair::class);
        $this->actorStore = $this->prophesize(ActorStore::class);
        $this->signingKeys = $this->prophesize(SigningKeyRepository::class);
        $this->repoBlocks = $this->prophesize(RepoBlockRepository::class);
        $this->repoRoot = $this->prophesize(RepoRootRepository::class);

        $this->repoInit = new RepoInitializer($this->repoEncoder->reveal());
        $this->events = new SubscribeReposEventFactory(
            $this->eventEncoder->reveal(),
            $this->carWriter->reveal(),
        );

        $this->actors->findActorByHandle('alice.pds.test')->willThrow(new ActorNotFoundException());
        $this->accounts->findAccountByEmail('alice@example.com')->willThrow(new AccountNotFoundException());
        $this->actorStore->getSigningKeys()->willReturn($this->signingKeys->reveal());
        $this->actorStore->getRepoBlocks()->willReturn($this->repoBlocks->reveal());
        $this->actorStore->getRepoRoot()->willReturn($this->repoRoot->reveal());
    }

    public static function missingRequiredFieldProvider(): array
    {
        return [
            'missing handle' => [['email' => 'alice@example.com', 'password' => 'hunter2'], 'handle'],
            'missing email' => [['handle' => 'alice.pds.test', 'password' => 'hunter2'], 'email'],
            'missing password' => [['handle' => 'alice.pds.test', 'email' => 'alice@example.com'], 'password'],
            'blank handle' => [['handle' => '   ', 'email' => 'alice@example.com', 'password' => 'hunter2'], 'handle'],
            'blank email' => [['handle' => 'alice.pds.test', 'email' => '   ', 'password' => 'hunter2'], 'email'],
            'blank password' => [
                ['handle' => 'alice.pds.test', 'email' => 'alice@example.com', 'password' => '   '],
                'password'
            ],
        ];
    }

    public static function invalidOptionalStringProvider(): array
    {
        return [
            'inviteCode must be string' => ['inviteCode', 123],
            'did must be string' => ['did', 123],
            'recoveryKey must be string' => ['recoveryKey', 123],
        ];
    }

    private function makeAccountCreator(): AccountCreator
    {
        return new AccountCreator(
            accounts: $this->accounts->reveal(),
            actors: $this->actors->reveal(),
            handleValidator: new HandleValidator($this->actors->reveal(), ['.pds.test']),
            inviteCodes: $this->inviteCodes->reveal(),
            actorStores: $this->actorStores->reveal(),
            keypairs: $this->keypairs->reveal(),
            passwordHasher: $this->passwords->reveal(),
            plc: $this->plc->reveal(),
            didResolver: $this->didResolver->reveal(),
            repoInitializer: $this->repoInit,
            sequencer: $this->sequencer->reveal(),
            events: $this->events,
            tokens: $this->tokens->reveal(),
            refreshTokens: $this->refreshTokens->reveal(),
            plcRotationKey: $this->plcRotationKey->reveal(),
            inviteRequired: true,
            hostname: 'pds.test',
        );
    }

    private function makeAction(): CreateAccountAction
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);

        return new CreateAccountAction(
            $logger,
            $settings,
            $this->makeAccountCreator(),
        );
    }

    private function tokenPair(): AuthTokenPair
    {
        return new AuthTokenPair(
            accessJwt: 'access.jwt.value',
            refreshJwt: 'refresh.jwt.value',
            refreshJti: 'jti-123',
            refreshExpiresAt: new DateTimeImmutable('2026-04-01T00:00:00Z'),
        );
    }

    private function existingAccount(string $did, string $email = 'alice@example.com'): Account
    {
        return new Account(
            did: $did,
            email: $email,
            passwordScrypt: 'stored-scrypt',
        );
    }

    private function inviteCode(string $code = 'invite-123', int $availableUses = 1, bool $disabled = false): InviteCode
    {
        return new InviteCode(
            code: $code,
            availableUses: $availableUses,
            disabled: $disabled,
            forAccount: 'did:plc:inviter',
            createdBy: 'did:plc:inviter',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    private function configureValidInvite(string $code = 'invite-123'): void
    {
        $this->inviteCodes->findByCode($code)->willReturn($this->inviteCode($code, 2, false))->shouldBeCalledOnce();
        $this->inviteCodes->findUsesForCode($code)->willReturn([])->shouldBeCalledOnce();
    }

    private function configureSuccessfulDidWebAccountCreation(): void
    {
        $did = 'did:web:alice.pds.test';
        $didDoc = ['id' => $did, '@context' => ['https://www.w3.org/ns/did/v1']];
        $signingKey = $this->prophesize(Keypair::class);

        $this->configureValidInvite();
        $this->didResolver->resolve($did)->willReturn($didDoc)->shouldBeCalledTimes(2);
        $this->keypairs->generate()->willReturn($signingKey->reveal())->shouldBeCalledOnce();
        $this->accounts->findAccountByDid($did)->willThrow(new AccountNotFoundException())->shouldBeCalledOnce();
        $this->passwords->hash('hunter2')->willReturn('hashed-password')->shouldBeCalledOnce();
        $this->actorStores->get($did)->willReturn($this->actorStore->reveal())->shouldBeCalledOnce();

        $signingKey->getCurveName()->willReturn('secp256k1')->shouldBeCalledOnce();
        $signingKey->export()->willReturn('private-key-bytes')->shouldBeCalledOnce();
        $signingKey->getDidKey()->willReturn('did:key:signing')->shouldBeCalledOnce();
        $signingKey->sign(Argument::type('string'))->willReturn('repo-signature')->shouldBeCalledOnce();

        $this->signingKeys->save(Argument::that(function (StoredSigningKey $key): bool {
            return $key->getCurve() === 'secp256k1'
                && $key->getPrivateKey() === 'private-key-bytes'
                && $key->getDidKey() === 'did:key:signing';
        }))->shouldBeCalledOnce();

        $this->actors->save(Argument::that(function (Actor $actor) use ($did): bool {
            return $actor->getDid() === $did
                && $actor->getHandle() === 'alice.pds.test';
        }))->shouldBeCalledOnce();

        $this->accounts->save(Argument::that(function (Account $account) use ($did): bool {
            return $account->getDid() === $did
                && $account->getEmail() === 'alice@example.com'
                && $account->getPasswordScrypt() === 'hashed-password';
        }))->shouldBeCalledOnce();

        $this->inviteCodes->recordUse(Argument::that(function ($use) use ($did): bool {
            return $use instanceof InviteCodeUse
                && $use->getCode() === 'invite-123'
                && $use->getUsedBy() === $did;
        }))->shouldBeCalledOnce();

        $this->repoEncoder->encode(Argument::type('array'))->willReturn('mst-bytes', 'commit-bytes');
        $this->repoBlocks->save(Argument::type(RepoBlock::class))->shouldBeCalledTimes(2);
        $this->repoRoot->upsert(Argument::type(RepoRoot::class))->shouldBeCalledOnce();

        $this->carWriter->write(Argument::type('array'), Argument::type('array'))
            ->willReturn('car-bytes')
            ->shouldBeCalledOnce();
        $this->eventEncoder->encode(Argument::type('array'))
            ->willReturn('commit-event', 'identity-event', 'account-event');
        $this->sequencer->append($did, RepoSeqEvent::TYPE_APPEND, 'commit-event')->shouldBeCalledOnce();
        $this->sequencer->append($did, RepoSeqEvent::TYPE_IDENTITY, 'identity-event')->shouldBeCalledOnce();
        $this->sequencer->append($did, RepoSeqEvent::TYPE_ACCOUNT, 'account-event')->shouldBeCalledOnce();

        $this->tokens->issue($did, AuthTokenIssuer::SCOPE_ACCESS, null)
            ->willReturn($this->tokenPair())
            ->shouldBeCalledOnce();
        $this->refreshTokens->save(Argument::that(function (RefreshToken $token) use ($did): bool {
            return $token->getId() === 'jti-123'
                && $token->getDid() === $did
                && $token->getAppPasswordName() === null
                && str_starts_with($token->getExpiresAt(), '2026-04-01');
        }))->shouldBeCalledOnce();
    }

    /** @param mixed $body */
    private function invoke(mixed $body): ResponseInterface
    {
        $request = $this->createRequest('POST', '/xrpc/com.atproto.server.createAccount')
            ->withParsedBody($body);
        $response = (new ResponseFactory())->createResponse();

        return ($this->makeAction())($request, $response, []);
    }

    private function assertXrpcException(callable $callback, string $error, ?string $messageContains = null): void
    {
        try {
            $callback();
            $this->fail('Expected XrpcException');
        } catch (XrpcException $e) {
            $this->assertSame($error, $e->getError());
            $this->assertSame(400, $e->getStatusCode());
            if ($messageContains !== null) {
                $this->assertStringContainsString($messageContains, $e->getMessage());
            }
        }
    }

    public function testSuccessfulCreateAccountReturnsResponsePayload(): void
    {
        $this->configureSuccessfulDidWebAccountCreation();

        $response = $this->invoke([
            'handle' => 'alice.pds.test',
            'email' => 'alice@example.com',
            'password' => 'hunter2',
            'inviteCode' => 'invite-123',
            'did' => 'did:web:alice.pds.test',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('access.jwt.value', $payload['accessJwt']);
        $this->assertSame('refresh.jwt.value', $payload['refreshJwt']);
        $this->assertSame('alice.pds.test', $payload['handle']);
        $this->assertSame('did:web:alice.pds.test', $payload['did']);
        $this->assertSame('did:web:alice.pds.test', $payload['didDoc']['id']);
    }

    public function testRequestBodyMustBeAJsonObject(): void
    {
        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke((object) ['foo' => 'bar']),
            'InvalidRequest',
            'Request body must be a JSON object'
        );
    }

    #[DataProvider('missingRequiredFieldProvider')]
    public function testMissingRequiredFieldsAreRejected(array $body, string $field): void
    {
        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke($body),
            'InvalidRequest',
            $field
        );
    }

    #[DataProvider('invalidOptionalStringProvider')]
    public function testOptionalScalarFieldsMustBeStrings(string $field, mixed $value): void
    {
        $body = [
            'handle' => 'alice.pds.test',
            'email' => 'alice@example.com',
            'password' => 'hunter2',
            $field => $value,
        ];

        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke($body),
            'InvalidRequest',
            $field . ' must be a string'
        );
    }

    public function testPlcOpMustBeAnObject(): void
    {
        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'alice.pds.test',
                'email' => 'alice@example.com',
                'password' => 'hunter2',
                'plcOp' => 'not-an-object',
            ]),
            'InvalidRequest',
            'plcOp must be an object'
        );
    }

    public function testInvalidEmailFromAccountCreatorIsMappedToXrpcError(): void
    {
        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'alice.pds.test',
                'email' => 'bad-email',
                'password' => 'hunter2',
            ]),
            'InvalidEmail'
        );
    }

    public function testInvalidHandleFromAccountCreatorIsMappedToXrpcError(): void
    {
        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'bad_handle.pds.test',
                'email' => 'alice@example.com',
                'password' => 'hunter2',
            ]),
            'InvalidHandle'
        );
    }

    public function testUnsupportedDomainFromAccountCreatorIsMappedToXrpcError(): void
    {
        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'alice.other.test',
                'email' => 'alice@example.com',
                'password' => 'hunter2',
            ]),
            'UnsupportedDomain'
        );
    }

    public function testInvalidInviteCodeFromAccountCreatorIsMappedToXrpcError(): void
    {
        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'alice.pds.test',
                'email' => 'alice@example.com',
                'password' => 'hunter2',
            ]),
            'InvalidInviteCode'
        );
    }

    public function testEmailAlreadyTakenFromAccountCreatorIsMappedToXrpcError(): void
    {
        $this->accounts->findAccountByEmail('alice@example.com')
            ->willReturn($this->existingAccount('did:plc:alice'))
            ->shouldBeCalledOnce();

        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'alice.pds.test',
                'email' => 'alice@example.com',
                'password' => 'hunter2',
            ]),
            'EmailAlreadyTaken'
        );
    }

    public function testHandleNotAvailableFromAccountCreatorIsMappedToXrpcError(): void
    {
        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'admin.pds.test',
                'email' => 'alice@example.com',
                'password' => 'hunter2',
            ]),
            'HandleNotAvailable'
        );
    }

    public function testAccountAlreadyExistsFromAccountCreatorIsMappedToXrpcError(): void
    {
        $this->configureValidInvite();
        $this->didResolver->resolve('did:web:alice.pds.test')
            ->willReturn(['id' => 'did:web:alice.pds.test'])
            ->shouldBeCalledOnce();
        $this->keypairs->generate()->willReturn($this->prophesize(Keypair::class)->reveal())->shouldBeCalledOnce();
        $this->accounts->findAccountByDid('did:web:alice.pds.test')
            ->willReturn($this->existingAccount('did:web:alice.pds.test'))
            ->shouldBeCalledOnce();

        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'alice.pds.test',
                'email' => 'alice@example.com',
                'password' => 'hunter2',
                'inviteCode' => 'invite-123',
                'did' => 'did:web:alice.pds.test',
            ]),
            'AccountAlreadyExists'
        );
    }

    public function testInvalidDidFromAccountCreatorIsMappedToInvalidRequest(): void
    {
        $this->configureValidInvite();

        $this->assertXrpcException(
            fn (): ResponseInterface => $this->invoke([
                'handle' => 'alice.pds.test',
                'email' => 'alice@example.com',
                'password' => 'hunter2',
                'inviteCode' => 'invite-123',
                'did' => 'not-a-did',
            ]),
            'InvalidRequest',
            'Invalid DID'
        );
    }

    public function testPasswordLengthStillFailsBeforeInvokingAccountCreator(): void
    {
        $this->expectException(XrpcException::class);
        $this->expectExceptionMessage('password too long');

        $this->invoke([
            'handle' => 'alice.pds.test',
            'email' => 'alice@example.com',
            'password' => str_repeat('a', CreateAccountAction::MAX_PASSWORD_LENGTH + 1),
        ]);
    }
}
