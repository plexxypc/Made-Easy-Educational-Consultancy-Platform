<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates the database row only. Tests that stream the file must also put a
 * file at `stored_path` on the (faked) "local" disk.
 *
 * @extends Factory<OrderDocument>
 */
class OrderDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'original_filename' => 'nin-slip.pdf',
            'stored_path' => 'order-documents/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 2_000_000),
        ];
    }
}
