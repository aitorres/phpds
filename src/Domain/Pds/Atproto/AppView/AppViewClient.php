<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\AppView;

/**
 * Client for an atproto AppView (e.g. the Bluesky AppView at
 * https://api.bsky.app/).
 *
 * Implementations talk to the upstream service over XRPC; this interface
 * exposes only the operations the PDS actually needs.
 */
interface AppViewClient
{
    /**
     * Resolve a handle to a DID via the AppView.
     *
     * Wraps the `com.atproto.identity.resolveHandle` XRPC query.
     *
     * @throws AppViewException When the AppView returns a non-2xx response
     *                          or the response payload is malformed.
     */
    public function resolveHandle(string $handle): string;
}
