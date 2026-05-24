<?php

declare(strict_types=1);

namespace Tests\Domain\Account;

use App\Domain\Account\Exception\HandleNotAvailableException;
use App\Domain\Account\Exception\InvalidHandleException;
use App\Domain\Account\Exception\UnsupportedDomainException;
use App\Domain\Account\HandleValidator;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\TestCase;

class HandleValidatorTest extends TestCase
{
    /** @var ObjectProphecy<ActorRepository> */
    private ObjectProphecy $actors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actors = $this->prophesize(ActorRepository::class);
    }

    private function validator(array $availableUserDomains = ['.pds.test']): HandleValidator
    {
        return new HandleValidator($this->actors->reveal(), $availableUserDomains);
    }

    private function existingActor(string $handle): Actor
    {
        return new Actor(
            did: 'did:plc:existing',
            handle: $handle,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validHandleProvider(): array
    {
        return [
            'simple two-label handle' => ['alice.pds.test'],
            'hyphen in middle label' => ['alice-1.pds.test'],
            'long but valid label' => [str_repeat('a', 63) . '.pds.test'],
            'mixed labels and numbers' => ['u2.example123.net'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidHandleProvider(): array
    {
        return [
            'empty string' => [''],
            'single label' => ['alice'],
            'label starts with hyphen' => ['-alice.pds.test'],
            'label ends with hyphen' => ['alice-.pds.test'],
            'invalid underscore character' => ['alice_name.pds.test'],
            'uppercase not allowed' => ['Alice.pds.test'],
            'numeric tld is invalid' => ['alice.example.123'],
            'double dot empty label' => ['alice..pds.test'],
        ];
    }

    #[DataProvider('validHandleProvider')]
    public function testIsSyntacticallyValidAcceptsWellFormedHandles(string $handle): void
    {
        $this->assertTrue(HandleValidator::isSyntacticallyValid($handle));
    }

    #[DataProvider('invalidHandleProvider')]
    public function testIsSyntacticallyValidRejectsMalformedHandles(string $handle): void
    {
        $this->assertFalse(HandleValidator::isSyntacticallyValid($handle));
    }

    public function testValidateForRegistrationNormalizesAndReturnsHandle(): void
    {
        $this->actors->findActorByHandle('alice.pds.test')
            ->willThrow(new ActorNotFoundException())
            ->shouldBeCalledOnce();

        $result = $this->validator()->validateForRegistration('  ALICE.PDS.TEST  ');

        $this->assertSame('alice.pds.test', $result);
    }

    public function testValidateForRegistrationRejectsUnsupportedDomain(): void
    {
        $this->actors->findActorByHandle('alice.other.test')->shouldNotBeCalled();

        $this->expectException(UnsupportedDomainException::class);
        $this->validator(['.pds.test'])->validateForRegistration('alice.other.test');
    }

    public function testValidateForRegistrationRejectsNestedSubdomain(): void
    {
        $this->actors->findActorByHandle('alice.dev.pds.test')->shouldNotBeCalled();

        $this->expectException(InvalidHandleException::class);
        $this->validator()->validateForRegistration('alice.dev.pds.test');
    }

    public function testValidateForRegistrationRejectsReservedSubdomain(): void
    {
        $this->actors->findActorByHandle('admin.pds.test')->shouldNotBeCalled();

        $this->expectException(HandleNotAvailableException::class);
        $this->validator()->validateForRegistration('Admin.pds.test');
    }

    public function testValidateForRegistrationRejectsAlreadyTakenHandle(): void
    {
        $this->actors->findActorByHandle('alice.pds.test')
            ->willReturn($this->existingActor('alice.pds.test'))
            ->shouldBeCalledOnce();

        $this->expectException(HandleNotAvailableException::class);
        $this->validator()->validateForRegistration('alice.pds.test');
    }

    public function testValidateForRegistrationRejectsSyntacticallyInvalidHandleBeforeLookup(): void
    {
        $this->actors->findActorByHandle('bad_handle.pds.test')->shouldNotBeCalled();

        $this->expectException(InvalidHandleException::class);
        $this->validator()->validateForRegistration('bad_handle.pds.test');
    }
}
