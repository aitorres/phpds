<?php

declare(strict_types=1);

namespace App\Infrastructure\Did;

use App\Domain\Common\Base32;
use App\Domain\Crypto\Keypair;
use App\Domain\Did\PlcDirectoryClient;
use App\Domain\Did\PlcDirectoryClientException;
use App\Domain\Repo\DagCborEncoder;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * HTTP-backed {@see PlcDirectoryClient}.
 *
 * Implements the did:plc operation format:
 *   - operation is a CBOR-canonical map
 *   - `sig` is base64url(no-padding) of the secp256k1 signature over the
 *     CBOR encoding of the operation WITHOUT the sig field
 *   - resulting DID is `did:plc:` + first 24 chars of
 *     base32-lower(sha256(cbor(signed-op)))
 *
 * @see https://github.com/did-method-plc/did-method-plc
 */
final class HttpPlcDirectoryClient implements PlcDirectoryClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly DagCborEncoder $cbor,
        private readonly string $plcDirectoryUrl,
    ) {
    }

    public function buildAndSignGenesisOp(
        array $rotationKeys,
        string $signingKey,
        string $handle,
        string $pdsEndpoint,
        Keypair $signer,
    ): array {
        $unsigned = [
            'type'               => self::OP_TYPE,
            'rotationKeys'       => $rotationKeys,
            'verificationMethods' => [
                'atproto' => $signingKey,
            ],
            'alsoKnownAs' => [
                'at://' . $handle,
            ],
            'services' => [
                'atproto_pds' => [
                    'type'     => 'AtprotoPersonalDataServer',
                    'endpoint' => rtrim($pdsEndpoint, '/'),
                ],
            ],
            'prev' => null,
        ];

        return $this->signOp($unsigned, $signer);
    }

    public function signOp(array $unsignedOp, Keypair $signer): array
    {
        unset($unsignedOp['sig']);
        $bytes = $this->cbor->encode($unsignedOp);
        $sig   = $signer->sign($bytes);
        $unsignedOp['sig'] = self::base64UrlEncode($sig);
        return $unsignedOp;
    }

    public function didForOp(array $signedOp): string
    {
        $bytes = $this->cbor->encode($signedOp);
        $hash  = hash('sha256', $bytes, true);
        $b32   = Base32::encode($hash);
        return 'did:plc:' . substr($b32, 0, 24);
    }

    public function submit(string $did, array $signedOp): void
    {
        $url = rtrim($this->plcDirectoryUrl, '/') . '/' . rawurlencode($did);
        try {
            $response = $this->httpClient->request('POST', $url, [
                'json'    => $signedOp,
                'timeout' => 15,
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new PlcDirectoryClientException(
                "Failed to submit plcOp for {$did}: " . $e->getMessage(),
                0,
                $e,
            );
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new PlcDirectoryClientException(
                "PLC directory rejected operation for {$did}: HTTP {$status} {$response->getBody()}"
            );
        }
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
