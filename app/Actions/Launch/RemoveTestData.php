<?php

namespace App\Actions\Launch;

use App\Enums\UserRole;
use App\Models\OrderDocument;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * LAUNCH DAY ONLY (GO-LIVE.md): remove the orders, customers and PINs left by
 * smoke tests, so the live site starts empty. Before launch every order is a
 * test order by definition, so this removes ALL of them.
 *
 * Removed: every order (including soft-deleted ones) with its payments,
 * receipts, consultation bookings, documents (rows AND files), status
 * history, testimonials, the PINs allocated to them, document/PIN access
 * logs, notifications, customer accounts and failed jobs.
 *
 * Kept: staff and admin accounts, the service catalogue and prices, posts,
 * FAQs, site settings, consultation slots, course search data, and PIN stock
 * that was never sold unless $include_pin_stock (smoke-test stock).
 *
 * One transaction for the database; files are deleted only after it commits.
 */
class RemoveTestData
{
    /**
     * Rows that handle() would remove, per table.
     *
     * @param  bool  $include_pin_stock  Also count unsold (available) PINs.
     * @return array<string, int>
     */
    public function counts(bool $include_pin_stock): array
    {
        return [
            'orders' => DB::table('orders')->count(),
            'payments' => DB::table('payments')->count(),
            'receipts' => DB::table('receipts')->count(),
            'consultation_bookings' => DB::table('consultation_bookings')->count(),
            'order_documents' => DB::table('order_documents')->count(),
            'order_status_changes' => DB::table('order_status_changes')->count(),
            'testimonials' => DB::table('testimonials')->count(),
            'result_checker_pins' => $this->pins($include_pin_stock)->count(),
            'document_access_logs' => DB::table('document_access_logs')->count(),
            'notifications' => DB::table('notifications')->count(),
            'customer accounts' => $this->customers()->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ];
    }

    /**
     * @param  bool  $include_pin_stock  Also delete unsold (available) PINs.
     * @return array<string, int> Rows removed per table (and "files").
     */
    public function handle(bool $include_pin_stock): array
    {
        $paths = OrderDocument::query()->pluck('stored_path')->all();

        $removed = DB::transaction(function () use ($include_pin_stock): array {
            // Children before parents: payments, bookings, PINs and access logs RESTRICT deletes.
            return [
                'receipts' => DB::table('receipts')->delete(),
                'payments' => DB::table('payments')->delete(),
                'consultation_bookings' => DB::table('consultation_bookings')->delete(),
                'document_access_logs' => DB::table('document_access_logs')->delete(),
                'result_checker_pins' => $this->pins($include_pin_stock)->delete(),
                'testimonials' => DB::table('testimonials')->delete(),
                'order_documents' => DB::table('order_documents')->delete(),
                'order_status_changes' => DB::table('order_status_changes')->delete(),
                'orders' => DB::table('orders')->delete(),
                'notifications' => DB::table('notifications')->delete(),
                'customer accounts' => $this->customers()->delete(),
                'failed_jobs' => DB::table('failed_jobs')->delete(),
            ];
        });

        $disk = Storage::disk(OrderDocument::DISK);
        $files = 0;

        foreach ($paths as $path) {
            $files += $disk->delete($path) ? 1 : 0;
        }

        return $removed + ['files' => $files];
    }

    /**
     * @param  bool  $include_pin_stock
     * @return Builder
     */
    private function pins(bool $include_pin_stock): Builder
    {
        $query = DB::table('result_checker_pins');

        return $include_pin_stock ? $query : $query->whereNotNull('order_id');
    }

    /**
     * @return Builder
     */
    private function customers(): Builder
    {
        return DB::table('users')->where('role', UserRole::Customer->value);
    }
}
