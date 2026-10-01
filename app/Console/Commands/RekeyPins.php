<?php

namespace App\Console\Commands;

use App\Actions\Pins\RekeyPins as RekeyPinsAction;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Step 3 of the APP_KEY rotation procedure (README, "Rotating APP_KEY").
 */
class RekeyPins extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pins:rekey {--force : Run without asking (production)}';

    /**
     * @var string
     */
    protected $description = 'After rotating APP_KEY: re-encrypt result checker PINs and recompute their duplicate-check hashes with the new key.';

    /**
     * @param  RekeyPinsAction  $rekey_pins
     * @return int
     */
    public function handle(RekeyPinsAction $rekey_pins): int
    {
        if (config('app.previous_keys') === []) {
            $this->warn('APP_PREVIOUS_KEYS is empty. If APP_KEY was just changed, put the old key there first or the PINs cannot be decrypted.');
        }

        if (! $this->option('force') && ! $this->confirm('Rewrite every PIN with the current APP_KEY?', true)) {
            return self::FAILURE;
        }

        try {
            $count = $rekey_pins->handle();
        } catch (DecryptException) {
            $this->error('A PIN could not be decrypted with APP_KEY or APP_PREVIOUS_KEYS. Nothing was changed. Add the old key to APP_PREVIOUS_KEYS, recreate the containers, and run this again.');

            return self::FAILURE;
        }

        $this->info("PINs re-keyed: {$count}.");

        return self::SUCCESS;
    }
}
