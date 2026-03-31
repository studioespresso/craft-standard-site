<?php

namespace studioespresso\standardsite\services;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use studioespresso\standardsite\StandardSite;
use yii\base\Component;

/**
 * Authenticated XRPC client for AT Protocol PDS requests.
 * Handles DPoP proofs, nonce retries, and token refresh.
 */
class ApiService extends Component
{
    private ?Client $httpClient = null;

    private function getClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = Craft::createGuzzleClient([
                'timeout' => 30,
            ]);
        }
        return $this->httpClient;
    }

    /**
     * Make an authenticated XRPC request to the PDS.
     *
     * @param string $method HTTP method
     * @param string $endpoint XRPC endpoint (e.g., com.atproto.repo.createRecord)
     * @param array $data Request body data (for POST)
     * @param bool $isRetry Internal flag to prevent infinite retry loops
     * @return array Decoded JSON response
     */
    public function request(string $method, string $endpoint, array $data = [], bool $isRetry = false): array
    {
        $plugin = StandardSite::getInstance();
        $conn = $plugin->connection;
        $dpop = $plugin->dpop;

        $pdsUrl = $conn->getPdsUrl();
        if (!$pdsUrl) {
            throw new \RuntimeException('Not connected to a PDS');
        }

        $url = "{$pdsUrl}/xrpc/{$endpoint}";

        // Get access token, auto-refresh if needed
        $accessToken = $plugin->oauth->getAccessToken();

        // Get DPoP key
        $dpopKey = $conn->getDpopKey();
        if (!$dpopKey) {
            throw new \RuntimeException('No DPoP key available');
        }

        // Create DPoP proof
        $proof = $dpop->createProof($dpopKey, $method, $url, $accessToken);

        $options = [
            'headers' => [
                'Authorization' => "DPoP {$accessToken}",
                'DPoP' => $proof,
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($data) && strtoupper($method) !== 'GET') {
            $options['json'] = $data;
        }

        try {
            $response = $this->getClient()->request($method, $url, $options);
            $body = (string)$response->getBody();

            // Store any DPoP nonce from response
            $this->extractNonce($response);

            return $body ? json_decode($body, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = json_decode((string)$response->getBody(), true) ?? [];

            // Handle DPoP nonce requirement
            if ($statusCode === 401 && ($body['error'] ?? '') === 'use_dpop_nonce') {
                $this->extractNonce($response);
                if (!$isRetry) {
                    return $this->request($method, $endpoint, $data, true);
                }
            }

            // Handle expired token
            if ($statusCode === 401 && !$isRetry) {
                $plugin->oauth->refreshToken();
                return $this->request($method, $endpoint, $data, true);
            }

            throw new \RuntimeException(
                "XRPC request failed: {$endpoint} — " . ($body['message'] ?? $e->getMessage()),
                $statusCode
            );
        }
    }

    /**
     * Upload a blob (image, etc.) to the PDS.
     *
     * @return array Blob reference (with $type, ref, mimeType, size)
     */
    public function uploadBlob(string $binaryData, string $mimeType): array
    {
        $plugin = StandardSite::getInstance();
        $conn = $plugin->connection;
        $dpop = $plugin->dpop;

        $url = "{$conn->getPdsUrl()}/xrpc/com.atproto.repo.uploadBlob";
        $accessToken = $plugin->oauth->getAccessToken();

        $dpopKey = $conn->getDpopKey();
        if (!$dpopKey) {
            throw new \RuntimeException('No DPoP key available');
        }
        $proof = $dpop->createProof($dpopKey, 'POST', $url, $accessToken);

        try {
            $response = $this->getClient()->post($url, [
                'headers' => [
                    'Authorization' => "DPoP {$accessToken}",
                    'DPoP' => $proof,
                    'Content-Type' => $mimeType,
                ],
                'body' => $binaryData,
            ]);

            $this->extractNonce($response);

            $result = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (!isset($result['blob'])) {
                throw new \RuntimeException('Invalid upload blob response: missing blob field');
            }

            return $result['blob'];
        } catch (ClientException $e) {
            $body = json_decode((string)$e->getResponse()->getBody(), true) ?? [];
            throw new \RuntimeException('Blob upload failed: ' . ($body['message'] ?? $e->getMessage()));
        }
    }

    public function createRecord(string $collection, array $record, ?string $rkey = null): array
    {
        $data = [
            'repo' => StandardSite::getInstance()->connection->getDid(),
            'collection' => $collection,
            'record' => $record,
        ];

        if ($rkey !== null) {
            $data['rkey'] = $rkey;
        }

        return $this->request('POST', 'com.atproto.repo.createRecord', $data);
    }

    public function putRecord(string $collection, string $rkey, array $record): array
    {
        return $this->request('POST', 'com.atproto.repo.putRecord', [
            'repo' => StandardSite::getInstance()->connection->getDid(),
            'collection' => $collection,
            'rkey' => $rkey,
            'record' => $record,
        ]);
    }

    public function deleteRecord(string $collection, string $rkey): array
    {
        return $this->request('POST', 'com.atproto.repo.deleteRecord', [
            'repo' => StandardSite::getInstance()->connection->getDid(),
            'collection' => $collection,
            'rkey' => $rkey,
        ]);
    }

    public function applyWrites(array $writes): array
    {
        return $this->request('POST', 'com.atproto.repo.applyWrites', [
            'repo' => StandardSite::getInstance()->connection->getDid(),
            'writes' => $writes,
        ]);
    }

    private function extractNonce($response): void
    {
        $nonce = $response->getHeaderLine('DPoP-Nonce');
        if ($nonce) {
            StandardSite::getInstance()->dpop->setNonce($nonce);
        }
    }
}
