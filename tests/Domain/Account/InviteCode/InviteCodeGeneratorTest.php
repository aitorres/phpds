<?php

declare(strict_types=1);

namespace Tests\Domain\Account\InviteCode;

use App\Domain\Account\InviteCode\InviteCodeGenerator;
use Tests\TestCase;

class InviteCodeGeneratorTest extends TestCase
{
    public function testGeneratedCodeStartsWithHostnameAndIsBase32Suffixed(): void
    {
        $generator = new InviteCodeGenerator('pds.example.com');
        $code = $generator->generate();

        $this->assertMatchesRegularExpression(
            '/^pds-example-com-[a-z2-7]{5}-[a-z2-7]{5}$/',
            $code
        );
    }

    public function testGeneratedCodesAreUniqueAcrossInvocations(): void
    {
        $generator = new InviteCodeGenerator('pds.test');
        $codes = [];
        for ($i = 0; $i < 25; $i++) {
            $codes[] = $generator->generate();
        }
        $this->assertCount(25, array_unique($codes));
    }
}
