<?php

declare(strict_types=1);

namespace App\Domain\ActorStore;

/**
 * Factory that opens (or creates) the per-actor data store for a given DID.
 */
interface ActorStoreFactory
{
    /**
     * Return the ActorStore for $did, creating it on first access.
     */
    public function get(string $did): ActorStore;

    /**
     * Tear down and discard the ActorStore for $did (e.g. on account deletion).
     */
    public function destroy(string $did): void;
}
