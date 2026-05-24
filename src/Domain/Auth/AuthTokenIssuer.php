<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Issues access/refresh JWT pairs for atproto session management.
 *
 * Tokens can be identified by their scope: an access JWT (`scope=com.atproto.access` or
 * `com.atproto.appPass`) and a refresh JWT (`scope=com.atproto.refresh`).
 */
interface AuthTokenIssuer
{
    public const SCOPE_ACCESS = 'com.atproto.access';
    public const SCOPE_APP_PASS = 'com.atproto.appPass';
    public const SCOPE_APP_PASS_PRIVILEGED = 'com.atproto.appPassPrivileged';
    public const SCOPE_REFRESH = 'com.atproto.refresh';

    /**
     * Mint a new (access, refresh) JWT pair for the given DID.
     *
     * @param string      $did             Account DID (becomes `sub` claim).
     * @param string      $accessScope     One of the SCOPE_* constants.
     * @param string|null $appPasswordName App-password identifier the
     *                                     session was bound to, or null
     *                                     when authenticated with the main
     *                                     account password.
     */
    public function issue(string $did, string $accessScope, ?string $appPasswordName = null): AuthTokenPair;
}
