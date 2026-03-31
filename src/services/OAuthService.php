<?php

namespace studioespresso\standardsite\services;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use studioespresso\standardsite\StandardSite;
use yii\base\Component;

/**
 * AT Protocol OAuth 2.1 with PKCE + DPoP.
 *
 * Handles the full authorization flow: PAR → redirect → callback → token exchange.
 */
class OAuthService extends Component
{
    private const CACHE_PREFIX = 'standardsite.oauth.';
    private const CACHE_TTL = 3600; // 1 hour
    private const SCOPE = 'atproto transition:generic';

    private ?Client $httpClient = null;

    private function getClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = Craft::createGuzzleClient(['timeout' => 15]);
        }
        return $this->httpClient;
    }

    /**
     * Initiate OAuth flow. Returns the authorization URL to redirect the user to.
     */
    public function authorize(string $handle, ?string $siteHandle = null): string
    {
        $plugin = StandardSite::getInstance();

        // Resolve identity chain
        $resolved = $plugin->resolver->resolve($handle);

        // Generate PKCE pair
        $codeVerifier = bin2hex(random_bytes(32));
        $codeChallenge = DPopService::base64UrlEncode(hash('sha256', $codeVerifier, true));

        // Generate DPoP key
        $dpopKey = $plugin->dpop->generateKey();

        // Generate state token
        $state = bin2hex(random_bytes(16));

        // Store in cache for callback
        $cache = Craft::$app->getCache();
        $cacheData = [
            'handle' => $handle,
            'did' => $resolved['did'],
            'pdsUrl' => $resolved['pdsUrl'],
            'authServer' => $resolved['authServer'],
            'codeVerifier' => $codeVerifier,
            'dpopKey' => $dpopKey,
            'siteHandle' => $siteHandle,
        ];
        $cache->set(self::CACHE_PREFIX . $state, $cacheData, self::CACHE_TTL);

        $authServer = $resolved['authServer'];
        $clientId = $this->getClientId($siteHandle);
        $redirectUri = $this->getRedirectUri($siteHandle);

        // Try PAR (Pushed Authorization Request) first
        if (!empty($authServer['pushed_authorization_request_endpoint'])) {
            return $this->authorizeViaPar($authServer, $dpopKey, $state, $codeChallenge, $clientId, $redirectUri);
        }

        // Fallback to standard authorization URL
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => self::SCOPE,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return $authServer['authorization_endpoint'] . '?' . $params;
    }

    /**
     * Handle OAuth callback. Exchange authorization code for tokens.
     */
    public function handleCallback(string $code, string $state): void
    {
        $cache = Craft::$app->getCache();
        $cacheData = $cache->get(self::CACHE_PREFIX . $state);

        if ($cacheData === false) {
            throw new \RuntimeException('Invalid or expired OAuth state');
        }

        $cache->delete(self::CACHE_PREFIX . $state);

        $plugin = StandardSite::getInstance();
        $authServer = $cacheData['authServer'];
        $dpopKey = $cacheData['dpopKey'];
        $tokenEndpoint = $authServer['token_endpoint'];

        // Create DPoP proof for token endpoint
        $proof = $plugin->dpop->createProof($dpopKey, 'POST', $tokenEndpoint);

        $siteHandle = $cacheData['siteHandle'] ?? null;

        $tokenData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri($siteHandle),
            'client_id' => $this->getClientId($siteHandle),
            'code_verifier' => $cacheData['codeVerifier'],
        ];

        $tokens = $this->tokenRequest($tokenEndpoint, $tokenData, $proof, $dpopKey);

        // Store everything
        $this->storeConnection($cacheData, $dpopKey, $tokens);
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshToken(): void
    {
        $plugin = StandardSite::getInstance();
        $conn = $plugin->connection;

        $refreshToken = $conn->getRefreshToken();
        if (!$refreshToken) {
            throw new \RuntimeException('No refresh token available');
        }

        $dpopKey = $conn->getDpopKey();
        if (!$dpopKey) {
            throw new \RuntimeException('No DPoP key available');
        }

        // Discover token endpoint
        $authServer = $plugin->resolver->discoverAuthServer($conn->getPdsUrl());
        $tokenEndpoint = $authServer['token_endpoint'];

        $proof = $plugin->dpop->createProof($dpopKey, 'POST', $tokenEndpoint);

        $tokenData = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->getClientId($conn->getSiteHandle()),
        ];

        $tokens = $this->tokenRequest($tokenEndpoint, $tokenData, $proof, $dpopKey);

        // Update stored tokens in DB
        $conn->updateTokens($tokens);
    }

    /**
     * Get a valid access token, refreshing if needed.
     */
    public function getAccessToken(): string
    {
        $plugin = StandardSite::getInstance();
        $conn = $plugin->connection;

        $accessToken = $conn->getAccessToken();
        if (!$accessToken) {
            throw new \RuntimeException('Not authenticated. Please connect via Standard.site in the CP.');
        }

        // Refresh if expiring within 5 minutes
        $expiresAt = $conn->getTokenExpiresAt();
        if ($expiresAt && $expiresAt < time() + 300) {
            $this->refreshToken();
            $accessToken = $conn->getAccessToken();
            if (!$accessToken) {
                throw new \RuntimeException('Failed to refresh access token');
            }
        }

        return $accessToken;
    }

    /**
     * OAuth client_id — a publicly accessible URL that serves client metadata JSON.
     */
    public function getClientId(?string $siteHandle = null): string
    {
        return rtrim($this->getSiteBaseUrl($siteHandle), '/') . '/standard-site/oauth/client-metadata';
    }

    /**
     * OAuth redirect URI — the callback URL.
     */
    public function getRedirectUri(?string $siteHandle = null): string
    {
        return rtrim($this->getSiteBaseUrl($siteHandle), '/') . '/standard-site/oauth/callback';
    }

    /**
     * Get the base URL for a specific site, or primary site as fallback.
     */
    private function getSiteBaseUrl(?string $siteHandle = null): string
    {
        if ($siteHandle) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if ($site) {
                return $site->getBaseUrl();
            }
        }
        return Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
    }

    private function authorizeViaPar(array $authServer, array $dpopKey, string $state, string $codeChallenge, string $clientId, string $redirectUri, int $retryCount = 0): string
    {
        $plugin = StandardSite::getInstance();
        $parEndpoint = $authServer['pushed_authorization_request_endpoint'];

        $proof = $plugin->dpop->createProof($dpopKey, 'POST', $parEndpoint);

        $parData = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => self::SCOPE,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        try {
            $response = $this->getClient()->post($parEndpoint, [
                'headers' => ['DPoP' => $proof],
                'form_params' => $parData,
            ]);

            $result = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            // Extract nonce if present
            $nonce = $response->getHeaderLine('DPoP-Nonce');
            if ($nonce) {
                $plugin->dpop->setNonce($nonce);
            }

            $requestUri = $result['request_uri'];

            $params = http_build_query([
                'client_id' => $clientId,
                'request_uri' => $requestUri,
            ]);

            return $authServer['authorization_endpoint'] . '?' . $params;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode((string)$response->getBody(), true) ?? [];

            // Handle DPoP nonce requirement on PAR
            if (($body['error'] ?? '') === 'use_dpop_nonce' && $retryCount < 2) {
                $nonce = $response->getHeaderLine('DPoP-Nonce');
                if ($nonce) {
                    $plugin->dpop->setNonce($nonce);
                    return $this->authorizeViaPar($authServer, $dpopKey, $state, $codeChallenge, $clientId, $redirectUri, $retryCount + 1);
                }
            }

            Craft::error('PAR request failed. Body: ' . json_encode($body) . ' | redirect_uri sent: ' . $redirectUri . ' | client_id: ' . $clientId, __METHOD__);
            throw new \RuntimeException('PAR request failed: ' . ($body['error_description'] ?? $e->getMessage()));
        }
    }

    /**
     * Make a token request with DPoP, handling nonce retries.
     */
    private function tokenRequest(string $endpoint, array $data, string $proof, array $dpopKey, int $retryCount = 0): array
    {
        try {
            $response = $this->getClient()->post($endpoint, [
                'headers' => ['DPoP' => $proof],
                'form_params' => $data,
            ]);

            // Extract nonce
            $nonce = $response->getHeaderLine('DPoP-Nonce');
            if ($nonce) {
                StandardSite::getInstance()->dpop->setNonce($nonce);
            }

            return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode((string)$response->getBody(), true) ?? [];

            // DPoP nonce retry
            if (($body['error'] ?? '') === 'use_dpop_nonce' && $retryCount < 2) {
                $nonce = $response->getHeaderLine('DPoP-Nonce');
                if ($nonce) {
                    $plugin = StandardSite::getInstance();
                    $plugin->dpop->setNonce($nonce);
                    $newProof = $plugin->dpop->createProof($dpopKey, 'POST', $endpoint);
                    return $this->tokenRequest($endpoint, $data, $newProof, $dpopKey, $retryCount + 1);
                }
            }

            throw new \RuntimeException('Token request failed: ' . ($body['error_description'] ?? $e->getMessage()));
        }
    }

    private function storeConnection(array $cacheData, array $dpopKey, array $tokens): void
    {
        StandardSite::getInstance()->connection->saveConnection(
            $cacheData['handle'],
            $cacheData['did'],
            $cacheData['pdsUrl'],
            $tokens,
            $dpopKey,
            $cacheData['siteHandle'] ?? null,
        );
    }
}
