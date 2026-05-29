<?php

namespace studioespresso\standardsite\services;

use Craft;
use studioespresso\standardsite\records\ConnectionRecord;
use studioespresso\standardsite\StandardSite;
use yii\base\Component;

/**
 * Manages AT Protocol connection data in the database (not project config).
 *
 * Connections are stored per Craft site (keyed by site UID), so each site can
 * authenticate with its own AT Protocol identity. Storing tokens in the DB also
 * means they can be written per-environment even when allowAdminChanges is false.
 *
 * Most methods take an optional $siteUid. When omitted they fall back to the
 * "active" site (set via {@see setActiveSiteUid()} at the start of a publish /
 * settings operation) and finally to the current request site. This lets the
 * lower-level API/OAuth services read "the current connection" without every
 * call having to thread the site through.
 */
class ConnectionService extends Component
{
    /** @var array<string, ConnectionRecord|null> Connection records keyed by site UID */
    private array $_connections = [];

    private ?string $_activeSiteUid = null;

    /**
     * Set the site whose connection the lower-level services (API, OAuth) should
     * operate on for the duration of the current operation.
     */
    public function setActiveSiteUid(?string $siteUid): void
    {
        $this->_activeSiteUid = $siteUid;
    }

    /**
     * Resolve a usable site UID: explicit > active > current request site.
     */
    private function resolveSiteUid(?string $siteUid): string
    {
        return $siteUid
            ?? $this->_activeSiteUid
            ?? Craft::$app->getSites()->getCurrentSite()->uid;
    }

    /**
     * Get the connection record for a site, or null if that site isn't connected.
     */
    public function getConnection(?string $siteUid = null): ?ConnectionRecord
    {
        $siteUid = $this->resolveSiteUid($siteUid);

        if (!array_key_exists($siteUid, $this->_connections)) {
            /** @var ConnectionRecord|null $connection */
            $connection = ConnectionRecord::find()->where(['siteUid' => $siteUid])->one();
            $this->_connections[$siteUid] = $connection;
        }

        return $this->_connections[$siteUid];
    }

    /**
     * Check if a site has an active connection.
     */
    public function isConnected(?string $siteUid = null): bool
    {
        $connection = $this->getConnection($siteUid);
        return $connection !== null && !empty($connection->did) && !empty($connection->accessToken);
    }

    /**
     * Get the DID from a site's connection.
     */
    public function getDid(?string $siteUid = null): ?string
    {
        return $this->getConnection($siteUid)?->did;
    }

    /**
     * Get the PDS URL from a site's connection.
     */
    public function getPdsUrl(?string $siteUid = null): ?string
    {
        return $this->getConnection($siteUid)?->pdsUrl;
    }

    /**
     * Get the AT Protocol handle from a site's connection.
     */
    public function getHandle(?string $siteUid = null): ?string
    {
        return $this->getConnection($siteUid)?->handle;
    }

    /**
     * Get the Craft site handle used during OAuth (for correct client_id on refresh).
     */
    public function getSiteHandle(?string $siteUid = null): ?string
    {
        return $this->getConnection($siteUid)?->siteHandle;
    }

    /**
     * Store a new connection for a site after successful OAuth.
     */
    public function saveConnection(string $siteUid, string $handle, string $did, string $pdsUrl, array $tokens, array $dpopKey, ?string $siteHandle = null): void
    {
        $encryption = StandardSite::getInstance()->encryption;

        $record = $this->getConnection($siteUid) ?? new ConnectionRecord();
        $record->siteUid = $siteUid;
        $record->handle = $handle;
        $record->siteHandle = $siteHandle;
        $record->did = $did;
        $record->pdsUrl = $pdsUrl;
        $record->accessToken = $encryption->encrypt($tokens['access_token']);
        $record->refreshToken = $encryption->encrypt($tokens['refresh_token']);
        $record->dpopKey = $encryption->encrypt(json_encode($dpopKey, JSON_THROW_ON_ERROR));
        $record->tokenExpiresAt = time() + ($tokens['expires_in'] ?? 3600);
        $record->save();

        // Refresh cache
        $this->_connections[$siteUid] = $record;
    }

    /**
     * Update tokens after a refresh.
     */
    public function updateTokens(array $tokens, ?string $siteUid = null): void
    {
        $connection = $this->getConnection($siteUid);
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
     * Get a valid decrypted access token for a site.
     */
    public function getAccessToken(?string $siteUid = null): ?string
    {
        $connection = $this->getConnection($siteUid);
        if (!$connection?->accessToken) {
            return null;
        }
        return StandardSite::getInstance()->encryption->decrypt($connection->accessToken);
    }

    /**
     * Get the decrypted DPoP key for a site.
     */
    public function getDpopKey(?string $siteUid = null): ?array
    {
        $connection = $this->getConnection($siteUid);
        if (!$connection?->dpopKey) {
            return null;
        }
        $json = StandardSite::getInstance()->encryption->decrypt($connection->dpopKey);
        return $json ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    /**
     * Get the decrypted refresh token for a site.
     */
    public function getRefreshToken(?string $siteUid = null): ?string
    {
        $connection = $this->getConnection($siteUid);
        if (!$connection?->refreshToken) {
            return null;
        }
        return StandardSite::getInstance()->encryption->decrypt($connection->refreshToken);
    }

    /**
     * Get token expiry timestamp for a site.
     */
    public function getTokenExpiresAt(?string $siteUid = null): ?int
    {
        return $this->getConnection($siteUid)?->tokenExpiresAt;
    }

    /**
     * Delete a site's connection (disconnect).
     */
    public function deleteConnection(?string $siteUid = null): void
    {
        $siteUid = $this->resolveSiteUid($siteUid);

        $connection = $this->getConnection($siteUid);
        if ($connection) {
            $connection->delete();
        }
        unset($this->_connections[$siteUid]);
    }
}
