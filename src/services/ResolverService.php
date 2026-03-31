<?php

namespace studioespresso\standardsite\services;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;

/**
 * Resolves AT Protocol identity chain: handle → DID → PDS → auth server.
 */
class ResolverService extends Component
{
    private Client $httpClient;

    public function init(): void
    {
        parent::init();
        $this->httpClient = Craft::createGuzzleClient([
            'timeout' => 10,
        ]);
    }

    /**
     * Full resolution chain. Returns all resolved identity data.
     *
     * @return array{did: string, pdsUrl: string, authServer: array}
     * @throws \RuntimeException on resolution failure
     */
    public function resolve(string $handle): array
    {
        // Strip leading @ if present
        $handle = ltrim($handle, '@');

        $did = $this->resolveHandleToDid($handle);
        $didDoc = $this->resolveDidDocument($did);
        $pdsUrl = $this->extractPdsUrl($didDoc);
        $authServer = $this->discoverAuthServer($pdsUrl);

        return [
            'did' => $did,
            'pdsUrl' => $pdsUrl,
            'authServer' => $authServer,
        ];
    }

    /**
     * Resolve a handle to a DID.
     * Tries DNS TXT first, then falls back to .well-known/atproto-did.
     */
    public function resolveHandleToDid(string $handle): string
    {
        // Try DNS TXT lookup: _atproto.<handle>
        $did = $this->resolveViaDns($handle);
        if ($did !== null) {
            return $did;
        }

        // Fallback: HTTPS well-known
        $did = $this->resolveViaHttps($handle);
        if ($did !== null) {
            return $did;
        }

        // Fallback: XRPC API (for *.bsky.social and other PDS-hosted handles)
        return $this->resolveViaXrpc($handle);
    }

    private function resolveViaDns(string $handle): ?string
    {
        $records = @dns_get_record("_atproto.{$handle}", DNS_TXT);

        if ($records === false || empty($records)) {
            return null;
        }

        foreach ($records as $record) {
            $txt = $record['txt'] ?? '';
            if (str_starts_with($txt, 'did=')) {
                return substr($txt, 4);
            }
        }

        return null;
    }

    private function resolveViaHttps(string $handle): ?string
    {
        try {
            $response = $this->httpClient->get("https://{$handle}/.well-known/atproto-did");
            $did = trim((string)$response->getBody());

            if (!str_starts_with($did, 'did:')) {
                return null;
            }

            return $did;
        } catch (GuzzleException $e) {
            Craft::warning("[standard-site] HTTPS handle resolution failed for {$handle}: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }

    private function resolveViaXrpc(string $handle): string
    {
        try {
            $response = $this->httpClient->get('https://bsky.social/xrpc/com.atproto.identity.resolveHandle', [
                'query' => ['handle' => $handle],
            ]);
            $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (!isset($data['did'])) {
                throw new \RuntimeException("No DID returned for handle: {$handle}");
            }

            return $data['did'];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Could not resolve handle '{$handle}' to DID: {$e->getMessage()}");
        }
    }

    /**
     * Resolve a DID to its DID document.
     * Supports did:plc: and did:web: methods.
     */
    public function resolveDidDocument(string $did): array
    {
        if (str_starts_with($did, 'did:plc:')) {
            $url = "https://plc.directory/{$did}";
        } elseif (str_starts_with($did, 'did:web:')) {
            $domain = substr($did, 8);
            $url = "https://{$domain}/.well-known/did.json";
        } else {
            throw new \RuntimeException("Unsupported DID method: {$did}");
        }

        try {
            $response = $this->httpClient->get($url);
            $doc = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (!isset($doc['id'])) {
                throw new \RuntimeException("Invalid DID document for: {$did}");
            }

            return $doc;
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Could not resolve DID document for '{$did}': {$e->getMessage()}");
        }
    }

    /**
     * Extract PDS URL from DID document service entries.
     */
    public function extractPdsUrl(array $didDoc): string
    {
        foreach ($didDoc['service'] ?? [] as $service) {
            if (($service['id'] ?? '') === '#atproto_pds' &&
                ($service['type'] ?? '') === 'AtprotoPersonalDataServer') {
                return rtrim($service['serviceEndpoint'], '/');
            }
        }

        throw new \RuntimeException('No PDS service endpoint found in DID document');
    }

    /**
     * Discover OAuth authorization server metadata from PDS.
     *
     * @return array OAuth server metadata (authorization_endpoint, token_endpoint, par_endpoint, etc.)
     */
    public function discoverAuthServer(string $pdsUrl): array
    {
        try {
            // Step 1: Get resource metadata to find issuer
            $response = $this->httpClient->get("{$pdsUrl}/.well-known/oauth-protected-resource");
            $resource = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $issuer = $resource['authorization_servers'][0] ?? null;

            if (!$issuer) {
                throw new \RuntimeException('No authorization server found in PDS resource metadata');
            }

            // Step 2: Get auth server metadata
            $response = $this->httpClient->get("{$issuer}/.well-known/oauth-authorization-server");
            return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Could not discover auth server: {$e->getMessage()}");
        }
    }
}
