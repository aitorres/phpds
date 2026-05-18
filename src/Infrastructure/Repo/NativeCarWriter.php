<?php

declare(strict_types=1);

namespace App\Infrastructure\Repo;

use App\Domain\Repo\CarWriter;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Domain\Repo\DagCborEncoder;

/**
 * Serialises blocks as a CARv1 binary stream.
 *
 * CARv1 wire format:
 *   <uvarint: header-cbor-len>  <header-cbor>
 *   ( <uvarint: cid-len + block-len>  <raw-cid-bytes>  <block-bytes> )*
 *
 * The header CBOR is: dag-cbor({version: 1, roots: [<CidLink>, ...]}).
 * CIDs inside sections are raw bytes (no multibase prefix).
 */
class NativeCarWriter implements CarWriter
{
    public function __construct(private readonly DagCborEncoder $encoder)
    {
    }

    public function write(array $rootCids, array $blocks): string
    {
        $roots      = array_map(static fn(string $cid): CidLink => new CidLink($cid), $rootCids);
        $headerCbor = $this->encoder->encode(['version' => 1, 'roots' => $roots]);

        $out  = $this->encodeVarint(strlen($headerCbor));
        $out .= $headerCbor;

        foreach ($blocks as $cidStr => $blockData) {
            $cidBytes   = CidUtil::toRawBytes($cidStr);
            $sectionLen = strlen($cidBytes) + strlen($blockData);

            $out .= $this->encodeVarint($sectionLen);
            $out .= $cidBytes;
            $out .= $blockData;
        }

        return $out;
    }

    /**
     * Encode a non-negative integer as an unsigned LEB-128 (uvarint).
     */
    private function encodeVarint(int $n): string
    {
        $out = '';

        while ($n >= 0x80) {
            $out .= chr(($n & 0x7f) | 0x80 & 0xff);
            $n   = $n >> 7;
        }

        $out .= chr($n & 0x7f);

        return $out;
    }
}
