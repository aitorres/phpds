<?php

declare(strict_types=1);

namespace App\Infrastructure\Did;

use App\Domain\Did\DidCacheRepository;
use App\Domain\Did\DidDocEntryNotFoundException;
use App\Domain\Did\DidResolver;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Resolves DID documents over HTTP, with local cache.
 *
 * Supported methods:
 *   - did:plc  → GET {plcDirectoryUrl}/{did}
 *   - did:web  → GET https://{domain}/.well-known/did.json
 */
class HttpDidResolver implements DidResolver
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly DidCacheRepository $cache,
        private readonly string $plcDirectoryUrl,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(string $did): ?array
    {
        if ($this->cache->has($did)) {
            try {
                return $this->cache->get($did)->getDoc();
            } catch (DidDocEntryNotFoundException) {
                // fall through to network fetch
            }
        }

        $doc = $this->fetchFromNetwork($did);

        if ($doc !== null) {
            $this->cache->set($did, $doc);
        }

        return $doc;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromNetwork(string $did): ?array
    {
        $url = $this->urlFor($did);
        if ($url === null) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
            $body     = (string) $response->getBody();
            $decoded  = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return null;
            }
            /** @var array<string, mixed> $decoded */
            return $decoded;
        } catch (Throwable) {
            return null;
        }
    }

    private function urlFor(string $did): ?string
    {
        if (str_starts_with($did, 'did:plc:')) {
            return rtrim($this->plcDirectoryUrl, '/') . '/' . rawurlencode($did);
        }

        if (str_starts_with($did, 'did:web:')) {
            $domain = substr($did, strlen('did:web:'));
            if ($domain === '') {
                return null;
            }
            return 'https://' . rawurldecode($domain) . '/.well-known/did.json';
        }

        return null;
    }
}
