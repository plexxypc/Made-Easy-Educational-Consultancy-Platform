<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Project security rule 5: every staff/admin view of a customer document is
 * written to document_access_logs (user id, document id, timestamp).
 */
class DocumentAccessLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Customer who owns the order.
     *
     * @var User
     */
    private User $owner;

    /**
     * Document on the owner's order, with a real file on the faked private disk.
     *
     * @var OrderDocument
     */
    private OrderDocument $order_document;

    /**
     * Create a stored document owned by a customer.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(OrderDocument::DISK);

        $this->owner = User::factory()->create();
        $this->order_document = OrderDocument::factory()
            ->for(Order::factory()->for($this->owner))
            ->create(['original_filename' => 'birth-certificate.pdf']);

        Storage::disk(OrderDocument::DISK)->put($this->order_document->stored_path, '%PDF-1.4 test');
    }

    /**
     * A staff member viewing the document creates exactly one audit row per view.
     *
     * @return void
     */
    public function test_staff_viewing_a_document_creates_an_access_log(): void
    {
        $staff_user = User::factory()->staff()->create();

        $this->travelTo(now()->startOfSecond());

        $response = $this->actingAs($staff_user)
            ->get(route('order-documents.show', $this->order_document));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.4 test', $response->streamedContent());

        $this->assertDatabaseCount('document_access_logs', 1);
        $this->assertDatabaseHas('document_access_logs', [
            'order_document_id' => $this->order_document->id,
            'user_id' => $staff_user->id,
            'ip_address' => '127.0.0.1',
            'viewed_at' => now()->toDateTimeString(),
        ]);

        $this->actingAs($staff_user)->get(route('order-documents.show', $this->order_document))->assertOk();
        $this->assertDatabaseCount('document_access_logs', 2);
    }

    /**
     * Admin views are logged too.
     *
     * @return void
     */
    public function test_admin_viewing_a_document_creates_an_access_log(): void
    {
        $admin_user = User::factory()->admin()->create();

        $this->actingAs($admin_user)
            ->get(route('order-documents.show', $this->order_document))
            ->assertOk();

        $this->assertDatabaseHas('document_access_logs', [
            'order_document_id' => $this->order_document->id,
            'user_id' => $admin_user->id,
        ]);
    }

    /**
     * The owner can open their own document; that is not logged.
     *
     * @return void
     */
    public function test_owner_viewing_own_document_is_not_logged(): void
    {
        $this->actingAs($this->owner)
            ->get(route('order-documents.show', $this->order_document))
            ->assertOk();

        $this->assertDatabaseCount('document_access_logs', 0);
    }

    /**
     * Another customer is refused and nothing is served or logged.
     *
     * @return void
     */
    public function test_other_customer_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('order-documents.show', $this->order_document))
            ->assertForbidden();

        $this->assertDatabaseCount('document_access_logs', 0);
    }

    /**
     * Guests are sent to login.
     *
     * @return void
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('order-documents.show', $this->order_document))
            ->assertRedirect('/login');

        $this->assertDatabaseCount('document_access_logs', 0);
    }
}
