<?php

declare(strict_types=1);

namespace App\Domain\Preference;

use JsonSerializable;

/**
 * A named preference entry for an account.
 * Maps to the `account_pref` table in the actor-store schema.
 *
 * Lives inside an ActorStore scoped to a specific DID, so no `did` field here.
 */
class AccountPref implements JsonSerializable
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $valueJson,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValueJson(): string
    {
        return $this->valueJson;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'valueJson' => $this->valueJson,
        ];
    }
}
