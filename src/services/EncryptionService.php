<?php

namespace studioespresso\standardsite\services;

use Craft;
use yii\base\Component;

class EncryptionService extends Component
{
    public function encrypt(string $value): string
    {
        return base64_encode(Craft::$app->getSecurity()->encryptByKey($value));
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            Craft::error('[standard-site] Failed to base64 decode encrypted value', __METHOD__);
            return null;
        }

        $decrypted = Craft::$app->getSecurity()->decryptByKey($decoded);

        if ($decrypted === false) {
            Craft::error('[standard-site] Decryption failed — possible data corruption or security key mismatch', __METHOD__);
            return null;
        }

        return $decrypted;
    }
}
