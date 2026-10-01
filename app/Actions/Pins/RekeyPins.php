<?php

namespace App\Actions\Pins;

use App\Models\ResultCheckerPin;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * After an APP_KEY rotation: re-encrypt every PIN and serial number with the
 * new key and recompute pin_hash (an HMAC keyed with APP_KEY).
 *
 * Why it's needed: existing PINs still DECRYPT while the old key is in
 * APP_PREVIOUS_KEYS, but their pin_hash was made with the old key, so a new
 * upload of the same PIN would get a different hash and slip past the
 * duplicate check (ImportPinBatch, DeliverOneOffPin, the unique index).
 *
 * One transaction: either every PIN moves to the new key or none does, so
 * old and new hashes are never mixed. Safe to re-run (it just rewrites the
 * same values). Raw updates: no model events, timestamps or audit entries.
 */
class RekeyPins
{
    /**
     * @return int Number of PINs rewritten.
     *
     * @throws DecryptException If a PIN can't be
     *                          decrypted (the old key isn't in APP_PREVIOUS_KEYS). Nothing is changed.
     */
    public function handle(): int
    {
        return DB::transaction(function (): int {
            $rewritten = 0;

            ResultCheckerPin::query()->lazyById(500)->each(function (ResultCheckerPin $pin) use (&$rewritten): void {
                $plain_pin = $pin->pin;
                $plain_serial = $pin->serial_number;

                ResultCheckerPin::query()->whereKey($pin->getKey())->toBase()->update([
                    'pin' => Crypt::encryptString($plain_pin),
                    'serial_number' => $plain_serial === null ? null : Crypt::encryptString($plain_serial),
                    'pin_hash' => ResultCheckerPin::hash($plain_pin),
                ]);

                $rewritten++;
            });

            return $rewritten;
        });
    }
}
