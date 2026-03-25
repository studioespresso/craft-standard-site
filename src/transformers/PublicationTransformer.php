<?php

namespace studioespresso\standardsite\transformers;

use Craft;
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

        return $record;
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
