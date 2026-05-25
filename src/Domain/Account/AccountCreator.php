<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\Account\Exception\AccountAlreadyExistsException;
use App\Domain\Account\Exception\EmailAlreadyTakenException;
use App\Domain\Account\Exception\InvalidEmailException;
use App\Domain\Account\Exception\InvalidInviteCodeException;
use App\Domain\Account\InviteCode\InviteCodeNotFoundException;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\InviteCode\InviteCodeUse;
use App\Domain\Account\Password\PasswordHasher;
use App\Domain\Account\RefreshToken\RefreshToken;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorRepository;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Auth\AuthTokenIssuer;
use App\Domain\Auth\AuthTokenPair;
use App\Domain\Common\StringNormalizer;
use App\Domain\Crypto\Keypair;
use App\Domain\Crypto\KeypairFactory;
use App\Domain\Crypto\StoredSigningKey;
use App\Domain\Did\Did;
use App\Domain\Did\DidResolver;
use App\Domain\Did\PlcDirectoryClient;
use App\Domain\Did\PlcDirectoryClientException;
use App\Domain\Repo\RepoInitializer;
use App\Domain\Sequencer\RepoSeqEvent;
use App\Domain\Sequencer\SequencerRepository;
use App\Domain\Sequencer\SubscribeReposEventFactory;
use DateTimeImmutable;

/**
 * Orchestrates the end-to-end account-creation flow for
 * `com.atproto.server.createAccount`.
 */
