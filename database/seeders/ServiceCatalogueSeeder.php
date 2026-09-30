<?php

namespace Database\Seeders;

use App\Enums\FulfilmentType;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

/**
 * Initial service catalogue from the requirements document (sections 2–6).
 *
 * Pricing rules (all amounts in KOBO):
 * - Prices given in the document are used as-is (WAEC Result Checker ₦6,500,
 *   Admission Consultation ₦20,000). These services are active, except
 *   Admission Consultation, which an admin activates once consultation
 *   slots have been added and booking has been tested.
 * - Manual/instant services WITHOUT a documented price get PLACEHOLDER_PRICE and
 *   (Service::PLACEHOLDER_PRICE) are seeded INACTIVE, so nothing can be bought at a made-up price. An admin
 *   sets the real price and activates them.
 * - Quote services have price 0 (staff set the order total per request) and are active.
 *
 * Idempotent and non-destructive: rows are matched on slug and only created if
 * missing, so re-seeding never overwrites prices/flags edited by an admin.
 */
class ServiceCatalogueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        foreach ($this->catalogue() as $sort_order => $category_data) {
            $category = ServiceCategory::query()->firstOrCreate(
                ['slug' => $category_data['slug']],
                [
                    'name' => $category_data['name'],
                    'description' => $category_data['description'],
                    'sort_order' => $sort_order + 1,
                ],
            );

