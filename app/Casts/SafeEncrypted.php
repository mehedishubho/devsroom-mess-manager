<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The `encrypted` cast, minus the landmine.
 *
 * Ciphertext that doesn't match the current APP_KEY (DB restored from another
 * environment, APP_KEY regenerated) throws DecryptException and 500s whatever
 * touched it. For backup secrets that is the wrong failure mode: a secret we
 * cannot decrypt is functionally a secret we never stored. Read it as null —
 * the UI then shows "not saved", and re-entering the value re-encrypts it
 * under the current key. Matches the backup system's fail-open philosophy.
 */
class SafeEncrypted implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decrypt($value, false);
        } catch (DecryptException) {
            return null;
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return [$key => Crypt::encrypt((string) $value, false)];
    }
}
