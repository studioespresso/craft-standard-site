<?php

namespace studioespresso\standardsite\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $siteUid
 * @property string $rkey
 * @property string $atUri
 * @property string|null $cid
 */
class PublicationRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%standardsite_publications}}';
    }

    public static function findBySiteUid(string $siteUid): ?self
    {
        return self::find()->where(['siteUid' => $siteUid])->one();
    }

    public static function getAtUri(string $siteUid): ?string
    {
        return self::findBySiteUid($siteUid)?->atUri;
    }
}
