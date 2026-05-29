<?php

namespace studioespresso\standardsite\models;

use craft\base\Model;

/**
 * Per-site settings for Standard.site plugin.
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

    // Publication appearance — powers AT Protocol link cards (icon, theme colours)
    /** @var int[] Selected icon asset IDs (element select, limited to one) */
    public array $publicationIcon = [];
    public ?string $themeBackground = null;
    public ?string $themeForeground = null;
    public ?string $themeAccent = null;
    public ?string $themeAccentForeground = null;
    public bool $showInDiscover = true;

    // Sync options
    public bool $publishOnSave = true;
    public bool $crossPostToBluesky = false;
    /** @var string[] Section UIDs */
    public array $enabledSections = [];

    // Content format type for the document content field
    public string $contentType = 'org.wordpress.html';

    /** @var array<string, string> sectionUid => fieldUid */
    public array $sectionImageFields = [];
    /** @var array<string, string> sectionUid => fieldUid */
    public array $sectionContentFields = [];

    public function defineRules(): array
    {
        return [
            [['publicationName', 'publicationDescription'], 'string'],
            [['themeBackground', 'themeForeground', 'themeAccent', 'themeAccentForeground'], 'string'],
            [['showInDiscover'], 'boolean'],
            [['enabledSections'], 'each', 'rule' => ['string']],
        ];
    }
}
