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
            return null;
        }

        $decrypted = Craft::$app->getSecurity()->decryptByKey($decoded);

        return $decrypted === false ? null : $decrypted;
    }
}
