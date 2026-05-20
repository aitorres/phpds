<?php

declare(strict_types=1);

namespace App\Domain\Account\InviteCode;

use App\Domain\Common\Base32;

/**
 * Generates atproto-style invite codes of the form `<host>-xxxxx-xxxxx`,
 * where `<host>` is the PDS hostname with `.`s replaced by `-`s and the
 * suffix is two five-character lowercase base32 (RFC 4648, no padding)
 * tokens.
 */
class InviteCodeGenerator
{
    public function __construct(private readonly string $hostname)
    {
    }

    public function generate(): string
    {
        return str_replace('.', '-', $this->hostname) . '-' . $this->randomToken();
    }

    private function randomToken(): string
    {
        $chars = '';
        for ($i = 0; $i < 10; $i++) {
            $chars .= Base32::ALPHABET[random_int(0, 31)];
        }
        return substr($chars, 0, 5) . '-' . substr($chars, 5, 5);
    }
}
