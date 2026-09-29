<?php

namespace Tests\Feature\PublicSite;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Mail\ServiceRequestReceivedMail;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Generic "Request this service" flow (guest checkout, no payment).
 */
class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var ServiceCategory
     */
    private ServiceCategory $category;

    /**
     * Service with one field of each kind, including a required file.
     *
     * @var Service
     */
    private Service $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake(OrderDocument::DISK);

        $this->category = ServiceCategory::factory()->create(['slug' => 'document-services']);
        $this->service = Service::factory()->for($this->category, 'category')->create([
            'name' => 'Birth Certificate',
            'price' => 1_500_000,
            'customer_fields' => [
                ['key' => 'state_of_origin', 'label' => 'State of origin', 'type' => 'select', 'required' => true, 'options' => ['Osun', 'Oyo']],
                ['key' => 'year_of_birth', 'label' => 'Year of birth', 'type' => 'number', 'required' => true, 'min' => 1900, 'max' => 2030],
                ['key' => 'notes', 'label' => 'Anything else?', 'type' => 'textarea', 'required' => false],
                ['key' => 'nin_slip', 'label' => 'NIN slip', 'type' => 'file', 'required' => true],
            ],
        ]);
    }

    /**
     * The form renders the service's own fields.
     *
     * @return void
     */
    public function test_form_renders_custom_fields(): void
    {
        $this->get($this->show_url())
            ->assertOk()
            ->assertSee('Request this service')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="fields[state_of_origin]"', false)
            ->assertSee('<option value="Osun"', false)
            ->assertSee('name="fields[year_of_birth]"', false)
            ->assertSee('type="number"', false)
            ->assertSee('name="fields[notes]"', false)
            ->assertSee('name="fields[nin_slip]"', false)
            ->assertSee('type="file"', false)
            ->assertSee('₦15,000');
    }

    /**
     * A guest submission creates a pending order with guest details, answers and the uploaded file.
     *
     * @return void
     */
    public function test_guest_request_creates_order_with_document(): void
    {
        $response = $this->post($this->request_url(), [
            'name' => 'Ada Obi',
            'phone' => '0803 123 4567',
            'email' => 'ada@example.com',
            'fields' => [
                'state_of_origin' => 'Osun',
                'year_of_birth' => '2004',
                'notes' => '',
                'nin_slip' => $this->real_pdf('my NIN slip.pdf'),
                'not_a_field' => 'ignored',
            ],
        ]);

        $order = Order::query()->sole();

        $response->assertRedirect(URL::signedRoute('service-requests.received', ['order' => $order]));

        $this->assertNull($order->user_id);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(1_500_000, $order->total_amount);
        $this->assertSame('Ada Obi', $order->guest_name);
        $this->assertSame('0803 123 4567', $order->guest_phone);
        $this->assertSame('ada@example.com', $order->guest_email);
        $this->assertSame($this->service->id, $order->service_id);
        $this->assertSame(
            ['state_of_origin' => 'Osun', 'year_of_birth' => 2004, 'notes' => null, 'nin_slip' => 'my NIN slip.pdf'],
            $order->field_answers,
        );

        $document = $order->documents()->sole();
        $this->assertSame('nin_slip', $document->field_key);
        $this->assertSame('my NIN slip.pdf', $document->original_filename);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertStringStartsWith("order-documents/{$order->id}/", $document->stored_path);
        $this->assertStringEndsWith('.pdf', $document->stored_path);
        Storage::disk(OrderDocument::DISK)->assertExists($document->stored_path);
        $this->assertStringNotContainsString('public', $document->stored_path);

        Mail::assertQueued(ServiceRequestReceivedMail::class, fn (ServiceRequestReceivedMail $mail): bool => $mail->hasTo('ada@example.com') && $mail->order->is($order));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Your request has been received.')
            ->assertSee("Reference <strong class=\"whitespace-nowrap\">{$order->reference}</strong>. We'll be in touch.", false)
            ->assertSee('No payment has been taken.')
            ->assertDontSee('ada@example.com');
    }

    /**
     * The uploaded file is served back through the existing audited document route.
     *
     * @return void
     */
    public function test_uploaded_file_is_served_by_existing_document_route(): void
    {
        $this->post($this->request_url(), $this->valid_payload());
        $document = OrderDocument::query()->sole();

        $this->get(route('order-documents.show', $document))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get(route('order-documents.show', $document))->assertForbidden();

        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user)->get(route('order-documents.show', $document))->assertOk();

        $this->assertDatabaseHas('document_access_logs', ['order_document_id' => $document->id, 'user_id' => $staff_user->id]);
    }

    /**
     * Required contact details and fields are enforced; nothing is created on failure.
     *
     * @return void
     */
    public function test_required_fields_are_validated(): void
    {
        $this->from($this->show_url())
            ->post($this->request_url(), ['fields' => ['state_of_origin' => 'Lagos', 'year_of_birth' => '1800']])
            ->assertRedirect($this->show_url())
            ->assertSessionHasErrors(['name', 'phone', 'email', 'fields.state_of_origin', 'fields.year_of_birth', 'fields.nin_slip'])
            ->assertSessionDoesntHaveErrors(['fields.notes']);

        $this->assertDatabaseCount('orders', 0);
        Mail::assertNothingQueued();
    }

    /**
     * A required file field can't be skipped: everything else valid, no file, nothing created.
     *
     * @return void
     */
    public function test_missing_required_file_blocks_submission(): void
    {
        $payload = $this->valid_payload();
        unset($payload['fields']['nin_slip']);

        $this->from($this->show_url())
            ->post($this->request_url(), $payload)
            ->assertRedirect($this->show_url())
            ->assertSessionHasErrors(['fields.nin_slip' => 'The NIN slip field is required.'])
            ->assertSessionDoesntHaveErrors(['name', 'phone', 'email', 'fields.state_of_origin', 'fields.year_of_birth']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_documents', 0);
        Mail::assertNothingQueued();
    }

    /**
     * An optional file field may be left empty.
     *
     * @return void
     */
    public function test_optional_file_can_be_omitted(): void
    {
        $this->service->update(['customer_fields' => [
            ['key' => 'supporting_letter', 'label' => 'Supporting letter', 'type' => 'file', 'required' => false],
        ]]);

        $this->post($this->request_url(), ['name' => 'Ada Obi', 'phone' => '08031234567', 'email' => 'ada@example.com'])
            ->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertArrayNotHasKey('supporting_letter', $order->field_answers ?? []);
        $this->assertSame(0, $order->documents()->count());
    }

    /**
     * Uploads are checked by content: a disallowed type, or a script renamed to .pdf, is rejected.
     *
     * @return void
     */
    public function test_disallowed_uploads_are_rejected(): void
    {
        $this->post($this->request_url(), $this->valid_payload(['nin_slip' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain')]))
            ->assertSessionHasErrors(['fields.nin_slip']);

        $script_path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($script_path, "<?php echo 'not a pdf';\n");
        $renamed_script = new UploadedFile($script_path, 'nin.pdf', 'application/pdf', null, true);

        $this->post($this->request_url(), $this->valid_payload(['nin_slip' => $renamed_script]))
            ->assertSessionHasErrors(['fields.nin_slip']);

        $too_large = UploadedFile::fake()->create('big.pdf', config('madeeasy.uploads.max_kilobytes') + 1, 'application/pdf');

        $this->post($this->request_url(), $this->valid_payload(['nin_slip' => $too_large]))
            ->assertSessionHasErrors(['fields.nin_slip']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_documents', 0);
        $this->assertSame([], Storage::disk(OrderDocument::DISK)->allFiles());
    }

    /**
     * Inactive services can't be requested.
     *
     * @return void
     */
    public function test_inactive_service_cannot_be_requested(): void
    {
        $this->service->update(['is_active' => false]);

        $this->post($this->request_url(), $this->valid_payload())->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Quote orders start at ₦0 awaiting an admin "Set Total"; the email says so.
     *
     * @return void
     */
    public function test_quote_request_awaits_a_quote(): void
    {
        $quote_service = Service::factory()->for($this->category, 'category')->create(['fulfilment_type' => FulfilmentType::Quote]);

        $this->post(route('services.request', ['category' => $this->category, 'service' => $quote_service]), [
            'name' => 'Ada Obi',
            'phone' => '+234 803 123 4567',
            'email' => 'ada@example.com',
        ])->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame(0, $order->total_amount);
        $this->assertTrue($order->awaitsQuote());

        Mail::assertQueued(ServiceRequestReceivedMail::class, function (ServiceRequestReceivedMail $mail): bool {
            $mail->assertSeeInHtml('Price on request');
            $mail->assertSeeInHtml('tell you the price before you pay anything');
            $mail->assertDontSeeInHtml('₦0');

            return true;
        });
    }

    /**
     * A signed-in customer's request is linked to their account.
     *
     * @return void
     */
    public function test_signed_in_customer_owns_their_request(): void
    {
        $customer = User::factory()->create(['name' => 'Chidi Customer', 'email' => 'chidi@example.com']);

        $this->actingAs($customer)->get($this->show_url())
            ->assertSee('value="Chidi Customer"', false)
            ->assertSee('value="chidi@example.com"', false);

        $this->actingAs($customer)->post($this->request_url(), $this->valid_payload());

        $this->assertSame($customer->id, Order::query()->sole()->user_id);
    }

    /**
     * The confirmation page needs the signed URL (no browsing other references).
     *
     * @return void
     */
    public function test_confirmation_page_requires_signature(): void
    {
        $order = Order::factory()->guest()->create();

        $this->get(route('service-requests.received', ['order' => $order]))->assertForbidden();
        $this->get(URL::signedRoute('service-requests.received', ['order' => $order]))->assertOk();
    }

    /**
     * Admission Guidance uses the same generic form with its seeded fields.
     *
     * @return void
     */
    public function test_admission_guidance_uses_generic_form(): void
    {
        (new ServiceCatalogueSeeder)->run();

        $guidance = Service::query()->with('category')->where('slug', Service::ADMISSION_GUIDANCE_SLUG)->sole();
        $url = route('services.show', ['category' => $guidance->category, 'service' => $guidance]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Price on request')
            ->assertSee('JAMB Score')
            ->assertSee('Preferred Institution')
            ->assertSee('<option value="DE"', false);

        $this->post(route('services.request', ['category' => $guidance->category, 'service' => $guidance]), [
            'name' => 'Tunde Ade',
            'phone' => '08031234567',
            'email' => 'tunde@example.com',
            'fields' => [
                'jamb_score' => '247',
                'olevel_results' => '5 credits including English and Maths',
                'preferred_course' => 'Nursing',
                'preferred_institution' => 'OAU',
                'admission_mode' => 'UTME',
                'question_or_problem' => 'Which schools can I apply to?',
            ],
        ])->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame($guidance->id, $order->service_id);
        $this->assertSame(247, $order->field_answers['jamb_score']);
        $this->assertSame('Nursing', $order->field_answers['preferred_course']);
    }

    /**
     * @return string
     */
    private function show_url(): string
    {
        return route('services.show', ['category' => $this->category, 'service' => $this->service]);
    }

    /**
     * @return string
     */
    private function request_url(): string
    {
        return route('services.request', ['category' => $this->category, 'service' => $this->service]);
    }

    /**
     * A valid submission for $this->service, with optional field overrides.
     *
     * @param  array<string, mixed>  $field_overrides
     * @return array<string, mixed>
     */
    private function valid_payload(array $field_overrides = []): array
    {
        return [
            'name' => 'Ada Obi',
            'phone' => '08031234567',
            'email' => 'ada@example.com',
            'fields' => array_merge([
                'state_of_origin' => 'Oyo',
                'year_of_birth' => '2004',
                'nin_slip' => $this->real_pdf('nin.pdf'),
            ], $field_overrides),
        ];
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
