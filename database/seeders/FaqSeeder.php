<?php

namespace Database\Seeders;

use App\Actions\Faqs\InstallOfficialFaqs;
use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * The 7 FAQs for PDF section 13.
 *
 * PENDING: the official wording goes in database/data/faqs_section_13.php.
 * While that file is empty, this seeds the DRAFTED stand-ins below (written
 * from what the site actually does). Once it holds the seven official
 * questions, this installs them instead and removes the untouched drafts
 * (InstallOfficialFaqs).
 *
 * Safe to re-run either way: questions are matched by text, and existing
 * answers are left as they are so admin edits are never overwritten.
 */
class FaqSeeder extends Seeder
{
    /**
     * Where the official wording is pasted (relative to database/).
     *
     * @var string
     */
    public const OFFICIAL_FILE = 'data/faqs_section_13.php';

    /**
     * Drafted stand-ins: [question, answer]. Blank lines in answers separate paragraphs.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const DRAFTED = [
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
     * @param  InstallOfficialFaqs  $install_official_faqs
     * @return void
     */
    public function run(InstallOfficialFaqs $install_official_faqs): void
    {
        $official = self::official_faqs();

        if ($official !== []) {
            $result = $install_official_faqs->handle($official, self::DRAFTED);

            $this->command?->info(sprintf('Official FAQs: %d added, %d drafted removed.', $result['added'], $result['removed']));

            foreach ($result['kept_drafts'] as $question) {
                $this->command?->warn('Kept a drafted FAQ an admin edited; review or delete it in Website > FAQ: '.$question);
            }

            return;
        }

        foreach (self::DRAFTED as $position => [$question, $answer]) {
            Faq::query()->firstOrCreate(
                ['question' => $question],
                ['answer' => $answer, 'sort_order' => $position + 1, 'is_active' => true],
            );
        }
    }

    /**
     * The official wording from OFFICIAL_FILE ([] while still pending).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function official_faqs(): array
    {
        $path = database_path(self::OFFICIAL_FILE);

        return is_file($path) ? (array) require $path : [];
    }
}
