<?php

namespace studioespresso\standardsite\services;

use yii\base\Component;

/**
 * Handles DPoP (Demonstrating Proof-of-Possession) for AT Protocol OAuth.
 *
 * Generates ES256 key pairs and creates DPoP proof JWTs for authenticated API requests.
 */
class DPopService extends Component
{
    private ?string $nonce = null;

    /**
     * Generate a new ES256 key pair and return it as a JWK array (including private 'd' parameter).
     */
    public function generateKey(): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($key === false) {
            throw new \RuntimeException('Failed to generate ES256 key pair');
        }

        $details = openssl_pkey_get_details($key);
        openssl_pkey_export($key, $pem);

        return [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => self::base64UrlEncode($details['ec']['x']),
            'y' => self::base64UrlEncode($details['ec']['y']),
            'd' => self::base64UrlEncode($details['ec']['d']),
            'pem' => $pem,
        ];
    }

    /**
     * Create a DPoP proof JWT.
     *
     * @param array $jwk Full JWK (with 'd' and 'pem')
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Target URL
     * @param string|null $accessToken If present, includes 'ath' (access token hash)
     * @param string|null $nonce Server-provided DPoP nonce
     */
    public function createProof(array $jwk, string $method, string $url, ?string $accessToken = null, ?string $nonce = null): string
    {
        $header = [
            'typ' => 'dpop+jwt',
            'alg' => 'ES256',
            'jwk' => [
                'kty' => $jwk['kty'],
                'crv' => $jwk['crv'],
                'x' => $jwk['x'],
                'y' => $jwk['y'],
            ],
        ];

        $payload = [
            'jti' => bin2hex(random_bytes(16)),
            'htm' => strtoupper($method),
            'htu' => $url,
            'iat' => time(),
        ];

        // Use provided nonce, fall back to stored nonce
        $useNonce = $nonce ?? $this->nonce;
        if ($useNonce !== null) {
            $payload['nonce'] = $useNonce;
        }

        // Access token hash for resource requests
        if ($accessToken !== null) {
            $payload['ath'] = self::base64UrlEncode(hash('sha256', $accessToken, true));
        }

        return $this->signJwt($header, $payload, $jwk['pem']);
    }

    /**
     * Store a DPoP nonce received from the server for subsequent requests.
     */
    public function setNonce(string $nonce): void
    {
        $this->nonce = $nonce;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    /**
     * Sign a JWT with ES256 using the PEM private key.
     */
    private function signJwt(array $header, array $payload, string $pem): string
    {
        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signingInput = "{$headerEncoded}.{$payloadEncoded}";

        $privateKey = openssl_pkey_get_private($pem);
        if ($privateKey === false) {
            throw new \RuntimeException('Invalid PEM private key');
        }

        $signed = '';
        if (!openssl_sign($signingInput, $signed, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign DPoP proof');
        }

        // OpenSSL returns DER-encoded signature, convert to raw R||S for JWS
        $signature = self::derToRaw($signed);

        return "{$signingInput}." . self::base64UrlEncode($signature);
    }

    /**
     * Convert DER-encoded ECDSA signature to raw R||S (64 bytes for P-256).
     */
    private static function derToRaw(string $der): string
    {
        $len = strlen($der);
        if ($len < 8) {
            throw new \RuntimeException('Invalid DER signature: too short');
        }

        $offset = 2; // Skip SEQUENCE tag + length

        // R
        if ($offset >= $len || ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid DER signature');
        }
        $offset++;
        if ($offset >= $len) {
            throw new \RuntimeException('Invalid DER signature');
        }
        $rLen = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        // S
        if ($offset >= $len || ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid DER signature');
        }
        $offset++;
        if ($offset >= $len) {
            throw new \RuntimeException('Invalid DER signature');
        }
        $sLen = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $sLen);

        // Pad/trim to 32 bytes each
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
