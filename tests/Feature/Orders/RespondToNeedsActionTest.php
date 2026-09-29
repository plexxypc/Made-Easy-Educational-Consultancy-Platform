<?php

namespace Tests\Feature\Orders;

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderStatusChange;
use App\Models\User;
use App\Notifications\CustomerRespondedToOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Customer responds to "needs action" from the tracking page.
 */
class RespondToNeedsActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var User
     */
    private User $staff_user;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake(OrderDocument::DISK);

        $this->staff_user = User::factory()->staff()->create();
    }

    /**
     * The needs-action page shows the staff note and the response form.
     *
     * @return void
     */
    public function test_needs_action_page_shows_note_and_form(): void
    {
        $order = $this->needs_action_order(OrderStatus::PendingPayment, 'Please upload a clearer NIN slip.');
        $this->look_up($order);

        $this->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertSee('What we need from you')
            ->assertSee('Please upload a clearer NIN slip.')
            ->assertSee('action="'.route('orders.respond', $order).'"', false)
            ->assertSee('name="documents[]"', false)
            ->assertSee('name="note"', false);
    }

    /**
     * A guest response adds the document and note and returns the order to pending_payment.
     *
     * @return void
     */
    public function test_guest_response_appends_document_and_note_and_restores_pending_payment(): void
    {
        Notification::fake();
        $admin_user = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $order = $this->needs_action_order(OrderStatus::PendingPayment, 'Please upload a clearer NIN slip.');
        $this->look_up($order);
        Mail::fake();

        $this->post(route('orders.respond', $order), [
            'note' => 'Here is a clearer scan.',
            'documents' => [$this->real_pdf('clear NIN.pdf')],
        ])->assertRedirect(route('orders.track.show', $order))
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);

        $document = $order->documents()->sole();
        $this->assertSame('clear NIN.pdf', $document->original_filename);
        $this->assertNull($document->field_key);
        $this->assertStringStartsWith("order-documents/{$order->id}/", $document->stored_path);
        Storage::disk(OrderDocument::DISK)->assertExists($document->stored_path);

        $response = $order->statusChanges()->first();
        $this->assertTrue($response->by_customer);
        $this->assertNull($response->changed_by);
        $this->assertSame(OrderStatus::NeedsAction, $response->from_status);
        $this->assertSame(OrderStatus::PendingPayment, $response->to_status);
        $this->assertSame('Here is a clearer scan.', $response->note);
        $this->assertSame('Customer', $response->actorLabel());

        Notification::assertSentTo([$this->staff_user, $admin_user], CustomerRespondedToOrder::class, fn (CustomerRespondedToOrder $notification): bool => $notification->order->is($order));
        Notification::assertNotSentTo($customer, CustomerRespondedToOrder::class);
        Mail::assertNotQueued(OrderStatusChangedMail::class);

        $this->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertDontSee('What we need from you')
            ->assertSee('Here is a clearer scan.')
            ->assertSee('clear NIN.pdf');
    }

    /**
     * An order that was in progress goes back to in_progress; a note alone is enough.
     *
     * @return void
     */
    public function test_owner_response_restores_in_progress(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::InProgress, $this->staff_user);
        app(ChangeOrderStatus::class)->handle($order->refresh(), OrderStatus::NeedsAction, $this->staff_user, 'Which year did you sit the exam?');

        $this->actingAs($customer)
            ->post(route('orders.respond', $order), ['note' => '2019'])
            ->assertRedirect(route('orders.track.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame(0, $order->documents()->count());

        $response = $order->statusChanges()->first();
        $this->assertSame($customer->id, $response->changed_by);
        $this->assertTrue($response->by_customer);
        $this->assertSame(OrderStatus::InProgress, $response->to_status);
    }

    /**
     * Staff get a Filament database notification linking to the order.
     *
     * @return void
     */
    public function test_staff_get_database_notification(): void
    {
        $order = $this->needs_action_order(OrderStatus::PendingPayment, 'Send your passport photo.');
        $this->look_up($order);

        $this->post(route('orders.respond', $order), ['documents' => [$this->real_pdf('photo.pdf')]])
            ->assertRedirect();

        $notification = $this->staff_user->notifications()->sole();
        $this->assertSame(CustomerRespondedToOrder::class, $notification->type);
        $this->assertSame('filament', $notification->data['format']);
        $this->assertSame("Customer responded to order #{$order->id}", $notification->data['title']);
        $this->assertStringContainsString('1 document uploaded', $notification->data['body']);
        $this->assertStringContainsString("/admin/orders/{$order->id}", json_encode($notification->data['actions'], JSON_UNESCAPED_SLASHES));
    }

    /**
     * Nothing to send: rejected, status unchanged.
     *
     * @return void
     */
    public function test_empty_response_is_rejected(): void
    {
        Notification::fake();
        $order = $this->needs_action_order(OrderStatus::PendingPayment, 'Send your passport photo.');
        $this->look_up($order);

        $this->post(route('orders.respond', $order), ['note' => '   '])
            ->assertSessionHasErrors('note');

        $this->assertSame(OrderStatus::NeedsAction, $order->refresh()->status);
        Notification::assertNothingSent();
    }

    /**
     * Uploads are validated exactly like the request form (type, size, content).
     *
     * @return void
     */
    public function test_disallowed_documents_are_rejected(): void
    {
        $order = $this->needs_action_order(OrderStatus::PendingPayment, 'Send your passport photo.');
        $this->look_up($order);

        $script_path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($script_path, "<?php echo 'not a pdf';\n");

        $this->post(route('orders.respond', $order), ['documents' => [UploadedFile::fake()->create('notes.txt', 5, 'text/plain')]])
            ->assertSessionHasErrors('documents.0');
        $this->post(route('orders.respond', $order), ['documents' => [new UploadedFile($script_path, 'photo.pdf', 'application/pdf', null, true)]])
            ->assertSessionHasErrors('documents.0');
        $this->post(route('orders.respond', $order), ['documents' => array_map(fn (int $number): UploadedFile => $this->real_pdf("doc{$number}.pdf"), range(1, 6))])
            ->assertSessionHasErrors('documents');

        $this->assertSame(OrderStatus::NeedsAction, $order->refresh()->status);
        $this->assertSame(0, $order->documents()->count());
        $this->assertSame([], Storage::disk(OrderDocument::DISK)->allFiles());
    }

    /**
     * Only a needs_action order accepts a response.
     *
     * @return void
     */
    public function test_response_rejected_when_not_needs_action(): void
    {
        Notification::fake();
        $order = Order::factory()->guest()->withStatus(OrderStatus::InProgress)->create(['guest_email' => 'ada@example.com']);
        $this->look_up($order);

        $this->post(route('orders.respond', $order), ['note' => 'Hello', 'documents' => [$this->real_pdf('extra.pdf')]])
            ->assertSessionHasErrors(['response' => 'This request is not waiting for anything from you.']);

        $this->assertSame(OrderStatus::InProgress, $order->refresh()->status);
        $this->assertSame(0, $order->documents()->count());
        $this->assertSame(0, OrderStatusChange::query()->count());
        $this->assertSame([], Storage::disk(OrderDocument::DISK)->allFiles());
        Notification::assertNothingSent();
    }

    /**
     * A second response (after the first restored the status) is refused.
     *
     * @return void
     */
    public function test_cannot_respond_twice(): void
    {
        $order = $this->needs_action_order(OrderStatus::PendingPayment, 'Send your passport photo.');
        $this->look_up($order);

        $this->post(route('orders.respond', $order), ['note' => 'First'])->assertSessionHasNoErrors();
        $this->post(route('orders.respond', $order), ['note' => 'Second'])->assertSessionHasErrors('response');

        $this->assertSame(1, $order->statusChanges()->where('by_customer', true)->count());
    }

    /**
     * Without a lookup (or ownership) nothing is saved and the visitor is sent to the lookup page.
     *
     * @return void
     */
    public function test_response_requires_access(): void
    {
        Notification::fake();
        $order = $this->needs_action_order(OrderStatus::PendingPayment, 'Send your passport photo.');

        $this->post(route('orders.respond', $order), ['note' => 'Sneaky'])
            ->assertRedirect(route('orders.track'));

        $this->actingAs(User::factory()->create())
            ->post(route('orders.respond', $order), ['note' => 'Sneaky'])
            ->assertRedirect(route('orders.track'));

        $this->assertSame(OrderStatus::NeedsAction, $order->refresh()->status);
        Notification::assertNothingSent();
    }

    /**
     * The status-change email links to the tracking page and asks the customer to respond.
     *
     * @return void
     */
    public function test_needs_action_email_links_to_tracking_page(): void
    {
        $order = $this->needs_action_order(OrderStatus::PendingPayment, 'Send your passport photo.');

        Mail::assertQueued(OrderStatusChangedMail::class, function (OrderStatusChangedMail $mail) use ($order): bool {
            $mail->assertSeeInHtml(route('orders.track.show', $order));
            $mail->assertSeeInHtml('Respond to this request');

            return true;
        });
    }

    /**
     * Guest order moved to needs_action by staff from the given status.
     *
     * @param  OrderStatus  $from_status
     * @param  string  $note
     * @return Order
     */
    private function needs_action_order(OrderStatus $from_status, string $note): Order
    {
        $order = Order::factory()->guest()->withStatus($from_status)->create(['guest_email' => 'ada@example.com']);

        app(ChangeOrderStatus::class)->handle($order, OrderStatus::NeedsAction, $this->staff_user, $note);

        return $order->refresh();
    }

    /**
     * @param  Order  $order
     * @return void
     */
    private function look_up(Order $order): void
    {
        $this->post(route('orders.track.lookup'), ['reference' => (string) $order->id, 'contact' => 'ada@example.com'])
            ->assertRedirect(route('orders.track.show', $order));
    }

    /**
     * An upload whose contents really are a PDF (so content sniffing sees application/pdf).
     *
     * @param  string  $client_name
     * @return UploadedFile
     */
    private function real_pdf(string $client_name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($path, $client_name, 'application/pdf', null, true);
    }
}
