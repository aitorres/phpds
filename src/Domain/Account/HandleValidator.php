<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\Account\Exception\HandleNotAvailableException;
use App\Domain\Account\Exception\InvalidHandleException;
use App\Domain\Account\Exception\UnsupportedDomainException;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;

/**
 * Validates handles for use on this PDS.
 *
 * A handle must:
 *   - be syntactically valid per atproto spec (lowercased ASCII, labels of
 *     1..63 chars made of [a-z0-9-], no leading/trailing hyphen; total
 *     length 1..253; at least two labels);
 *   - end with one of the server's available user domains (the suffix
 *     `availableUserDomains` setting, each entry beginning with `.`);
 *   - not be already taken by another actor on this PDS;
 *   - not be in the reserved-handle list (e.g. "admin", "www").
 */
final class HandleValidator
{
    /** @var list<string> reserved handle subdomains */
    private const RESERVED_SUBDOMAINS = [
        'admin', 'administrator', 'root', 'system',
        'www', 'web', 'host', 'localhost',
        'support', 'help', 'staff', 'about',
        'security', 'mail', 'email', 'noreply', 'no-reply',
        'bsky', 'atproto', 'phpds',
    ];

    /**
     * @param list<string> $availableUserDomains  e.g. [".example.com"]
     */
    public function __construct(
        private readonly ActorRepository $actors,
        private readonly array $availableUserDomains,
    ) {
    }

    /**
     * Returns the normalized handle on success.
     *
     * @throws InvalidHandleException
     * @throws UnsupportedDomainException
     * @throws HandleNotAvailableException
     */
    public function validateForRegistration(string $rawHandle): string
    {
        $handle = strtolower(trim($rawHandle));

        if (!self::isSyntacticallyValid($handle)) {
            throw new InvalidHandleException("Invalid handle: '{$rawHandle}'");
        }

        $matchedDomain = null;
        foreach ($this->availableUserDomains as $domain) {
            if (str_ends_with($handle, $domain)) {
                $matchedDomain = $domain;
                break;
            }
        }
        if ($matchedDomain === null) {
            throw new UnsupportedDomainException("Handle '{$handle}' is not under an available user domain");
        }

        $subdomain = substr($handle, 0, strlen($handle) - strlen($matchedDomain));
        if ($subdomain === '' || str_contains($subdomain, '.')) {
            throw new InvalidHandleException("Handle '{$handle}' must be a direct subdomain of '{$matchedDomain}'");
        }

        if (in_array($subdomain, self::RESERVED_SUBDOMAINS, true)) {
            throw new HandleNotAvailableException("Handle '{$handle}' is reserved");
        }

        try {
            $this->actors->findActorByHandle($handle);
            throw new HandleNotAvailableException("Handle '{$handle}' is already taken");
        } catch (ActorNotFoundException) {
            // available!
            return $handle;
        }
    }

    public static function isSyntacticallyValid(string $handle): bool
    {
        $len = strlen($handle);
        if ($len < 3 || $len > 253) {
            return false;
        }

        $labels = explode('.', $handle);
        if (count($labels) < 2) {
            return false;
        }

        foreach ($labels as $label) {
            $ll = strlen($label);
            if ($ll < 1 || $ll > 63) {
                return false;
            }
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
                return false;
            }
        }

        // TLD must not be purely numeric
        $tld = end($labels);
        if (preg_match('/^[0-9]+$/', $tld)) {
            return false;
        }

        return true;
    }
}
