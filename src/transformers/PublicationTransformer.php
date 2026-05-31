<?php

namespace studioespresso\standardsite\transformers;

use Craft;
use craft\elements\Asset;
use craft\models\Site;
use studioespresso\standardsite\records\PublicationSettingsRecord;
use studioespresso\standardsite\StandardSite;

class PublicationTransformer
{
    /**
     * Build a site.standard.publication record for a Craft site from its
     * (database-backed) publication settings.
     */
    public function transformForSite(Site $site, PublicationSettingsRecord $config): array
    {
        $record = [
            '$type' => 'site.standard.publication',
            'url' => rtrim($site->getBaseUrl(), '/'),
            'name' => $config->name ?: $site->getName(),
        ];

        if ($config->description) {
            $record['description'] = $config->description;
        }

        // Icon — a square image shown on AT Protocol link cards.
        $icon = $this->uploadIcon($config);
        if ($icon) {
            $record['icon'] = $icon;
        }

        // Theme — brand colours adopted by link cards.
        $theme = $this->buildTheme($config);
        if ($theme) {
            $record['basicTheme'] = $theme;
        }

        // Preferences — discovery feed visibility.
        $record['preferences'] = [
            'showInDiscover' => (bool)$config->showInDiscover,
        ];

        return $record;
    }

    /**
     * Resolve the configured icon asset and upload it as a blob. The asset ID is
     * stored per-environment, so it resolves on whichever environment pushes the
     * record.
     */
    private function uploadIcon(PublicationSettingsRecord $config): ?array
    {
        $assetId = $config->iconAssetId;
        if (!$assetId) {
            return null;
        }

        /** @var Asset|null $asset */
        $asset = Asset::find()->id($assetId)->status(null)->one();
        if (!$asset) {
            Craft::warning("[standard-site] Publication icon asset #{$assetId} not found.", __METHOD__);
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
    private function buildTheme(PublicationSettingsRecord $config): ?array
    {
        $background = $this->hexToRgb($config->themeBackground);
        $foreground = $this->hexToRgb($config->themeForeground);
        $accent = $this->hexToRgb($config->themeAccent);
        $accentForeground = $this->hexToRgb($config->themeAccentForeground);

        if (!$background || !$foreground || !$accent || !$accentForeground) {
            return null;
        }

        return [
            '$type' => 'site.standard.theme.basic',
            'background' => $background,
            'foreground' => $foreground,
            'accent' => $accent,
            'accentForeground' => $accentForeground,
        ];
    }

    /**
     * Convert a "#rrggbb" hex string to a site.standard.theme.color#rgb object.
     * The colour field is a union, so the $type discriminator is required.
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
            '$type' => 'site.standard.theme.color#rgb',
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    public function getCollection(): string
    {
        return 'site.standard.publication';
    }
}
