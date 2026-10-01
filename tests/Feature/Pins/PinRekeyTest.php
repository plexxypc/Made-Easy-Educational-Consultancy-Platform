<?php

namespace Tests\Feature\Pins;

use App\Models\ResultCheckerPin;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * APP_KEY rotation (README, "Rotating APP_KEY"): with the old key in
 * APP_PREVIOUS_KEYS, PINs still decrypt; `pins:rekey` then moves their
 * encryption and duplicate-check hash to the new key.
 */
class PinRekeyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Before the re-key a re-uploaded PIN would not be recognised as a
     * duplicate; afterwards it is, and the PINs decrypt with the new key alone.
     *
     * @return void
     */
    public function test_rekey_after_app_key_rotation(): void
    {
        $old_key = (string) config('app.key');
        $pin = ResultCheckerPin::factory()->create(['pin' => '1234 5678 9012', 'pin_hash' => ResultCheckerPin::hash('1234 5678 9012')]);
        $this->assertTrue(ResultCheckerPin::query()->where('pin_hash', ResultCheckerPin::hash('123456789012'))->exists());
        $serial_number = $pin->serial_number;

        $this->use_keys('base64:'.base64_encode(Encrypter::generateKey(config('app.cipher'))), [$old_key]);

        $this->assertSame('1234 5678 9012', $pin->fresh()->pin);
        $this->assertFalse(ResultCheckerPin::query()->where('pin_hash', ResultCheckerPin::hash('123456789012'))->exists());

        $this->artisan('pins:rekey', ['--force' => true])->expectsOutputToContain('PINs re-keyed: 1.')->assertSuccessful();

        $this->use_keys((string) config('app.key'), []);

        $fresh = $pin->fresh();
        $this->assertSame('1234 5678 9012', $fresh->pin);
        $this->assertSame($serial_number, $fresh->serial_number);
        $this->assertTrue(ResultCheckerPin::query()->where('pin_hash', ResultCheckerPin::hash('123456789012'))->exists());
    }

    /**
     * Without the old key the command refuses and changes nothing.
     *
     * @return void
     */
    public function test_rekey_without_the_old_key_changes_nothing(): void
    {
        $pin = ResultCheckerPin::factory()->create();
        $raw_before = $pin->getRawOriginal('pin');

        $this->use_keys('base64:'.base64_encode(Encrypter::generateKey(config('app.cipher'))), []);

        $this->artisan('pins:rekey', ['--force' => true])->assertFailed();
        $this->assertSame($raw_before, ResultCheckerPin::query()->whereKey($pin->id)->toBase()->value('pin'));
    }

    /**
     * Switch APP_KEY / APP_PREVIOUS_KEYS for the rest of the test.
     *
     * @param  string  $key
     * @param  array<int, string>  $previous_keys
     * @return void
     */
    private function use_keys(string $key, array $previous_keys): void
    {
        config(['app.key' => $key, 'app.previous_keys' => $previous_keys]);

        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }
}
