<?php

declare(strict_types=1);

namespace Tests\Domain\Account\Auth;

use App\Domain\Account\Account;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\AccountRepository;
use App\Domain\Account\AccountTakedownException;
use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Account\AppPassword\AppPasswordRepository;
use App\Domain\Account\Auth\InvalidCredentialsException;
use App\Domain\Account\Auth\RepositoryAccountAuthenticator;
use App\Domain\Account\Password\PasswordHasher;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use DateTimeImmutable;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\TestCase;

class RepositoryAccountAuthenticatorTest extends TestCase
{
    /** @var ObjectProphecy<AccountRepository> */
    private ObjectProphecy $accounts;
    /** @var ObjectProphecy<AppPasswordRepository> */
    private ObjectProphecy $appPasswords;
    /** @var ObjectProphecy<ActorRepository> */
    private ObjectProphecy $actors;
    /** @var ObjectProphecy<PasswordHasher> */
    private ObjectProphecy $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts     = $this->prophesize(AccountRepository::class);
        $this->appPasswords = $this->prophesize(AppPasswordRepository::class);
        $this->actors       = $this->prophesize(ActorRepository::class);
        $this->hasher       = $this->prophesize(PasswordHasher::class);
    }

    private function authenticator(): RepositoryAccountAuthenticator
    {
        return new RepositoryAccountAuthenticator(
            $this->accounts->reveal(),
            $this->appPasswords->reveal(),
            $this->actors->reveal(),
            $this->hasher->reveal(),
        );
    }

    private function account(): Account
    {
        return new Account(
            did: 'did:plc:alice',
            email: 'alice@example.com',
            passwordScrypt: 'stored-scrypt-hash',
            emailConfirmedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    private function actor(?string $takedownRef = null): Actor
    {
        return new Actor(
            did: 'did:plc:alice',
            handle: 'alice.pds.test',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            takedownRef: $takedownRef,
        );
    }

    public function testHandleIdentifierResolvesByHandle(): void
    {
        $this->accounts->findAccountByHandle('alice.pds.test')->willReturn($this->account())->shouldBeCalledOnce();
        $this->hasher->verify('hunter2', 'stored-scrypt-hash')->willReturn(true);
        $this->actors->findActorByDid('did:plc:alice')->willReturn($this->actor());

        $result = $this->authenticator()->login('alice.pds.test', 'hunter2');

        $this->assertSame('did:plc:alice', $result->account->getDid());
        $this->assertNull($result->appPassword);
        $this->assertFalse($result->isAppPasswordAuth());
    }

    public function testEmailIdentifierResolvesByEmail(): void
    {
        $this->accounts->findAccountByEmail('alice@example.com')
            ->willReturn($this->account())
            ->shouldBeCalledOnce();
        $this->hasher->verify('hunter2', 'stored-scrypt-hash')->willReturn(true);
        $this->actors->findActorByDid('did:plc:alice')->willReturn($this->actor());

        $this->authenticator()->login('alice@example.com', 'hunter2');
    }

    public function testDidIdentifierResolvesByDid(): void
    {
        $this->accounts->findAccountByDid('did:plc:alice')
            ->willReturn($this->account())
            ->shouldBeCalledOnce();
        $this->hasher->verify('hunter2', 'stored-scrypt-hash')->willReturn(true);
        $this->actors->findActorByDid('did:plc:alice')->willReturn($this->actor());

        $this->authenticator()->login('did:plc:alice', 'hunter2');
    }

    public function testWhitespaceAroundIdentifierIsTrimmed(): void
    {
        $this->accounts->findAccountByHandle('alice.pds.test')->willReturn($this->account());
        $this->hasher->verify('hunter2', 'stored-scrypt-hash')->willReturn(true);
        $this->actors->findActorByDid('did:plc:alice')->willReturn($this->actor());

        $result = $this->authenticator()->login('  alice.pds.test  ', 'hunter2');
        $this->assertSame('did:plc:alice', $result->account->getDid());
    }

    public function testEmptyIdentifierThrowsInvalidCredentials(): void
    {
        $this->accounts->findAccountByHandle(Argument::any())->shouldNotBeCalled();

        $this->expectException(InvalidCredentialsException::class);
        $this->authenticator()->login('   ', 'hunter2');
    }

    public function testUnknownAccountThrowsInvalidCredentials(): void
    {
        $this->accounts->findAccountByHandle('ghost.pds.test')->willThrow(new AccountNotFoundException());

        $this->expectException(InvalidCredentialsException::class);
        $this->authenticator()->login('ghost.pds.test', 'hunter2');
    }

    public function testWrongPasswordWithoutAppPasswordsThrowsInvalidCredentials(): void
    {
        $this->accounts->findAccountByHandle('alice.pds.test')->willReturn($this->account());
        $this->hasher->verify('wrong', 'stored-scrypt-hash')->willReturn(false);
        $this->appPasswords->findAllForDid('did:plc:alice')->willReturn([])->shouldBeCalledOnce();

        $this->expectException(InvalidCredentialsException::class);
        $this->authenticator()->login('alice.pds.test', 'wrong');
    }

    public function testAppPasswordIsTriedWhenMainPasswordFails(): void
    {
        $appPassword = new AppPassword(
            did: 'did:plc:alice',
            name: 'phone',
            passwordScrypt: 'app-scrypt-hash',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $this->accounts->findAccountByHandle('alice.pds.test')->willReturn($this->account());
        $this->hasher->verify('app-pass-123', 'stored-scrypt-hash')->willReturn(false)->shouldBeCalledOnce();
        $this->appPasswords->findAllForDid('did:plc:alice')->willReturn([$appPassword])->shouldBeCalledOnce();
        $this->hasher->verify('app-pass-123', 'app-scrypt-hash')->willReturn(true)->shouldBeCalledOnce();
        $this->actors->findActorByDid('did:plc:alice')->willReturn($this->actor());

        $result = $this->authenticator()->login('alice.pds.test', 'app-pass-123');

        $this->assertNotNull($result->appPassword);
        $this->assertTrue($result->isAppPasswordAuth());
        $this->assertSame('phone', $result->appPassword->getName());
    }

    public function testTakedownActorRaisesAccountTakedown(): void
    {
        $this->accounts->findAccountByHandle('alice.pds.test')->willReturn($this->account());
        $this->hasher->verify('hunter2', 'stored-scrypt-hash')->willReturn(true);
        $this->actors->findActorByDid('did:plc:alice')->willReturn($this->actor('ref-1'));

        $this->expectException(AccountTakedownException::class);
        $this->authenticator()->login('alice.pds.test', 'hunter2');
    }

    public function testMissingActorRowIsSynthesizedAsActiveButHandleless(): void
    {
        $this->accounts->findAccountByHandle('alice.pds.test')->willReturn($this->account());
        $this->hasher->verify('hunter2', 'stored-scrypt-hash')->willReturn(true);
        $this->actors->findActorByDid('did:plc:alice')->willThrow(new ActorNotFoundException());

        $result = $this->authenticator()->login('alice.pds.test', 'hunter2');

        $this->assertNull($result->actor->getHandle());
        $this->assertTrue($result->actor->isRepoActive());
    }
}
