<?php

namespace studioespresso\standardsite\records;

use craft\db\ActiveRecord;

/**
 * Per-site publication settings, stored in the database so they can be edited
 * on production (where allowAdminChanges is false) and so the icon asset is
 * referenced by an ID that's local to the environment it's uploaded from.
 *
 * @property int $id
 * @property string $siteUid
 * @property string|null $name
 * @property string|null $description
 * @property int|null $iconAssetId
 * @property string|null $themeBackground
 * @property string|null $themeForeground
 * @property string|null $themeAccent
 * @property string|null $themeAccentForeground
 * @property bool $showInDiscover
 */
class PublicationSettingsRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%standardsite_publicationsettings}}';
    }

    public static function findBySiteUid(string $siteUid): ?self
    {
        /** @var self|null */
        return self::find()->where(['siteUid' => $siteUid])->one();
    }

    /**
     * Get the settings for a site, or a new (unsaved) record with defaults.
     */
    public static function forSite(string $siteUid): self
    {
        $record = self::findBySiteUid($siteUid);
        if (!$record) {
            $record = new self();
            $record->siteUid = $siteUid;
            $record->showInDiscover = true;
        }
        return $record;
    }
}
