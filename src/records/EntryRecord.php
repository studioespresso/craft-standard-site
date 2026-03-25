<?php

namespace studioespresso\standardsite\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $entryId
 * @property int $siteId
 * @property string $collection
 * @property string $rkey
 * @property string $atUri
 * @property string|null $cid
 */
class EntryRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%standardsite_records}}';
    }
}
