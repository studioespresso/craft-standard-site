<?php

namespace studioespresso\standardsite\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string|null $siteUid
 * @property string $handle
 * @property string|null $siteHandle
 * @property string $did
 * @property string $pdsUrl
 * @property string|null $accessToken
 * @property string|null $refreshToken
 * @property string|null $dpopKey
 * @property int|null $tokenExpiresAt
 */
class ConnectionRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%standardsite_connections}}';
    }
}
