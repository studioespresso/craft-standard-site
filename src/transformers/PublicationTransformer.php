<?php

namespace studioespresso\standardsite\transformers;

use Craft;
use craft\elements\Asset;
use craft\models\Site;
use studioespresso\standardsite\models\SiteSettings;
use studioespresso\standardsite\StandardSite;

class PublicationTransformer
{
    /**
     * Transform for a specific Craft site using its per-site settings.
     */
    public function transformForSite(Site $site, SiteSettings $siteSettings): array
    {
        $record = [
            '$type' => 'site.standard.publication',
            'url' => rtrim($site->getBaseUrl(), '/'),
            'name' => $siteSettings->publicationName ?: $site->getName(),
        ];

        if ($siteSettings->publicationDescription) {
            $record['description'] = $siteSettings->publicationDescription;
        }

        // Icon — a square image shown on AT Protocol link cards.
        $icon = $this->uploadIcon($siteSettings, $site);
        if ($icon) {
            $record['icon'] = $icon;
        }

        // Theme — brand colours adopted by link cards.
        $theme = $this->buildTheme($siteSettings);
        if ($theme) {
            $record['basicTheme'] = $theme;
        }

        // Preferences — discovery feed visibility.
        $record['preferences'] = [
            'showInDiscover' => $siteSettings->showInDiscover,
        ];

        return $record;
    }

    /**
     * Resolve the configured icon asset and upload it as a blob.
     * Runs in the environment where the publication record is created, so the
     * asset must exist there.
     */
    private function uploadIcon(SiteSettings $siteSettings, Site $site): ?array
    {
        $assetId = $siteSettings->publicationIcon[0] ?? null;
        if (!$assetId) {
            return null;
        }

        /** @var Asset|null $asset */
        $asset = Asset::find()->id($assetId)->siteId($site->id)->kind('image')->one();
        if (!$asset) {
            return null;
        }

        try {
            $stream = $asset->getStream();
            try {
                $binaryData = stream_get_contents($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (!$binaryData) {
                return null;
            }

            $mimeType = $asset->getMimeType() ?: 'image/jpeg';
            return StandardSite::getInstance()->api->uploadBlob($binaryData, $mimeType);
        } catch (\Throwable $e) {
            Craft::warning("[standard-site] Failed to upload publication icon: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }

    /**
     * Build a site.standard.theme.basic object from the configured colours.
     * Only emitted when all four colours are set, to avoid a partial theme.
     */
    private function buildTheme(SiteSettings $siteSettings): ?array
    {
        $background = $this->hexToRgb($siteSettings->themeBackground);
        $foreground = $this->hexToRgb($siteSettings->themeForeground);
        $accent = $this->hexToRgb($siteSettings->themeAccent);
        $accentForeground = $this->hexToRgb($siteSettings->themeAccentForeground);

        if (!$background || !$foreground || !$accent || !$accentForeground) {
            return null;
        }

        return [
            'background' => $background,
            'foreground' => $foreground,
            'accent' => $accent,
            'accentForeground' => $accentForeground,
        ];
    }

    /**
     * Convert a "#rrggbb" hex string to an {r, g, b} object of integers.
     */
    private function hexToRgb(?string $hex): ?array
    {
        if (!$hex) {
            return null;
        }

        $hex = ltrim(trim($hex), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Legacy: transform using global settings and primary site.
     */
    public function transform(): array
    {
        $settings = StandardSite::getInstance()->getSettings();
        $site = Craft::$app->getSites()->getPrimarySite();
        $siteSettings = $settings->getSiteSettings($site->uid);

        return $this->transformForSite($site, $siteSettings);
    }

    public function getCollection(): string
    {
        return 'site.standard.publication';
    }
}
