<?php

declare(strict_types=1);

namespace Tests\Domain\Account;

use App\Domain\Account\Account;
use App\Domain\Account\AccountCreator;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\AccountRepository;
use App\Domain\Account\Exception\AccountAlreadyExistsException;
use App\Domain\Account\Exception\EmailAlreadyTakenException;
use App\Domain\Account\Exception\InvalidEmailException;
use App\Domain\Account\Exception\InvalidInviteCodeException;
use App\Domain\Account\HandleValidator;
use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeNotFoundException;
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
use App\Domain\Did\PlcDirectoryClientException;
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
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\TestCase;

class AccountCreatorTest extends TestCase
{
    /** @var ObjectProphecy<AccountRepository> */
    private ObjectProphecy $accounts;
    /** @var ObjectProphecy<ActorRepository> */
    private ObjectProphecy $actors;
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

        $this->accounts = $this->prophesize(AccountRepository::class);
        $this->actors = $this->prophesize(ActorRepository::class);
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

    private function creator(bool $inviteRequired = true): AccountCreator
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
            inviteRequired: $inviteRequired,
            hostname: 'pds.test',
        );
    }

    private function tokenPair(): AuthTokenPair
    {
        return new AuthTokenPair(
            accessJwt: 'access.jwt.value',
            refreshJwt: 'refresh.jwt.value',
            refreshJti: 'jti-abc',
            refreshExpiresAt: new DateTimeImmutable('2026-04-01T00:00:00Z'),
        );
    }

    private function existingAccount(string $did = 'did:plc:existing', string $email = 'alice@example.com'): Account
    {
        return new Account(
            did: $did,
            email: $email,
            passwordScrypt: 'stored-scrypt',
        );
    }

    private function inviteCode(string $code = 'invite-123', int $availableUses = 2, bool $disabled = false): InviteCode
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
        $this->inviteCodes->findByCode($code)->willReturn($this->inviteCode($code))->shouldBeCalledOnce();
        $this->inviteCodes->findUsesForCode($code)->willReturn([])->shouldBeCalledOnce();
    }

    /** @param ObjectProphecy<Keypair> $signingKey */
    private function configurePersistenceForSuccess(
        string $did,
        ObjectProphecy $signingKey,
        bool $expectInviteUse = true
    ): void {
        $this->accounts->findAccountByDid($did)
            ->willThrow(new AccountNotFoundException())
            ->shouldBeCalledOnce();
        $this->passwords->hash('hunter2')->willReturn('hashed-password')->shouldBeCalledOnce();
        $this->actorStores->get($did)->willReturn($this->actorStore->reveal())->shouldBeCalledOnce();

        $this->actors->save(Argument::that(function (Actor $actor) use ($did): bool {
            return $actor->getDid() === $did && $actor->getHandle() === 'alice.pds.test';
        }))->shouldBeCalledOnce();
        $this->accounts->save(Argument::that(function (Account $account) use ($did): bool {
            return $account->getDid() === $did
                && $account->getEmail() === 'alice@example.com'
                && $account->getPasswordScrypt() === 'hashed-password';
        }))->shouldBeCalledOnce();
        if ($expectInviteUse) {
            $this->inviteCodes->recordUse(Argument::that(function ($use) use ($did): bool {
                return $use instanceof InviteCodeUse
                    && $use->getCode() === 'invite-123'
                    && $use->getUsedBy() === $did;
            }))->shouldBeCalledOnce();
        } else {
            $this->inviteCodes->recordUse(Argument::any())->shouldNotBeCalled();
        }

        $this->signingKeys->save(Argument::that(function (StoredSigningKey $key): bool {
            return $key->getCurve() === 'secp256k1'
                && $key->getPrivateKey() === 'private-key-bytes'
                && $key->getDidKey() === 'did:key:signing';
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
            return $token->getId() === 'jti-abc'
                && $token->getDid() === $did
                && $token->getAppPasswordName() === null
                && $token->getNextId() === null
                && str_starts_with($token->getExpiresAt(), '2026-04-01');
        }))->shouldBeCalledOnce();

        $signingKey->getCurveName()->willReturn('secp256k1')->shouldBeCalledOnce();
        $signingKey->export()->willReturn('private-key-bytes')->shouldBeCalledOnce();
        $signingKey->getDidKey()->willReturn('did:key:signing');
        $signingKey->sign(Argument::type('string'))->willReturn('repo-signature')->shouldBeCalledOnce();
    }

    public function testCreateValidatesHandleInsideAccountCreator(): void
    {
        $this->actors->findActorByHandle('alice.pds.test')
            ->willThrow(new ActorNotFoundException())
            ->shouldBeCalledOnce();
        $this->accounts->findAccountByEmail('alice@example.com')->shouldNotBeCalled();

        $this->expectException(InvalidEmailException::class);

        $this->creator()->create(
            handle: '  ALICE.PDS.TEST  ',
            email: 'not-an-email',
            password: 'hunter2',
            inviteCode: null,
        );
    }

    public function testCreateRejectsInvalidEmailBeforeRepositoryChecks(): void
    {
        $this->actors->findActorByHandle('alice.pds.test')
            ->willThrow(new ActorNotFoundException())
            ->shouldBeCalledOnce();
        $this->accounts->findAccountByEmail('bad-email')->shouldNotBeCalled();

        $this->expectException(InvalidEmailException::class);

        $this->creator()->create(
            handle: 'alice.pds.test',
            email: 'bad-email',
            password: 'hunter2',
            inviteCode: null,
        );
    }

    public function testCreateRequiresInviteCodeWhenConfigured(): void
    {
        $this->expectException(InvalidInviteCodeException::class);
        $this->expectExceptionMessage('Invite code required');

        $this->creator()->create('alice.pds.test', 'alice@example.com', 'hunter2', null);
    }

    public function testCreateRejectsUnknownInviteCode(): void
    {
        $this->inviteCodes->findByCode('invite-123')
            ->willThrow(new InviteCodeNotFoundException())
            ->shouldBeCalledOnce();

        $this->expectException(InvalidInviteCodeException::class);
        $this->expectExceptionMessage('Invite code not found');

        $this->creator()->create('alice.pds.test', 'alice@example.com', 'hunter2', 'invite-123');
    }

    public function testCreateRejectsDisabledInviteCode(): void
    {
        $this->inviteCodes->findByCode('invite-123')
            ->willReturn($this->inviteCode('invite-123', 2, true))
            ->shouldBeCalledOnce();

        $this->expectException(InvalidInviteCodeException::class);
        $this->expectExceptionMessage('Invite code is disabled');

        $this->creator()->create('alice.pds.test', 'alice@example.com', 'hunter2', 'invite-123');
    }

    public function testCreateRejectsExhaustedInviteCode(): void
    {
        $this->inviteCodes->findByCode('invite-123')
            ->willReturn($this->inviteCode('invite-123', 1, false))
            ->shouldBeCalledOnce();
        $this->inviteCodes->findUsesForCode('invite-123')
            ->willReturn([
                new InviteCodeUse('invite-123', 'did:plc:alice', new DateTimeImmutable('2026-01-01T00:00:00Z')),
            ])
            ->shouldBeCalledOnce();

        $this->expectException(InvalidInviteCodeException::class);
        $this->expectExceptionMessage('Invite code has no remaining uses');

        $this->creator()->create('alice.pds.test', 'alice@example.com', 'hunter2', 'invite-123');
    }

    public function testCreateRejectsEmailAlreadyTaken(): void
    {
        $this->accounts->findAccountByEmail('alice@example.com')
            ->willReturn($this->existingAccount())
            ->shouldBeCalledOnce();

        $this->expectException(EmailAlreadyTakenException::class);
        $this->expectExceptionMessage('Email already in use');

        $this->creator(false)->create('alice.pds.test', 'alice@example.com', 'hunter2', null);
    }

    public function testCreateRejectsInvalidProvidedDid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DID: not-a-did');

        $this->creator(false)->create('alice.pds.test', 'alice@example.com', 'hunter2', null, 'not-a-did');
    }

    public function testCreateRejectsUnsupportedDidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DID method: did:example:alice');

        $this->creator(false)->create('alice.pds.test', 'alice@example.com', 'hunter2', null, 'did:example:alice');
    }

    public function testCreateRejectsUnresolvableProvidedDid(): void
    {
        $this->didResolver->resolve('did:web:alice.pds.test')->willReturn(null)->shouldBeCalledOnce();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not resolve provided DID: did:web:alice.pds.test');

        $this->creator(false)->create('alice.pds.test', 'alice@example.com', 'hunter2', null, 'did:web:alice.pds.test');
    }

    public function testCreateWrapsPlcFailureForProvidedPlcOp(): void
    {
        $plcOp = ['type' => 'plc_operation'];
        $this->plc->submit('did:plc:alice', $plcOp)
            ->willThrow(new PlcDirectoryClientException('PLC submit failed'))
            ->shouldBeCalledOnce();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to submit caller-supplied plcOp: PLC submit failed');

        $this->creator(false)->create(
            'alice.pds.test',
            'alice@example.com',
            'hunter2',
            null,
            'did:plc:alice',
            $plcOp,
        );
    }

    public function testCreateRejectsExistingDid(): void
    {
        $signingKey = $this->prophesize(Keypair::class);
        $this->didResolver->resolve('did:web:alice.pds.test')
            ->willReturn(['id' => 'did:web:alice.pds.test'])
            ->shouldBeCalledOnce();
        $this->keypairs->generate()->willReturn($signingKey->reveal())->shouldBeCalledOnce();
        $this->accounts->findAccountByDid('did:web:alice.pds.test')
            ->willReturn($this->existingAccount('did:web:alice.pds.test'))
            ->shouldBeCalledOnce();

        $this->expectException(AccountAlreadyExistsException::class);
        $this->expectExceptionMessage('Account already exists for did:web:alice.pds.test');

        $this->creator(false)->create(
            'alice.pds.test',
            'alice@example.com',
            'hunter2',
            null,
            'did:web:alice.pds.test',
        );
    }

    public function testCreateWrapsPlcRegistrationFailureForNewDid(): void
    {
        $signingKey = $this->prophesize(Keypair::class);
        $op = ['type' => 'plc_operation'];

        $this->keypairs->generate()->willReturn($signingKey->reveal())->shouldBeCalledOnce();
        $this->plcRotationKey->getDidKey()->willReturn('did:key:rotation')->shouldBeCalledOnce();
        $signingKey->getDidKey()->willReturn('did:key:signing')->shouldBeCalledOnce();
        $this->plc->buildAndSignGenesisOp(
            ['did:key:rotation'],
            'did:key:signing',
            'alice.pds.test',
            'https://pds.test',
            $this->plcRotationKey->reveal(),
        )->willReturn($op)->shouldBeCalledOnce();
        $this->plc->didForOp($op)->willReturn('did:plc:newaccount')->shouldBeCalledOnce();
        $this->plc->submit('did:plc:newaccount', $op)
            ->willThrow(new PlcDirectoryClientException('PLC directory offline'))
            ->shouldBeCalledOnce();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to register new did:plc with directory: PLC directory offline');

        $this->creator(false)->create('alice.pds.test', 'alice@example.com', 'hunter2', null);
    }

    public function testCreateCanSucceedWithDidWebProvidedDid(): void
    {
        $did = 'did:web:alice.pds.test';
        $didDoc = ['id' => $did, '@context' => ['https://www.w3.org/ns/did/v1']];
        $signingKey = $this->prophesize(Keypair::class);

        $this->configureValidInvite();
        $this->didResolver->resolve($did)->willReturn($didDoc)->shouldBeCalledTimes(2);
        $this->keypairs->generate()->willReturn($signingKey->reveal())->shouldBeCalledOnce();
        $signingKey->getDidKey()->willReturn('did:key:signing');
        $this->configurePersistenceForSuccess($did, $signingKey);

        $result = $this->creator()->create(
            handle: 'alice.pds.test',
            email: 'alice@example.com',
            password: 'hunter2',
            inviteCode: 'invite-123',
            providedDid: $did,
        );

        $this->assertSame($did, $result['result']->getDid());
        $this->assertSame('alice.pds.test', $result['result']->getHandle());
        $this->assertSame($didDoc, $result['result']->getDidDoc());
        $this->assertSame('access.jwt.value', $result['tokens']->getAccessJwt());
        $this->assertSame('refresh.jwt.value', $result['tokens']->getRefreshJwt());
    }

    public function testCreateCanMintNewDidPlcAndUseRecoveryKey(): void
    {
        $did = 'did:plc:newaccount';
        $didDoc = ['id' => $did, '@context' => ['https://www.w3.org/ns/did/v1']];
        $signingKey = $this->prophesize(Keypair::class);
        $op = ['type' => 'plc_operation'];

        $this->configureValidInvite();
        $this->keypairs->generate()->willReturn($signingKey->reveal())->shouldBeCalledOnce();
        $this->plcRotationKey->getDidKey()->willReturn('did:key:rotation')->shouldBeCalledOnce();
        $signingKey->getDidKey()->willReturn('did:key:signing');
        $this->plc->buildAndSignGenesisOp(
            ['did:key:recovery', 'did:key:rotation'],
            'did:key:signing',
            'alice.pds.test',
            'https://pds.test',
            $this->plcRotationKey->reveal(),
        )->willReturn($op)->shouldBeCalledOnce();
        $this->plc->didForOp($op)->willReturn($did)->shouldBeCalledOnce();
        $this->plc->submit($did, $op)->shouldBeCalledOnce();
        $this->didResolver->resolve($did)->willReturn($didDoc)->shouldBeCalledOnce();
        $this->configurePersistenceForSuccess($did, $signingKey);

        $result = $this->creator()->create(
            handle: 'alice.pds.test',
            email: 'Alice@Example.com',
            password: 'hunter2',
            inviteCode: 'invite-123',
            recoveryKey: 'did:key:recovery',
        );

        $this->assertSame($did, $result['result']->getDid());
        $this->assertSame('alice.pds.test', $result['result']->getHandle());
        $this->assertSame($didDoc, $result['result']->getDidDoc());
        $this->assertSame('access.jwt.value', $result['tokens']->getAccessJwt());
    }

    public function testCreateSkipsInviteValidationWhenInviteIsNotRequired(): void
    {
        $did = 'did:web:alice.pds.test';
        $didDoc = ['id' => $did];
        $signingKey = $this->prophesize(Keypair::class);

        $this->inviteCodes->findByCode(Argument::any())->shouldNotBeCalled();
        $this->didResolver->resolve($did)->willReturn($didDoc)->shouldBeCalledTimes(2);
        $this->keypairs->generate()->willReturn($signingKey->reveal())->shouldBeCalledOnce();
        $signingKey->getDidKey()->willReturn('did:key:signing');
        $this->configurePersistenceForSuccess($did, $signingKey, false);

        $result = $this->creator(false)->create(
            handle: 'alice.pds.test',
            email: 'alice@example.com',
            password: 'hunter2',
            inviteCode: null,
            providedDid: $did,
        );

        $this->assertSame($did, $result['result']->getDid());
    }
}
