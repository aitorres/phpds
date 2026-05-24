<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use InvalidArgumentException;

/**
 * Immutable representation of a Merkle Search Tree node.
 */
final class MstNode
{
    /**
     * @param list<MstNodeEntry> $entries
     */
    public function __construct(
        private readonly ?CidLink $left,
        private readonly array $entries,
    ) {
    }

    /**
     * The empty MST is the CBOR-encoded map:
     *   {
     *     "l": null,
     *     "e": []
     *   }
     *
     *  encoded as DAG-CBOR. Its CID (CIDv1 + dag-cbor + sha2-256) is the
     * `data` field of the genesis commit.
     *
     * `l` is the left child link.
     * `e` is the list of entries in this MST node.
     *
     * @return self
     */
    public static function empty(): self
    {
        return new self(left: null, entries: []);
    }

    /**
     * @param list<MstLeaf> $leaves
     */
    public static function fromLeaves(?CidLink $left, array $leaves): self
    {
        $entries = [];
        $previousKey = '';

        foreach ($leaves as $leaf) {
            $key = $leaf->getKey();
            MstKey::ensureValid($key);

            $entries[] = new MstNodeEntry(
                prefixLength: MstKey::countSharedPrefix($previousKey, $key),
                keySuffix: substr($key, MstKey::countSharedPrefix($previousKey, $key)),
                value: $leaf->getValue(),
                right: $leaf->getRight(),
            );
            $previousKey = $key;
        }

        return new self($left, $entries);
    }

    /**
     * @param array<string, mixed> $node
     */
    public static function fromMap(array $node): self
    {
        $left = $node['l'] ?? null;
        $entries = $node['e'] ?? null;

        if ($left !== null && !$left instanceof CidLink) {
            throw new InvalidArgumentException('MST node left child must be a CID link or null.');
        }

        if (!is_array($entries) || !array_is_list($entries)) {
            throw new InvalidArgumentException('MST node entries must be a list.');
        }

        $decodedEntries = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException('MST node entries must decode to maps.');
            }

