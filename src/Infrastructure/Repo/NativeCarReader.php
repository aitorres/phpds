<?php

declare(strict_types=1);

namespace App\Infrastructure\Repo;

use App\Domain\Repo\CarReader;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Domain\Repo\DagCborDecoder;
use RuntimeException;

/**
 * Deserialises a CARv1 binary stream into root CIDs and block map.
 *
 * CARv1 wire format (see NativeCarWriter for layout documentation).
 * CIDs in block sections are self-delimiting: we parse the CIDv1 structure
 * (version varint + codec varint + multihash: fn varint + len varint + digest)
 * to determine the exact CID byte-length before reading block data.
 */
class NativeCarReader implements CarReader
{
    public function __construct(private readonly DagCborDecoder $decoder)
    {
    }

    /**
     * @return array{roots: string[], blocks: array<string, string>}
     */
    public function read(string $carBytes): array
    {
        $cursor    = 0;
        $totalLen  = strlen($carBytes);

        // Header
        $headerLen  = $this->decodeVarint($carBytes, $cursor);
        $headerCbor = substr($carBytes, $cursor, $headerLen);
        $cursor    += $headerLen;

        $header = $this->decoder->decode($headerCbor);

        if (!is_array($header) || !isset($header['roots']) || !is_array($header['roots'])) {
            throw new RuntimeException('Invalid CARv1 header: missing roots array');
        }

        $roots = [];

        foreach ($header['roots'] as $link) {
            if (!$link instanceof CidLink) {
                throw new RuntimeException('CARv1 header roots must be CidLink values');
            }

            $roots[] = $link->getCid();
        }

        // Blocks
        $blocks = [];

        while ($cursor < $totalLen) {
            $sectionLen = $this->decodeVarint($carBytes, $cursor);
            $sectionEnd = $cursor + $sectionLen;

            $cidStart  = $cursor;
            $cidBytes  = $this->readCidBytes($carBytes, $cursor);
            $cidStr    = CidUtil::fromRawBytes($cidBytes);

            $blockLen   = $sectionLen - ($cursor - $cidStart);
            $blockData  = substr($carBytes, $cursor, $blockLen);
            $cursor    += $blockLen;

            if ($cursor !== $sectionEnd) {
                throw new RuntimeException("CARv1 section length mismatch for CID {$cidStr}");
            }

            $blocks[$cidStr] = $blockData;
        }

        return ['roots' => $roots, 'blocks' => $blocks];
    }

    /**
     * Parse a CIDv1 from $bytes at $cursor and advance $cursor past it.
     *
     * CIDv1 layout (all fields are uvarint-encoded integers):
     *   version  (= 1)
     *   codec    (e.g. 0x71 for dag-cbor)
     *   multihash: hash-fn-code + digest-length + <digest-length bytes>
     */
    private function readCidBytes(string $bytes, int &$cursor): string
    {
        $start = $cursor;

        $this->decodeVarint($bytes, $cursor); // version
        $this->decodeVarint($bytes, $cursor); // codec
        $this->decodeVarint($bytes, $cursor); // hash function code
        $digestLen = $this->decodeVarint($bytes, $cursor); // digest length
        $cursor   += $digestLen;

        return substr($bytes, $start, $cursor - $start);
    }

    /**
     * Decode an unsigned LEB-128 (uvarint) at $cursor, advancing it.
     */
    private function decodeVarint(string $bytes, int &$cursor): int
    {
        $result = 0;
        $shift  = 0;

        while (true) {
            if ($cursor >= strlen($bytes)) {
                throw new RuntimeException('Unexpected end of stream while reading uvarint');
            }

            $b       = ord($bytes[$cursor++]);
            $result |= ($b & 0x7f) << $shift;

            if (($b & 0x80) === 0) {
                break;
            }

            $shift += 7;
        }

        return $result;
    }
}
