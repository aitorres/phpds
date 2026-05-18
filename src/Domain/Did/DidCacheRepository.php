<?php

declare(strict_types=1);

namespace App\Domain\Did;

interface DidCacheRepository
{
    /**
     * @throws DidDocEntryNotFoundException
     */
    public function get(string $did): DidDocEntry;

    /**
     * @param array<string, mixed> $doc
     */
    public function set(string $did, array $doc): void;

    public function has(string $did): bool;

    public function clear(string $did): void;

    public function clearAll(): void;
}