            /** @var array<string, mixed> $entry */
            $decodedEntries[] = MstNodeEntry::fromMap($entry);
        }

        return new self($left, $decodedEntries);
    }

    public static function decode(DagCborDecoder $decoder, string $bytes): self
    {
        $decoded = $decoder->decode($bytes);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('MST node must decode to a map.');
        }

        /** @var array<string, mixed> $decoded */
        return self::fromMap($decoded);
    }

    public function getLeft(): ?CidLink
    {
        return $this->left;
    }

    /**
     * @return list<MstNodeEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->left === null && $this->entries === [];
    }

    public function entryCount(): int
    {
        return count($this->entries);
    }

    public function getLeaf(string $key): ?MstLeaf
    {
        MstKey::ensureValid($key);

        foreach ($this->toLeaves() as $leaf) {
            if ($leaf->getKey() === $key) {
                return $leaf;
            }
        }

        return null;
    }

    public function hasLeaf(string $key): bool
    {
        return $this->getLeaf($key) !== null;
    }

    /**
     * @return list<MstLeaf>
     */
    public function toLeaves(): array
    {
        $previousKey = '';
        $leaves = [];

        foreach ($this->entries as $entry) {
            $key = $entry->expandKey($previousKey);
            MstKey::ensureValid($key);
            $leaves[] = new MstLeaf($key, $entry->getValue(), $entry->getRight());
            $previousKey = $key;
        }

        return $leaves;
    }

    /**
     * @return list<MstLeaf>
     */
    public function listLeaves(int $count = PHP_INT_MAX, ?string $after = null, ?string $before = null): array
    {
        if ($count < 0) {
            throw new InvalidArgumentException('MST leaf listing count must be greater than or equal to zero.');
        }

        if ($after !== null) {
            MstKey::ensureValid($after);
        }

        if ($before !== null) {
            MstKey::ensureValid($before);
        }

        if ($count === 0) {
            return [];
        }

        $leaves = [];

        foreach ($this->toLeaves() as $leaf) {
            $key = $leaf->getKey();

            if ($after !== null && $key <= $after) {
                continue;
            }

            if ($before !== null && $key >= $before) {
                break;
            }

            $leaves[] = $leaf;

            if (count($leaves) >= $count) {
                break;
            }
        }

        return $leaves;
    }

    /**
     * @return list<MstLeaf>
     */
    public function listLeavesWithPrefix(string $prefix, int $count = PHP_INT_MAX): array
    {
        if ($count < 0) {
            throw new InvalidArgumentException('MST prefix listing count must be greater than or equal to zero.');
        }

        $leaves = [];

        foreach ($this->toLeaves() as $leaf) {
            if (!str_starts_with($leaf->getKey(), $prefix)) {
                if ($leaves !== []) {
                    break;
                }

                continue;
            }

            $leaves[] = $leaf;

            if (count($leaves) >= $count) {
                break;
            }
        }

        return $leaves;
    }

    public function addLeaf(MstLeaf $leaf): self
    {
        $leaves = $this->toLeaves();
        $insertAt = count($leaves);

        foreach ($leaves as $index => $existingLeaf) {
            $comparison = strcmp($existingLeaf->getKey(), $leaf->getKey());

            if ($comparison === 0) {
                throw new InvalidArgumentException(sprintf('There is already a value at key: %s', $leaf->getKey()));
            }

            if ($comparison > 0) {
                $insertAt = $index;
                break;
            }
        }

        array_splice($leaves, $insertAt, 0, [$leaf]);

        return self::fromLeaves($this->left, $leaves);
    }

    public function updateLeaf(string $key, CidLink $value): self
    {
        MstKey::ensureValid($key);

        $leaves = $this->toLeaves();

        foreach ($leaves as $index => $leaf) {
            if ($leaf->getKey() !== $key) {
                continue;
            }

            $leaves[$index] = new MstLeaf($key, $value, $leaf->getRight());

            return self::fromLeaves($this->left, $leaves);
        }

        throw new InvalidArgumentException(sprintf('Could not find a record with key: %s', $key));
    }

    public function putLeaf(MstLeaf $leaf): self
    {
        if ($this->hasLeaf($leaf->getKey())) {
            return $this->updateLeaf($leaf->getKey(), $leaf->getValue());
        }

        return $this->addLeaf($leaf);
    }

    public function deleteLeaf(string $key): self
    {
        MstKey::ensureValid($key);

        $leaves = $this->toLeaves();

        foreach ($leaves as $index => $leaf) {
            if ($leaf->getKey() !== $key) {
                continue;
            }

            array_splice($leaves, $index, 1);

            return self::fromLeaves($this->left, array_values($leaves));
        }

        throw new InvalidArgumentException(sprintf('Could not find a record with key: %s', $key));
    }

    /**
     * @param list<MstMutation> $mutations
     */
    public function applyMutations(array $mutations): self
    {
        $node = $this;

        foreach ($mutations as $mutation) {
            if ($mutation->isPut()) {
                $value = $mutation->getValue();

                if ($value === null) {
                    throw new InvalidArgumentException('MST put mutation requires a value CID.');
                }

                $node = $node->putLeaf(new MstLeaf($mutation->getKey(), $value, null));
                continue;
            }

            $node = $node->deleteLeaf($mutation->getKey());
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    public function toMap(): array
    {
        $entries = [];

        foreach ($this->entries as $entry) {
            $entries[] = $entry->toMap();
        }

        return [
            'l' => $this->left,
            'e' => $entries,
        ];
    }

    /**
     * Encode and return [cborBytes, cidString].
     *
     * @return array{0: string, 1: string}
     */
    public function encode(DagCborEncoder $encoder): array
    {
        $bytes = $encoder->encode($this->toMap());
        $cid = CidUtil::computeForDagCbor($bytes);

        return [$bytes, $cid];
    }
}