            foreach ($category_data['services'] as $service_data) {
                $price = $service_data['price'] ?? null;
                $fulfilment_type = $service_data['type'];

                if ($fulfilment_type === FulfilmentType::Quote) {
                    $price = 0;
                    $is_active = true;
                } else {
                    $is_active = $service_data['active'] ?? $price !== null;
                    $price ??= Service::PLACEHOLDER_PRICE;
                }

                Service::query()->firstOrCreate(
                    ['slug' => $service_data['slug']],
                    [
                        'service_category_id' => $category->id,
                        'name' => $service_data['name'],
                        'description' => null,
                        'price' => $price,
                        'is_active' => $is_active,
                        'fulfilment_type' => $fulfilment_type,
                        'customer_fields' => $service_data['fields'] ?? [],
                    ],
                );
            }
        }
    }

    /**
     * The catalogue, in display order. `price` is kobo; omitted = not in the document.
     *
     * @return array<int, array{name: string, slug: string, description: string, services: array<int, array<string, mixed>>}>
     */
    private function catalogue(): array
    {
        $manual = FulfilmentType::Manual;
        $quote = FulfilmentType::Quote;
        $instant = FulfilmentType::Instant;

        return [
            [
                'name' => 'Examination Services',
                'slug' => 'examination-services',
                'description' => 'WAEC, NECO, GCE and NABTEB registration, certificate processing, O\'Level and JAMB result services.',
                'services' => [
                    ['name' => 'WAEC Registration', 'slug' => 'waec-registration', 'type' => $manual],
                    ['name' => 'NECO Registration', 'slug' => 'neco-registration', 'type' => $manual],
                    ['name' => 'GCE Registration', 'slug' => 'gce-registration', 'type' => $manual],
                    ['name' => 'NABTEB Registration', 'slug' => 'nabteb-registration', 'type' => $manual],
                    ['name' => 'WAEC/NECO Certificate Processing', 'slug' => 'waec-neco-certificate-processing', 'type' => $quote],
                    ['name' => "O'Level Result Verification", 'slug' => 'olevel-result-verification', 'type' => $manual],
                    ['name' => "O'Level Result Upload", 'slug' => 'olevel-result-upload', 'type' => $manual],
                    ['name' => 'JAMB Result Checking', 'slug' => 'jamb-result-checking', 'type' => $manual],
                    ['name' => 'JAMB Result Printout', 'slug' => 'jamb-result-printout', 'type' => $manual],
                ],
            ],
            [
                'name' => 'Admission Services',
                'slug' => 'admission-services',
                'description' => 'JAMB, CAPS, screening and admission support for universities, polytechnics and colleges of education.',
                'services' => [
                    ['name' => 'JAMB Registration Assistance', 'slug' => 'jamb-registration-assistance', 'type' => $manual],
                    ['name' => 'JAMB Profile Creation Assistance', 'slug' => 'jamb-profile-creation-assistance', 'type' => $manual],
                    ['name' => 'JAMB CAPS Guidance', 'slug' => 'jamb-caps-guidance', 'type' => $manual],
                    ['name' => "O'Level Upload Assistance", 'slug' => 'olevel-upload-assistance', 'type' => $manual],
                    ['name' => 'Admission Screening Registration', 'slug' => 'admission-screening-registration', 'type' => $manual],
                    ['name' => 'Admission Screening Calculation', 'slug' => 'admission-screening-calculation', 'type' => $manual],
                    ['name' => 'Admission Status Checking', 'slug' => 'admission-status-checking', 'type' => $manual],
                    [
                        'name' => 'Admission Guidance',
                        'slug' => 'admission-guidance',
                        'type' => $quote,
                        'fields' => $this->admission_guidance_fields(),
                    ],
                    ['name' => 'Course Selection Guidance', 'slug' => 'course-selection-guidance', 'type' => $manual],
                    ['name' => 'School Selection Guidance', 'slug' => 'school-selection-guidance', 'type' => $manual],
                    ['name' => 'Post-UTME Assistance', 'slug' => 'post-utme-assistance', 'type' => $manual],
                    ['name' => 'Admission Monitoring', 'slug' => 'admission-monitoring', 'type' => $manual],
                    ['name' => 'Direct Entry Guidance', 'slug' => 'direct-entry-guidance', 'type' => $manual],
                    ['name' => 'University Admission Assistance', 'slug' => 'university-admission-assistance', 'type' => $manual],
                    ['name' => 'Polytechnic Admission Assistance', 'slug' => 'polytechnic-admission-assistance', 'type' => $manual],
                    ['name' => 'College of Education Admission Assistance', 'slug' => 'college-of-education-admission-assistance', 'type' => $manual],
                    // Section 6 (consultation booking): booked on /book-consultation.
                    // Seeded inactive: an admin activates it once slots are set up.
                    ['name' => 'Admission Consultation', 'slug' => 'admission-consultation', 'type' => FulfilmentType::Consultation, 'price' => 2_000_000, 'active' => false],
                ],
            ],
            [
                'name' => 'Document Services',
                'slug' => 'document-services',
                'description' => 'Certificates, affidavits, NIN-related services, verification and other educational documentation.',
                'services' => [
                    ['name' => 'Document & Verification Services', 'slug' => 'document-verification-services', 'type' => $quote],
                    ['name' => 'State of Origin Certificate', 'slug' => 'state-of-origin-certificate', 'type' => $quote],
                    ['name' => 'LGA Certificate', 'slug' => 'lga-certificate', 'type' => $quote],
                    ['name' => 'Birth Certificate', 'slug' => 'birth-certificate', 'type' => $quote],
                    ['name' => 'Affidavit', 'slug' => 'affidavit', 'type' => $quote],
                    ['name' => 'Declaration of Age', 'slug' => 'declaration-of-age', 'type' => $quote],
                    ['name' => 'NIN-Related Services', 'slug' => 'nin-related-services', 'type' => $quote],
                    ['name' => 'Certificate Verification', 'slug' => 'certificate-verification', 'type' => $quote],
                    ['name' => 'WAEC/NECO/GCE/NABTEB Certificate Processing', 'slug' => 'waec-neco-gce-nabteb-certificate-processing', 'type' => $quote],
                    ['name' => 'Other Educational Documentation', 'slug' => 'other-educational-documentation', 'type' => $quote],
                ],
            ],
            [
                'name' => 'Result Checkers',
                'slug' => 'result-checkers',
                'description' => 'Buy WAEC, NECO, NABTEB and GCE result checker PINs online.',
                'services' => [
                    ['name' => 'WAEC Result Checker', 'slug' => 'waec-result-checker', 'type' => $instant, 'price' => 650_000],
                    ['name' => 'NECO Result Checker', 'slug' => 'neco-result-checker', 'type' => $instant],
                    ['name' => 'NABTEB Result Checker', 'slug' => 'nabteb-result-checker', 'type' => $instant],
                    ['name' => 'GCE Result Checker', 'slug' => 'gce-result-checker', 'type' => $instant],
                ],
            ],
        ];
    }

    /**
     * Fields from the "I Need Admission Guidance" button (requirements section 3).
     *
     * JAMB score is optional because Direct Entry applicants may not have a UTME score.
     * "Full Name" and "Phone Number" are not custom fields: every request form
     * already asks for name, phone and email (stored in orders.guest_*).
     *
     * @return array<int, array<string, mixed>>
     */
    private function admission_guidance_fields(): array
    {
        return [
            ['key' => 'jamb_score', 'label' => 'JAMB Score', 'type' => 'number', 'required' => false, 'min' => 0, 'max' => 400],
            ['key' => 'olevel_results', 'label' => "O'Level Results", 'type' => 'textarea', 'required' => true],
            ['key' => 'preferred_course', 'label' => 'Preferred Course', 'type' => 'text', 'required' => true],
            ['key' => 'preferred_institution', 'label' => 'Preferred Institution', 'type' => 'text', 'required' => true],
            ['key' => 'admission_mode', 'label' => 'UTME/DE', 'type' => 'select', 'required' => true, 'options' => ['UTME', 'DE']],
            ['key' => 'question_or_problem', 'label' => 'Question or Problem', 'type' => 'textarea', 'required' => true],
        ];
    }
}
