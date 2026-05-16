<?php

declare(strict_types=1);

namespace Tests\Domain\Pds\Atproto\Server;

use App\Domain\Pds\Atproto\Server\DescribeServerResponse;
use Tests\TestCase;

class DescribeServerResponseTest extends TestCase
{
    public function testJsonSerializeReturnsRequiredFieldsOnly(): void
    {
        $response = new DescribeServerResponse(
            did: 'did:web:localhost',
            inviteCodeRequired: true,
            availableUserDomains: ['.localhost'],
            termsOfServiceUrl: null,
            privacyPolicyUrl: null,
            email: null,
            phoneVerificationRequired: null
        );

        $this->assertSame([
            'did' => 'did:web:localhost',
            'inviteCodeRequired' => true,
            'availableUserDomains' => ['.localhost'],
        ], $response->jsonSerialize());
    }

    public function testJsonSerializeIncludesOptionalFieldsWhenPresent(): void
    {
        $response = new DescribeServerResponse(
            did: 'did:web:pds.example.com',
            inviteCodeRequired: false,
            availableUserDomains: ['.pds.example.com'],
            termsOfServiceUrl: 'https://pds.example.com/tos',
            privacyPolicyUrl: 'https://pds.example.com/privacy',
            email: 'hello@pds.example.com',
            phoneVerificationRequired: false
        );

        $this->assertSame([
            'did' => 'did:web:pds.example.com',
            'inviteCodeRequired' => false,
            'availableUserDomains' => ['.pds.example.com'],
            'phoneVerificationRequired' => false,
            'contact' => [
                'email' => 'hello@pds.example.com',
            ],
            'links' => [
                'termsOfService' => 'https://pds.example.com/tos',
                'privacyPolicy' => 'https://pds.example.com/privacy',
            ],
        ], $response->jsonSerialize());
    }
}