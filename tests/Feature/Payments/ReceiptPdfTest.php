<?php

namespace Tests\Feature\Payments;

use App\Actions\Receipts\RenderReceiptPdf;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Tests\Concerns\ReadsPdfs;
use Tests\Concerns\SendsPaystackWebhooks;
use Tests\TestCase;

/**
 * The receipt email really carries the receipt as a PDF attachment, and each
 * PDF describes the order it was generated for (and no other).
 */
class ReceiptPdfTest extends TestCase
{
    use ReadsPdfs, RefreshDatabase, SendsPaystackWebhooks;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->use_paystack_test_key();
        Http::preventStrayRequests();
        Notification::fake();
        config(['mail.default' => 'array']);
    }

    /**
     * Two paid orders: each receipt email has one PDF attachment, named after
     * its receipt, whose text matches that order's receipt only.
     *
     * @return void
     */
    public function test_receipt_email_attaches_a_pdf_matching_the_order(): void
    {
        $ada_order = $this->guest_order('Ada Obi', 'ada@example.com', 'Birth Certificate', 1_500_000);
        $bola_order = $this->guest_order('Bola Ade', 'bola@example.com', 'Certificate Collection', 2_250_050);

        $this->pay_with_webhook($this->initiated_payment_for($ada_order))->assertOk();
        $this->pay_with_webhook($this->initiated_payment_for($bola_order))->assertOk();

        $ada_receipt = $ada_order->refresh()->receipt;
        $bola_receipt = $bola_order->refresh()->receipt;

        $ada_pdf = $this->receipt_attachment('ada@example.com', $ada_receipt);
        $bola_pdf = $this->receipt_attachment('bola@example.com', $bola_receipt);

        $ada_text = $this->pdf_text($ada_pdf->getBody());
        foreach ([
            'Payment receipt', $ada_receipt->number, $ada_order->reference, $ada_receipt->payment_reference,
            'Ada Obi', 'ada@example.com', 'Birth Certificate', '₦15,000.00', 'Successful',
            $ada_receipt->paidAtLabel(),
        ] as $expected) {
            $this->assertStringContainsString($expected, $ada_text);
        }
        foreach ([$bola_receipt->number, $bola_order->reference, 'Bola Ade', 'Certificate Collection', '₦22,500.50'] as $other_order_detail) {
            $this->assertStringNotContainsString($other_order_detail, $ada_text);
        }

        $bola_text = $this->pdf_text($bola_pdf->getBody());
        foreach ([$bola_receipt->number, $bola_order->reference, 'Bola Ade', 'Certificate Collection', '₦22,500.50'] as $expected) {
            $this->assertStringContainsString($expected, $bola_text);
        }
        $this->assertStringNotContainsString($ada_order->reference, $bola_text);

        // Same document as the receipt link on the order page.
        $this->assertSame($ada_text, $this->pdf_text(app(RenderReceiptPdf::class)->handle($ada_receipt)));
    }

    /**
     * The payment time is printed in Lagos time and labelled as such, on the
     * PDF, in the email and on the order page. 23:30 UTC on 30 September is
     * already 1 October in Lagos.
     *
     * @return void
     */
    public function test_receipt_time_is_shown_in_lagos_time_and_labelled(): void
    {
        $this->travelTo(Carbon::parse('2026-09-30 23:30:00', 'UTC'));
        $order = $this->guest_order('Ada Obi', 'ada@example.com', 'Birth Certificate', 1_500_000);
        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        $receipt = $order->refresh()->receipt;
        $expected = '1 October 2026, 12:30am (Lagos time, WAT)';
        $this->assertSame($expected, $receipt->paidAtLabel());

        $pdf_text = $this->pdf_text($this->receipt_attachment('ada@example.com', $receipt)->getBody());
        $this->assertStringContainsString($expected, $pdf_text);
        $this->assertStringNotContainsString('30 September', $pdf_text);

        /** @var SentMessage $sent */
        $sent = app('mailer')->getSymfonyTransport()->messages()
            ->first(fn (SentMessage $sent): bool => str_starts_with((string) $sent->getOriginalMessage()->getSubject(), 'Payment receipt'));
        $this->assertStringContainsString($expected, (string) $sent->getOriginalMessage()->getHtmlBody());
    }

    /**
     * The one PDF attached to the receipt email sent to this address.
     *
     * @param  string  $email
     * @param  Receipt  $receipt
     * @return DataPart
     */
    private function receipt_attachment(string $email, Receipt $receipt): DataPart
    {
        /** @var Collection<int, SentMessage> $messages */
        $messages = app('mailer')->getSymfonyTransport()->messages();

        $receipt_emails = $messages
            ->map(fn (SentMessage $sent): Email => $sent->getOriginalMessage())
            ->filter(fn (Email $message): bool => $message->getTo()[0]->getAddress() === $email
                && str_starts_with((string) $message->getSubject(), 'Payment receipt '.$receipt->number))
            ->values();

        $this->assertCount(1, $receipt_emails, "Expected one receipt email to {$email}.");

        $attachments = $receipt_emails[0]->getAttachments();
        $this->assertCount(1, $attachments);
        $attachment = $attachments[0];
        $this->assertSame('receipt-'.$receipt->number.'.pdf', $attachment->getFilename());
        $this->assertSame('application/pdf', $attachment->getMediaType().'/'.$attachment->getMediaSubtype());

        return $attachment;
    }

    /**
     * @param  string  $name
     * @param  string  $email
     * @param  string  $service_name
     * @param  int  $price  KOBO.
     * @return Order
     */
    private function guest_order(string $name, string $email, string $service_name, int $price): Order
    {
        $service = Service::factory()->create(['name' => $service_name, 'price' => $price]);

        return Order::factory()->guest()->for($service)->create([
            'guest_name' => $name,
            'guest_email' => $email,
            'guest_phone' => '08031234567',
        ]);
    }
}
