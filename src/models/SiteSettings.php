<?php

namespace studioespresso\standardsite\models;

use craft\base\Model;

/**
 * Per-site settings for Standard Site plugin.
 * Each Craft site can have its own publication and sync configuration.
 */
class SiteSettings extends Model
{
    // Identity
    public string $handle = '';

    // Publication
    public ?string $publicationAtUri = null;
    public ?string $publicationCid = null;
    public ?string $publicationRkey = null;
    public string $publicationName = '';
    public string $publicationDescription = '';

    // Sync options
    public bool $publishOnSave = true;
    public bool $crossPostToBluesky = false;
    public array $enabledSections = [];

    // Per-section cover image field mapping: sectionUid => fieldUid
    public array $sectionImageFields = [];

    public function defineRules(): array
    {
        return [
            [['publicationName', 'publicationDescription'], 'string'],
            [['enabledSections'], 'each', 'rule' => ['string']],
        ];
    }
}
