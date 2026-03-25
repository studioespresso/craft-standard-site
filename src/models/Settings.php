<?php

namespace studioespresso\standardsite\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    // Identity (global — one AT Protocol account)
    public string $handle = '';
    public string $did = '';
    public string $pdsUrl = '';

    // OAuth tokens (global, stored encrypted)
    public ?string $accessToken = null;
    public ?string $refreshToken = null;
    public ?string $dpopKey = null;
    public ?int $tokenExpiresAt = null;

    // Per-site settings keyed by site UID
    public array $siteSettings = [];

    // ── Legacy properties (for backward compatibility during migration) ──
    // These will be auto-migrated to siteSettings on first access
    public ?string $publicationAtUri = null;
    public ?string $publicationCid = null;
    public ?string $publicationRkey = null;
    public string $publicationName = '';
    public string $publicationDescription = '';
    public bool $crossPostToBluesky = false;
    public bool $publishOnSave = true;
    public array $enabledSections = [];

    /**
     * Get settings for a specific site, with backward-compatible migration.
     */
    public function getSiteSettings(string $siteUid): SiteSettings
    {
        // Check if per-site settings exist
        if (isset($this->siteSettings[$siteUid])) {
            $data = $this->siteSettings[$siteUid];
            $model = new SiteSettings();
            $model->setAttributes($data, false);
            return $model;
        }

        // Auto-migrate legacy flat properties to primary site
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        if ($siteUid === $primarySite->uid && $this->hasLegacySettings()) {
            $model = new SiteSettings();
            $model->publicationAtUri = $this->publicationAtUri;
            $model->publicationCid = $this->publicationCid;
            $model->publicationRkey = $this->publicationRkey;
            $model->publicationName = $this->publicationName;
            $model->publicationDescription = $this->publicationDescription;
            $model->enabledSections = $this->enabledSections;
            $model->publishOnSave = $this->publishOnSave;
            $model->crossPostToBluesky = $this->crossPostToBluesky;
            return $model;
        }

        // Return empty defaults
        return new SiteSettings();
    }

    /**
     * Set settings for a specific site.
     */
    public function setSiteSettings(string $siteUid, SiteSettings $siteSettings): void
    {
        $this->siteSettings[$siteUid] = $siteSettings->getAttributes();
    }

    /**
     * Check if legacy flat properties contain any data.
     */
    private function hasLegacySettings(): bool
    {
        return !empty($this->publicationAtUri)
            || !empty($this->publicationName)
            || !empty($this->enabledSections);
    }

    public function defineRules(): array
    {
        return [
            [['handle'], 'string'],
            [['did', 'pdsUrl'], 'string'],
        ];
    }
}
