<?php

declare(strict_types=1);

namespace App\Payments\Gateways\Easypaisa;

use RuntimeException;

/**
 * Easypaisa hosted checkout "merchantHashedReq".
 *
 *   1. Sort the request fields by name (ASCII), skipping merchantHashedReq and empty values.
 *   2. Build "name=value&name=value…".
 *   3. AES-128-ECB encrypt it with the 16-character hash key (PKCS#7 padding); base64.
 */
final class EasypaisaHash
{
    /**
     * @param  array<string, string>  $fields
     */
    public static function make(array $fields, string $hashKey): string
    {
        $signed = array_filter(
            $fields,
            fn (string $value, string $name): bool => $name !== 'merchantHashedReq' && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        ksort($signed, SORT_STRING);

        $pairs = [];
        foreach ($signed as $name => $value) {
            $pairs[] = "{$name}={$value}";
        }

        $encrypted = openssl_encrypt(implode('&', $pairs), 'AES-128-ECB', $hashKey, OPENSSL_RAW_DATA);

        if ($encrypted === false) {
            throw new RuntimeException('Could not sign the Easypaisa request — check the hash key.');
        }

        return base64_encode($encrypted);
    }
}
