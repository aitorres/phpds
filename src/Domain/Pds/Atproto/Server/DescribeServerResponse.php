<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Server;

use JsonSerializable;

class DescribeServerResponse implements JsonSerializable
{
    private string $did;

    private bool $inviteCodeRequired;

    /** @var list<string> */
    private array $availableUserDomains;

    private ?string $termsOfServiceUrl;

    private ?string $privacyPolicyUrl;

    private ?string $email;

    private ?bool $phoneVerificationRequired;

    /**
     * @param list<string> $availableUserDomains
     */
    public function __construct(
        string $did,
        bool $inviteCodeRequired,
        array $availableUserDomains,
        ?string $termsOfServiceUrl,
        ?string $privacyPolicyUrl,
        ?string $email,
        ?bool $phoneVerificationRequired
    ) {
        $this->inviteCodeRequired = $inviteCodeRequired;
        $this->phoneVerificationRequired = $phoneVerificationRequired;
        $this->availableUserDomains = $availableUserDomains;
        $this->termsOfServiceUrl = $termsOfServiceUrl;
        $this->privacyPolicyUrl = $privacyPolicyUrl;
        $this->email = $email;
        $this->did = $did;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $json = [
            'did' => $this->did,
            'inviteCodeRequired' => $this->inviteCodeRequired,
            'availableUserDomains' => $this->availableUserDomains,
        ];

        if ($this->phoneVerificationRequired !== null) {
            $json['phoneVerificationRequired'] = $this->phoneVerificationRequired;
        }

        if ($this->email) {
            $json['contact'] = ['email' => $this->email];
        }

        if ($this->termsOfServiceUrl || $this->privacyPolicyUrl) {
            $json['links'] = [];

            if ($this->termsOfServiceUrl) {
                $json['links']['termsOfService'] = $this->termsOfServiceUrl;
            }

            if ($this->privacyPolicyUrl) {
                $json['links']['privacyPolicy'] = $this->privacyPolicyUrl;
            }
        }

        return $json;
    }
}
