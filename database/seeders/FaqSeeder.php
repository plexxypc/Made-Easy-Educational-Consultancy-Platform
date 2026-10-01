<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * The 7 FAQs for PDF section 13.
 *
 * CLIENT REVIEW: the PDF's exact wording wasn't available when this was
 * written, so these questions and answers are DRAFTED from what the site
 * actually does. Check them against the PDF, then edit in the back office
 * (Website > FAQ). Safe to re-run: questions are matched by text, and
 * existing ones are left as they are so admin edits are never overwritten.
 */
class FaqSeeder extends Seeder
{
    /**
     * [question, answer]. Blank lines in answers separate paragraphs.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const FAQS = [
        [
            'What services do you offer?',
            "We help with WAEC, NECO, GCE and NABTEB registration, JAMB services, admission guidance for universities, polytechnics and colleges of education, result checker PINs, and document services such as certificates, affidavits and verification.\n\nWe also offer online Mathematics tutorials, arranged on WhatsApp.",
        ],
        [
            'How do I request a service?',
            "Choose the service on our Services page and fill in the short form. You don't need an account. You'll get a reference number straight away, and our team will contact you with the next steps.",
        ],
        [
            'How do I pay?',
            'Pay online through Paystack by card, bank transfer or USSD, using the "Pay now" button on your request page. Your request is only marked as paid once Paystack confirms the payment to us, and you get a receipt by email.',
        ],
        [
            'How can I track my request?',
            'Use "Track your request" with your reference number and the email or phone number you gave us. If you have an account, your requests are also listed under My Requests.',
        ],
        [
            'How quickly will I get my result checker PIN?',
            "Straight away in most cases. As soon as your payment is confirmed, the PIN appears on your request page and is sent to your email. If it doesn't arrive within a few minutes, contact us with your reference number.",
        ],
        [
            'Can you help me choose a course or school for admission?',
            "Yes. Request Admission Guidance or book a consultation and tell us your JAMB score, O'Level results and preferred course. You can also check courses and cut-off marks yourself with our course search.",
        ],
        [
            'Are my documents safe with you?',
            'Yes. Documents you upload are stored privately and are never public. Only you and our team can open them, and every time a staff member opens one it is recorded.',
        ],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        foreach (self::FAQS as $position => [$question, $answer]) {
            Faq::query()->firstOrCreate(
                ['question' => $question],
                ['answer' => $answer, 'sort_order' => $position + 1, 'is_active' => true],
            );
        }
    }
}
