<?php

namespace App\Console\Commands;

use App\Actions\Launch\RemoveTestData as RemoveTestDataAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Launch-day clean-up (GO-LIVE.md). Without --force it only reports what it
 * would remove. NEVER run it once real customers have placed orders.
 */
class RemoveTestData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'launch:remove-test-data
        {--include-pin-stock : Also delete unsold PINs (smoke-test stock); real stock is uploaded afterwards}
        {--force : Actually delete (otherwise this is a dry run)}';

    /**
     * @var string
     */
    protected $description = 'LAUNCH DAY ONLY: remove every order, payment, customer account and test PIN left by smoke tests.';

    /**
     * @param  RemoveTestDataAction  $remove_test_data
     * @return int
     */
    public function handle(RemoveTestDataAction $remove_test_data): int
    {
        $include_pin_stock = (bool) $this->option('include-pin-stock');

        $this->table(['Would remove', 'Rows'], collect($remove_test_data->counts($include_pin_stock))->map(fn (int $count, string $table): array => [$table, $count])->values()->all());

        $this->line('Kept: staff/admin accounts (review these in Users), services and prices, posts, FAQs, site settings, consultation slots, course search data'
            .($include_pin_stock ? '.' : ', unsold PIN stock (add --include-pin-stock to remove it).'));
        $this->table(['Staff / admin kept', 'Role'], User::query()->where('role', '!=', UserRole::Customer->value)->orderBy('email')->get()
            ->map(fn (User $user): array => [$user->email, $user->role->value])->all());

        if (! $this->option('force')) {
            $this->warn('Dry run: nothing was deleted. Back up first, then run again with --force.');

            return self::SUCCESS;
        }

        $removed = $remove_test_data->handle($include_pin_stock);

        $this->info('Removed: '.collect($removed)->map(fn (int $count, string $table): string => "{$table} {$count}")->implode(', ').'.');

        return self::SUCCESS;
    }
}
