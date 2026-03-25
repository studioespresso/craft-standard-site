<?php

namespace studioespresso\standardsite\helpers;

/**
 * Generates AT Protocol TID (Timestamp Identifier) record keys.
 *
 * TIDs are 13-character base32-sortable strings encoding a microsecond timestamp
 * with a random clock identifier. They serve as record keys (rkeys) in AT Protocol.
 *
 * @see https://atproto.com/specs/record-key#record-key-type-tid
 */
class Tid
{
    private const CHARSET = '234567abcdefghijklmnopqrstuvwxyz';

    public static function generate(): string
    {
        $timestamp = (int)(microtime(true) * 1_000_000);
        $clockId = random_int(0, 1023);

        // TID is a 64-bit integer: timestamp in upper 54 bits, clock ID in lower 10 bits
        $tid = ($timestamp << 10) | $clockId;

        return self::encode($tid);
    }

    private static function encode(int $tid): string
    {
        $result = '';
        for ($i = 0; $i < 13; $i++) {
            $result = self::CHARSET[$tid & 0x1f] . $result;
            $tid >>= 5;
        }

        return $result;
    }
}
