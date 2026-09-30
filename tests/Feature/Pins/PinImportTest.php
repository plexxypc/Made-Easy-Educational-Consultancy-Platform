<?php

namespace Tests\Feature\Pins;

use App\Actions\Pins\ImportPinBatch;
use App\Enums\FulfilmentType;
use App\Enums\PinStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\PinImportRejected;
use App\Filament\Resources\ResultCheckerPins\Pages\ListResultCheckerPins;
use App\Filament\Resources\ResultCheckerPins\ResultCheckerPinResource;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bulk PIN upload (admin only): parsing, duplicate rejection within the batch
 * and against existing stock, encryption at rest, and masked listing.
 */
class PinImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var User
     */
    private User $admin_user;

    /**
     * @var Service
     */
    private Service $waec;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin_user = User::factory()->admin()->create();
        $this->waec = Service::factory()->create(['name' => 'WAEC Result Checker', 'fulfilment_type' => FulfilmentType::Instant, 'price' => 650_000]);
    }

    /**
     * Pasted lines and CSV (with header and serial numbers) are imported as one batch, encrypted.
     *
     * @return void
     */
    public function test_imports_pasted_and_csv_pins(): void
    {
        $result = app(ImportPinBatch::class)->handle(
            $this->waec,
            "PIN,Serial\n1111 2222 3333,WRN000000001\n\n444455556666,WRN000000002\r\n777788889999\n",
            $this->admin_user,
        );

        $this->assertSame(3, $result['imported']);

        $pins = ResultCheckerPin::query()->orderBy('id')->get();
        $this->assertSame(['111122223333', '444455556666', '777788889999'], $pins->pluck('pin')->all());
        $this->assertSame(['WRN000000001', 'WRN000000002', null], $pins->pluck('serial_number')->all());
        $this->assertTrue($pins->every(fn (ResultCheckerPin $pin): bool => $pin->status === PinStatus::Available
            && $pin->order_id === null
            && $pin->batch_id === $result['batch_id']
            && $pin->added_by === $this->admin_user->id));

        $raw = DB::table('result_checker_pins')->where('id', $pins->first()->id)->first();
        $this->assertStringNotContainsString('111122223333', $raw->pin);
        $this->assertStringNotContainsString('WRN000000001', $raw->serial_number);
        $this->assertSame(ResultCheckerPin::hash('1111 2222 3333'), $raw->pin_hash);

        $this->assertArrayNotHasKey('pin', $pins->first()->toArray());
        $this->assertArrayNotHasKey('serial_number', $pins->first()->toArray());
        $this->assertArrayNotHasKey('pin_hash', $pins->first()->toArray());
    }

    /**
     * A PIN repeated inside the batch (even spaced or cased differently) rejects the whole batch.
     *
     * @return void
     */
    public function test_rejects_duplicates_within_the_batch(): void
    {
        try {
            app(ImportPinBatch::class)->handle($this->waec, "111122223333\n999988887777\n1111 2222 3333\nabcd-1234\nABCD-1234", $this->admin_user);
            $this->fail('The batch should have been rejected.');
        } catch (PinImportRejected $exception) {
            $this->assertSame(['Line 3: duplicate of line 1.', 'Line 5: duplicate of line 4.'], $exception->problems);
            $this->assertStringNotContainsString('1111', implode(' ', $exception->problems).$exception->getMessage());
        }

        $this->assertDatabaseCount('result_checker_pins', 0);
    }

    /**
     * A PIN already in stock (for this or ANY other service, in any status) rejects the whole batch.
     *
     * @return void
     */
    public function test_rejects_duplicates_against_existing_stock(): void
    {
        $neco = Service::factory()->create(['name' => 'NECO Result Checker', 'fulfilment_type' => FulfilmentType::Instant]);
        ResultCheckerPin::factory()->for($this->waec)->create(['pin' => '111122223333', 'pin_hash' => ResultCheckerPin::hash('111122223333')]);
        ResultCheckerPin::factory()->for($neco)->create(['pin' => '555566667777', 'pin_hash' => ResultCheckerPin::hash('555566667777'), 'status' => PinStatus::Revealed]);

        try {
            app(ImportPinBatch::class)->handle($this->waec, "999900001111\n1111 2222 3333\n555566667777", $this->admin_user);
            $this->fail('The batch should have been rejected.');
        } catch (PinImportRejected $exception) {
            $this->assertSame([
                'Line 2: this PIN is already in stock (WAEC Result Checker).',
                'Line 3: this PIN is already in stock (NECO Result Checker).',
            ], $exception->problems);
        }

        $this->assertDatabaseCount('result_checker_pins', 2);
        $this->assertFalse(ResultCheckerPin::query()->where('pin_hash', ResultCheckerPin::hash('999900001111'))->exists());
    }

    /**
     * Malformed lines are reported by number and nothing is imported.
     *
     * @return void
     */
    public function test_rejects_malformed_lines(): void
    {
        try {
            app(ImportPinBatch::class)->handle($this->waec, "123456789012\n12\n1234;5678\n111122223333,SER1,extra", $this->admin_user);
            $this->fail('The batch should have been rejected.');
        } catch (PinImportRejected $exception) {
            $this->assertCount(3, $exception->problems);
            $this->assertStringStartsWith('Line 2:', $exception->problems[0]);
            $this->assertStringStartsWith('Line 3:', $exception->problems[1]);
            $this->assertStringStartsWith('Line 4:', $exception->problems[2]);
        }

        $this->assertDatabaseCount('result_checker_pins', 0);
    }

    /**
     * Only admins upload, and only to result checker services.
     *
     * @return void
     */
    public function test_only_admins_can_upload_to_result_checkers(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(ImportPinBatch::class)->handle($this->waec, '123456789012', User::factory()->staff()->create());
    }

    /**
     * Non-result-checker services can't hold PINs.
     *
     * @return void
     */
    public function test_cannot_upload_to_other_services(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(ImportPinBatch::class)->handle(Service::factory()->create(), '123456789012', $this->admin_user);
    }

    /**
     * The Filament upload action imports a pasted batch and reports rejected batches.
     *
     * @return void
     */
    public function test_filament_upload_action(): void
    {
        $this->actingAs($this->admin_user);

        Livewire::test(ListResultCheckerPins::class)
            ->callAction('uploadPins', ['service_id' => $this->waec->id, 'pins' => "123456789012\n210987654321"])
            ->assertHasNoActionErrors()
            ->assertNotified('2 PINs added to WAEC Result Checker.');

        $this->assertSame(2, $this->waec->pins()->count());

        Livewire::test(ListResultCheckerPins::class)
            ->callAction('uploadPins', ['service_id' => $this->waec->id, 'pins' => "333344445555\n123456789012"])
            ->assertNotified('Nothing was imported: 1 line needs fixing.');

        $this->assertSame(2, $this->waec->pins()->count());
    }

    /**
     * The stock list is admin only and never renders PIN values.
     *
     * @return void
     */
    public function test_stock_list_is_admin_only_and_masked(): void
    {
        $pin = ResultCheckerPin::factory()->for($this->waec)->create(['pin' => '864213579246', 'pin_hash' => ResultCheckerPin::hash('864213579246'), 'serial_number' => 'WRN555555555']);

        $this->actingAs(User::factory()->staff()->create())
            ->get(ResultCheckerPinResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($this->admin_user)
            ->get(ResultCheckerPinResource::getUrl('index'))
            ->assertOk()
            ->assertSee('WAEC Result Checker')
            ->assertSee(ResultCheckerPin::MASK)
            ->assertDontSee('864213579246')
            ->assertDontSee('WRN555555555');

        Livewire::test(ListResultCheckerPins::class)
            ->assertCanSeeTableRecords([$pin])
            ->assertDontSeeHtml('864213579246');
    }
}
