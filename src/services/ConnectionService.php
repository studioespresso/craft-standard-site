<?php

namespace studioespresso\standardsite\services;

use studioespresso\standardsite\records\ConnectionRecord;
use studioespresso\standardsite\StandardSite;
use yii\base\Component;

/**
 * Manages the AT Protocol connection data in the database (not project config).
 * This allows OAuth tokens to be stored per-environment and written
 * even when allowAdminChanges is false.
 */
class ConnectionService extends Component
{
    private ?ConnectionRecord $_connection = null;
    private bool $_fetched = false;

    /**
     * Get the current connection record, or null if not connected.
     */
    public function getConnection(): ?ConnectionRecord
    {
        if (!$this->_fetched) {
            $this->_connection = ConnectionRecord::find()->one();
            $this->_fetched = true;
        }
        return $this->_connection;
    }

    /**
     * Check if we have an active connection.
     */
    public function isConnected(): bool
    {
        $connection = $this->getConnection();
        return $connection !== null && !empty($connection->did) && !empty($connection->accessToken);
    }

    /**
     * Get the DID from the connection.
     */
    public function getDid(): ?string
    {
        return $this->getConnection()?->did;
    }

    /**
     * Get the PDS URL from the connection.
     */
    public function getPdsUrl(): ?string
    {
        return $this->getConnection()?->pdsUrl;
    }

    /**
     * Get the handle from the connection.
     */
    public function getHandle(): ?string
    {
        return $this->getConnection()?->handle;
    }

    /**
     * Store a new connection after successful OAuth.
     */
    public function saveConnection(string $handle, string $did, string $pdsUrl, array $tokens, array $dpopKey): void
    {
        $encryption = StandardSite::getInstance()->encryption;

        $record = $this->getConnection() ?? new ConnectionRecord();
        $record->handle = $handle;
        $record->did = $did;
        $record->pdsUrl = $pdsUrl;
        $record->accessToken = $encryption->encrypt($tokens['access_token']);
        $record->refreshToken = $encryption->encrypt($tokens['refresh_token']);
        $record->dpopKey = $encryption->encrypt(json_encode($dpopKey, JSON_THROW_ON_ERROR));
        $record->tokenExpiresAt = time() + ($tokens['expires_in'] ?? 3600);
        $record->save();

        // Reset cache
        $this->_connection = $record;
        $this->_fetched = true;
    }

    /**
     * Update tokens after a refresh.
     */
    public function updateTokens(array $tokens): void
    {
        $connection = $this->getConnection();
        if (!$connection) {
            throw new \RuntimeException('No connection to update');
        }

        $encryption = StandardSite::getInstance()->encryption;
        $connection->accessToken = $encryption->encrypt($tokens['access_token']);
        $connection->refreshToken = $encryption->encrypt($tokens['refresh_token']);
        $connection->tokenExpiresAt = time() + ($tokens['expires_in'] ?? 3600);
        $connection->save();
    }

    /**
     * Get a valid decrypted access token.
     */
    public function getAccessToken(): ?string
    {
        $connection = $this->getConnection();
        if (!$connection?->accessToken) {
            return null;
        }
        return StandardSite::getInstance()->encryption->decrypt($connection->accessToken);
    }

    /**
     * Get the decrypted DPoP key.
     */
    public function getDpopKey(): ?array
    {
        $connection = $this->getConnection();
        if (!$connection?->dpopKey) {
            return null;
        }
        $json = StandardSite::getInstance()->encryption->decrypt($connection->dpopKey);
        return $json ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    /**
     * Get the decrypted refresh token.
     */
    public function getRefreshToken(): ?string
    {
        $connection = $this->getConnection();
        if (!$connection?->refreshToken) {
            return null;
        }
        return StandardSite::getInstance()->encryption->decrypt($connection->refreshToken);
    }

    /**
     * Get token expiry timestamp.
     */
    public function getTokenExpiresAt(): ?int
    {
        return $this->getConnection()?->tokenExpiresAt;
    }

    /**
     * Delete the connection (disconnect).
     */
    public function deleteConnection(): void
    {
        $connection = $this->getConnection();
        if ($connection) {
            $connection->delete();
        }
        $this->_connection = null;
        $this->_fetched = false;
    }
}
