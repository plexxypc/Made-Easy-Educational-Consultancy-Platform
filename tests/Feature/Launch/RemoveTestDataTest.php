<?php

namespace Tests\Feature\Launch;

use App\Enums\OrderStatus;
use App\Models\ConsultationBooking;
use App\Models\Faq;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `launch:remove-test-data` (GO-LIVE.md): a dry run changes nothing; with
 * --force every order and what hangs off it goes, while staff, admins,
 * catalogue and content stay.
 */
class RemoveTestDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_dry_run_then_removal(): void
    {
        Storage::fake(OrderDocument::DISK);
        $admin = User::factory()->admin()->create(['email' => 'owner@example.com']);
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();

        $paid_order = Order::factory()->withStatus(OrderStatus::Completed)->create(['user_id' => $customer->id]);
        $payment_id = DB::table('payments')->insertGetId([
            'order_id' => $paid_order->id, 'paystack_reference' => 'TEST-REF-1', 'amount' => 100_000, 'paid_amount' => 100_000,
            'status' => 'success', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('receipts')->insert([
            'payment_id' => $payment_id, 'order_reference' => $paid_order->reference, 'payment_reference' => 'TEST-REF-1',
            'customer_name' => 'Test', 'service_name' => 'Test', 'amount' => 100_000, 'payment_status' => 'success',
            'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $document = OrderDocument::factory()->for($paid_order)->create();
        Storage::disk(OrderDocument::DISK)->put($document->stored_path, '%PDF-1.4 test');
        DB::table('document_access_logs')->insert(['order_document_id' => $document->id, 'user_id' => $staff->id, 'viewed_at' => now()]);
        Testimonial::factory()->create(['order_id' => $paid_order->id]);
        ResultCheckerPin::factory()->create(['order_id' => $paid_order->id, 'status' => 'revealed']);
        $stock_pin = ResultCheckerPin::factory()->create();
        ConsultationBooking::factory()->create();
        Order::factory()->guest()->create()->delete();
        $faq = Faq::factory()->create();

        $this->artisan('launch:remove-test-data')->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame(3, Order::withTrashed()->count());

        $this->artisan('launch:remove-test-data', ['--force' => true])->assertSuccessful();

        foreach (['orders', 'payments', 'receipts', 'order_documents', 'document_access_logs', 'testimonials', 'consultation_bookings'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} still has rows.");
        }
        $this->assertFalse(Storage::disk(OrderDocument::DISK)->exists($document->stored_path));
        $this->assertSame([$stock_pin->id], ResultCheckerPin::query()->pluck('id')->all());
        $this->assertFalse(User::query()->whereKey($customer->id)->exists());
        $this->assertTrue(User::query()->whereKey($admin->id)->exists());
        $this->assertTrue(User::query()->whereKey($staff->id)->exists());
        $this->assertTrue($faq->exists && Faq::query()->whereKey($faq->id)->exists());
        $this->assertGreaterThan(0, Service::query()->count());

        $this->artisan('launch:remove-test-data', ['--force' => true, '--include-pin-stock' => true])->assertSuccessful();
        $this->assertSame(0, ResultCheckerPin::query()->count());
    }
}
