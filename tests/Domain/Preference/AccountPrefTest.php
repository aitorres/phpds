<?php

declare(strict_types=1);

namespace Tests\Domain\Preference;

use App\Domain\Preference\AccountPref;
use Tests\TestCase;

class AccountPrefTest extends TestCase
{
    public function testGetters(): void
    {
        $pref = new AccountPref(
            id: 1,
            name: 'app.bsky.actor.defs#savedFeedsPref',
            valueJson: '{"items":[]}',
        );

        $this->assertSame(1, $pref->getId());
        $this->assertSame('app.bsky.actor.defs#savedFeedsPref', $pref->getName());
        $this->assertSame('{"items":[]}', $pref->getValueJson());
    }

    public function testJsonSerialize(): void
    {
        $pref = new AccountPref(
            id: 1,
            name: 'app.bsky.actor.defs#savedFeedsPref',
            valueJson: '{"items":[]}',
        );

        $this->assertSame([
            'id'        => 1,
            'name'      => 'app.bsky.actor.defs#savedFeedsPref',
            'valueJson' => '{"items":[]}',
        ], $pref->jsonSerialize());
    }
}
