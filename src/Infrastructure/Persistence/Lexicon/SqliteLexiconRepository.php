<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Lexicon;

use App\Domain\Lexicon\LexiconEntry;
use App\Domain\Lexicon\LexiconEntryNotFoundException;
use App\Domain\Lexicon\LexiconRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteLexiconRepository implements LexiconRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByNsid(string $nsid): LexiconEntry
    {
        $row = $this->db->fetchOne('SELECT * FROM lexicon WHERE nsid = ?', [$nsid]);

        if ($row === null) {
            throw new LexiconEntryNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @return LexiconEntry[]
     */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM lexicon ORDER BY nsid');

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(LexiconEntry $entry): void
    {
        $lexicon = $entry->getLexicon();
        $lexiconJson = $lexicon === null
            ? null
            : json_encode($lexicon, JSON_THROW_ON_ERROR);

        $this->db->execute(
            'INSERT INTO lexicon (nsid, created_at, updated_at, last_succeeded_at, uri, lexicon_json)
             VALUES (:nsid, :created_at, :updated_at, :last_succeeded_at, :uri, :lexicon_json)
             ON CONFLICT(nsid) DO UPDATE SET
                created_at = excluded.created_at,
                updated_at = excluded.updated_at,
                last_succeeded_at = excluded.last_succeeded_at,
                uri = excluded.uri,
                lexicon_json = excluded.lexicon_json',
            [
                'nsid'              => $entry->getNsid(),
                'created_at'        => $entry->getCreatedAt()->format(DATE_ATOM),
                'updated_at'        => $entry->getUpdatedAt()->format(DATE_ATOM),
                'last_succeeded_at' => $entry->getLastSucceededAt()?->format(DATE_ATOM),
                'uri'               => $entry->getUri(),
                'lexicon_json'      => $lexiconJson,
            ]
        );
    }

    public function deleteByNsid(string $nsid): void
    {
        $this->db->execute('DELETE FROM lexicon WHERE nsid = ?', [$nsid]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): LexiconEntry
    {
        $lexiconJson = Row::nstr($row, 'lexicon_json');
        $lexicon = $lexiconJson === null
            ? null
            : json_decode($lexiconJson, true, 512, JSON_THROW_ON_ERROR);
        assert($lexicon === null || is_array($lexicon));
        /** @var array<string, mixed>|null $lexicon */

        $lastSucceededAt = Row::nstr($row, 'last_succeeded_at');

        return new LexiconEntry(
            nsid: Row::str($row, 'nsid'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
            updatedAt: new DateTimeImmutable(Row::str($row, 'updated_at')),
            lastSucceededAt: $lastSucceededAt === null ? null : new DateTimeImmutable($lastSucceededAt),
            uri: Row::nstr($row, 'uri'),
            lexicon: $lexicon,
        );
    }
}
