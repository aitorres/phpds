<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds;

use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\Settings;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class PdsActionTest extends TestCase
{
    private function makeAction(): TestPdsAction
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);

        return new TestPdsAction($logger, $settings);
    }

    public function testRequireStringReturnsPresentString(): void
    {
        $this->assertSame(
            'alice.pds.test',
            $this->makeAction()->exposeRequireString(['identifier' => 'alice.pds.test'], 'identifier')
        );
    }

    public function testRequireStringRejectsMissingOrEmptyString(): void
    {
        $this->expectException(XrpcException::class);
        $this->expectExceptionMessage('Missing required key "identifier"');

        $this->makeAction()->exposeRequireString(['identifier' => ''], 'identifier');
    }

    public function testRequireStringCanTreatWhitespaceOnlyAsMissing(): void
    {
        $this->expectException(XrpcException::class);
        $this->expectExceptionMessage('Missing required key "handle"');

        $this->makeAction()->exposeRequireString(['handle' => '   '], 'handle');
    }

    public function testOptionalStringReturnsNullForMissingValue(): void
    {
        $this->assertNull($this->makeAction()->exposeOptionalString([], 'inviteCode'));
    }

    public function testOptionalStringCanTreatWhitespaceOnlyAsNull(): void
    {
        $this->assertNull($this->makeAction()->exposeOptionalString(['inviteCode' => '   '], 'inviteCode'));
    }

    public function testOptionalStringRejectsNonStringValues(): void
    {
        $this->expectException(XrpcException::class);
        $this->expectExceptionMessage('inviteCode must be a string');

        $this->makeAction()->exposeOptionalString(['inviteCode' => 123], 'inviteCode');
    }

    public function testRequireStringPreservesNonEmptyWhitespace(): void
    {
        $this->assertSame(
            '  hunter2  ',
            $this->makeAction()->exposeRequireString(['password' => '  hunter2  '], 'password')
        );
    }
}
