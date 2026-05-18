<?php

declare(strict_types=1);

namespace Tests\Application\Settings;

use App\Application\Settings\Settings;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function testGetWithoutKeyReturnsAllSettings(): void
    {
        $values = ['foo' => 'bar', 'baz' => 42];
        $settings = new Settings($values);

        $this->assertSame($values, $settings->get());
    }

    public function testGetWithKeyReturnsValue(): void
    {
        $settings = new Settings(['foo' => 'bar', 'baz' => 42]);

        $this->assertSame('bar', $settings->get('foo'));
        $this->assertSame(42, $settings->get('baz'));
    }

    public function testGetSupportsNestedAndNullValues(): void
    {
        $nested = ['a' => ['b' => 'c']];
        $settings = new Settings(['nested' => $nested, 'nullable' => null]);

        $this->assertSame($nested, $settings->get('nested'));
        $this->assertNull($settings->get('nullable'));
    }
}