final class AccountCreator
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly ActorRepository $actors,
        private readonly HandleValidator $handleValidator,
        private readonly InviteCodeRepository $inviteCodes,
        private readonly ActorStoreFactory $actorStores,
        private readonly KeypairFactory $keypairs,
        private readonly PasswordHasher $passwordHasher,
        private readonly PlcDirectoryClient $plc,
        private readonly DidResolver $didResolver,
        private readonly RepoInitializer $repoInitializer,
        private readonly SequencerRepository $sequencer,
        private readonly SubscribeReposEventFactory $events,
        private readonly AuthTokenIssuer $tokens,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly Keypair $plcRotationKey,
        private readonly bool $inviteRequired,
        private readonly string $hostname,
    ) {
    }

    /**
     * @param string|null $providedDid  caller-supplied DID (BYO)
     * @param array<string, mixed>|null $providedPlcOp  caller-supplied plcOp
     *
     * @return array{result: AccountCreationResult, tokens: AuthTokenPair}
     */
    public function create(
        string $handle,
        string $email,
        string $password,
        ?string $inviteCode,
        ?string $providedDid = null,
        ?array $providedPlcOp = null,
        ?string $recoveryKey = null,
    ): array {
        $handle = $this->handleValidator->validateForRegistration($handle);
        $email = $this->normalizeAndValidateEmail($email);

        $this->ensureEmailFree($email);
        $invite = $this->verifyInvite($inviteCode);

        [$did, $signingKey] = $this->establishIdentity(
            $handle,
            $providedDid,
            $providedPlcOp,
            $recoveryKey,
        );

        $this->ensureDidFree($did);

        $now = new DateTimeImmutable();

        $this->actors->save(new Actor(
            did: $did,
            handle: $handle,
            createdAt: $now,
        ));

        $this->accounts->save(new Account(
            did: $did,
            email: $email,
            passwordScrypt: $this->passwordHasher->hash($password),
            emailConfirmedAt: null,
            invitesDisabled: false,
        ));

        if ($invite !== null) {
            $this->inviteCodes->recordUse(new InviteCodeUse(
                code: $invite,
                usedBy: $did,
                usedAt: $now,
            ));
        }

        $store = $this->actorStores->get($did);

        $store->getSigningKeys()->save(new StoredSigningKey(
            curve: $signingKey->getCurveName(),
            privateKey: $signingKey->export(),
            didKey: $signingKey->getDidKey(),
            createdAt: $now,
        ));

        $init = $this->repoInitializer->initialize($did, $store, $signingKey);

        $this->sequenceEvents($did, $handle, $init, $now);

        $tokens = $this->tokens->issue($did, AuthTokenIssuer::SCOPE_ACCESS, null);
        $this->refreshTokens->save(new RefreshToken(
            id: $tokens->getRefreshJti(),
            did: $did,
            expiresAt: $tokens->getRefreshExpiresAt()->format(DATE_ATOM),
            appPasswordName: null,
            nextId: null,
        ));

        $didDoc = $this->didResolver->resolve($did);

        return [
            'result' => new AccountCreationResult($did, $handle, $didDoc),
            'tokens' => $tokens,
        ];
    }

    private function normalizeAndValidateEmail(string $email): string
    {
        $normalizedEmail = StringNormalizer::normalizeEmail($email);
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException('Invalid email address');
        }

        return $normalizedEmail;
    }

    private function verifyInvite(?string $inviteCode): ?string
    {
        if (!$this->inviteRequired) {
            return $inviteCode;
        }

        if ($inviteCode === null || $inviteCode === '') {
            throw new InvalidInviteCodeException('Invite code required');
        }

        try {
            $code = $this->inviteCodes->findByCode($inviteCode);
        } catch (InviteCodeNotFoundException) {
            throw new InvalidInviteCodeException('Invite code not found');
        }

        if ($code->isDisabled()) {
            throw new InvalidInviteCodeException('Invite code is disabled');
        }

        $uses = count($this->inviteCodes->findUsesForCode($code->getCode()));
        if ($uses >= $code->getAvailableUses()) {
            throw new InvalidInviteCodeException('Invite code has no remaining uses');
        }

        return $code->getCode();
    }

    private function ensureEmailFree(string $email): void
    {
        try {
            $this->accounts->findAccountByEmail($email);
        } catch (AccountNotFoundException) {
            return;
        }
        throw new EmailAlreadyTakenException('Email already in use');
    }

    private function ensureDidFree(string $did): void
    {
        try {
            $this->accounts->findAccountByDid($did);
        } catch (AccountNotFoundException) {
            return;
        }
        throw new AccountAlreadyExistsException("Account already exists for {$did}");
    }

    /**
     * @param array<string, mixed>|null $providedPlcOp
     * @return array{0: string, 1: Keypair}  [did, signingKey]
     */
    private function establishIdentity(
        string $handle,
        ?string $providedDid,
        ?array $providedPlcOp,
        ?string $recoveryKey,
    ): array {
        if ($providedDid !== null) {
            if (!Did::isValid($providedDid)) {
                throw new \InvalidArgumentException("Invalid DID: {$providedDid}");
            }

            if (str_starts_with($providedDid, 'did:plc:')) {
                if ($providedPlcOp !== null) {
                    try {
                        $this->plc->submit($providedDid, $providedPlcOp);
                    } catch (PlcDirectoryClientException $e) {
                        throw new \RuntimeException(
                            "Failed to submit caller-supplied plcOp: " . $e->getMessage(),
                            0,
                            $e,
                        );
                    }
                }

                $signingKey = $this->extractSigningKeyForExistingDid($providedDid);
                return [$providedDid, $signingKey];
            }

            if (str_starts_with($providedDid, 'did:web:')) {
                $signingKey = $this->extractSigningKeyForExistingDid($providedDid);
                return [$providedDid, $signingKey];
            }

            throw new \InvalidArgumentException("Unsupported DID method: {$providedDid}");
        }

        $signingKey = $this->keypairs->generate();

        $rotationKeys = [$this->plcRotationKey->getDidKey()];
        if ($recoveryKey !== null && $recoveryKey !== '') {
            array_unshift($rotationKeys, $recoveryKey);
        }

        $op  = $this->plc->buildAndSignGenesisOp(
            rotationKeys: $rotationKeys,
            signingKey: $signingKey->getDidKey(),
            handle: $handle,
            pdsEndpoint: 'https://' . $this->hostname,
            signer: $this->plcRotationKey,
        );
        $did = $this->plc->didForOp($op);

        try {
            $this->plc->submit($did, $op);
        } catch (PlcDirectoryClientException $e) {
            throw new \RuntimeException(
                'Failed to register new did:plc with directory: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        return [$did, $signingKey];
    }

    /**
     * When the caller brings their own DID, we don't generate a new signing
     * key; we expect the caller's DID document to already declare one
     * matching a key we hold. We provision a fresh signing key
     * (the BYO-did caller must have rotated their plcOp to include it
     * before calling).
     */
    private function extractSigningKeyForExistingDid(string $did): Keypair
    {
        // Resolve doc to surface a clear error early if the DID isn't usable.
        $doc = $this->didResolver->resolve($did);
        if ($doc === null) {
            throw new \RuntimeException("Could not resolve provided DID: {$did}");
        }
        return $this->keypairs->generate();
    }

    /**
     * @param array{commitCid: string, commitBytes: string, mstCid: string, mstBytes: string, rev: string} $init
     */
    private function sequenceEvents(string $did, string $handle, array $init, DateTimeImmutable $time): void
    {
        $blocks = [
            $init['commitCid'] => $init['commitBytes'],
            $init['mstCid']    => $init['mstBytes'],
        ];

        $commitPayload = $this->events->genesisCommit(
            did: $did,
            commitCid: $init['commitCid'],
            rev: $init['rev'],
            blocks: $blocks,
            time: $time,
        );
        $this->sequencer->append($did, RepoSeqEvent::TYPE_APPEND, $commitPayload);

        $identityPayload = $this->events->identity($did, $handle, $time);
        $this->sequencer->append($did, RepoSeqEvent::TYPE_IDENTITY, $identityPayload);

        $accountPayload = $this->events->account($did, true, $time, null);
        $this->sequencer->append($did, RepoSeqEvent::TYPE_ACCOUNT, $accountPayload);
    }
}
