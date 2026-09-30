<?php

namespace App\Actions\Pins;

use App\Enums\PinStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\PinImportRejected;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Add a batch of result checker PINs to a service's stock (admin only).
 *
 * Input is text: one PIN per line, or CSV lines "PIN" / "PIN,serial number"
 * (an optional header row starting with "PIN" is skipped). Pasted text and an
 * uploaded CSV both end up here.
 *
 * Business rules:
 *  - All or nothing. If any line is invalid, repeats another line in the
 *    batch, or matches a PIN already in stock (for ANY service), nothing is
 *    imported and every problem is reported by line number. Assumption: a
 *    partially-imported batch is harder to reconcile against the supplier's
 *    list than a clean re-upload.
 *  - Duplicates are detected by ResultCheckerPin::hash() (PINs are encrypted
 *    non-deterministically); the unique index on pin_hash is the final guard
 *    if two admins upload overlapping batches at the same moment.
 *  - Problem messages never repeat PIN values.
 */
class ImportPinBatch
{
    /**
     * Largest batch accepted in one upload.
     *
     * @var int
     */
    public const MAX_LINES = 5000;

    /**
     * Characters allowed in a PIN or serial (after removing spaces).
     *
     * @var string
     */
    private const VALUE_PATTERN = '/^[A-Z0-9\-]{4,'.ResultCheckerPin::MAX_LENGTH.'}$/';

    /**
     * First cells (normalised) that mark a CSV header row.
     *
     * @var array<int, string>
     */
    private const HEADER_CELLS = ['PIN', 'PINS', 'PINNUMBER', 'PIN_NUMBER', 'PINCODE'];

    /**
     * @param  Service  $service  A result checker service.
     * @param  string  $input  Pasted lines or CSV file contents.
     * @param  User  $admin  The uploader.
     * @return array{imported: int, batch_id: string}
     *
     * @throws BusinessRuleException If the uploader or service is not allowed.
     * @throws PinImportRejected If any line has a problem (nothing is imported).
     */
    public function handle(Service $service, string $input, User $admin): array
    {
        if (! $admin->isAdmin()) {
            throw new BusinessRuleException('Only admins can upload PINs.');
        }

        if (! $service->isResultChecker()) {
            throw new BusinessRuleException('PINs can only be added to a result checker service.');
        }

        [$rows, $problems] = self::parse($input);

        if ($rows === [] && $problems === []) {
            throw new PinImportRejected('No PINs found. Paste one PIN per line, or upload a CSV file.');
        }

        if (count($rows) > self::MAX_LINES) {
            throw new PinImportRejected('That batch has '.count($rows).' PINs; upload at most '.self::MAX_LINES.' at a time.');
        }

        $problems = $problems + self::duplicates_within_batch($rows) + self::duplicates_in_stock($rows);

        if ($problems !== []) {
            ksort($problems);

            throw new PinImportRejected(
                trans_choice('Nothing was imported: :count line needs fixing.|Nothing was imported: :count lines need fixing.', count($problems)),
                array_values($problems),
            );
        }

        $batch_id = (string) Str::ulid();

        try {
            DB::transaction(function () use ($rows, $service, $admin, $batch_id): void {
                foreach ($rows as $row) {
                    (new ResultCheckerPin(['service_id' => $service->id]))->forceFill([
                        'pin' => $row['pin'],
                        'serial_number' => $row['serial_number'],
                        'pin_hash' => $row['hash'],
                        'status' => PinStatus::Available,
                        'batch_id' => $batch_id,
                        'added_by' => $admin->id,
                    ])->save();
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw new PinImportRejected('Nothing was imported: some of these PINs were added to stock while you were uploading. Upload the batch again to see which lines.');
        }

        return ['imported' => count($rows), 'batch_id' => $batch_id];
    }

    /**
     * Whether a normalised PIN or serial number has an acceptable format.
     *
     * @param  string  $value  Already passed through ResultCheckerPin::normalise().
     * @return bool
     */
    public static function is_valid_value(string $value): bool
    {
        return preg_match(self::VALUE_PATTERN, $value) === 1;
    }

    /**
     * Split the input into PIN rows and line problems.
     *
     * @param  string  $input
     * @return array{0: array<int, array{pin: string, serial_number: string|null, hash: string}>, 1: array<int, string>}
     */
    private static function parse(string $input): array
    {
        $lines = preg_split('/\R/u', (string) preg_replace('/^\xEF\xBB\xBF/', '', $input)) ?: [];
        $rows = [];
        $problems = [];
        $seen_data = false;

        foreach ($lines as $index => $line) {
            $line_number = $index + 1;

            if (trim($line) === '') {
                continue;
            }

            $cells = array_map(fn (?string $cell): string => ResultCheckerPin::normalise((string) $cell), str_getcsv($line, escape: ''));
            $cells = array_values(array_filter($cells, fn (string $cell): bool => $cell !== ''));

            $is_header = ! $seen_data && in_array($cells[0] ?? '', self::HEADER_CELLS, true);
            $seen_data = true;

            if ($is_header) {
                continue;
            }

            $problem = match (true) {
                count($cells) > 2 => 'expected a PIN, or "PIN,serial number"; found '.count($cells).' values.',
                ! preg_match(self::VALUE_PATTERN, $cells[0] ?? '') => 'the PIN must be 4–'.ResultCheckerPin::MAX_LENGTH.' letters, digits or dashes.',
                isset($cells[1]) && ! preg_match(self::VALUE_PATTERN, $cells[1]) => 'the serial number must be 4–'.ResultCheckerPin::MAX_LENGTH.' letters, digits or dashes.',
                default => null,
            };

            if ($problem !== null) {
                $problems[$line_number] = "Line {$line_number}: {$problem}";

                continue;
            }

            $rows[$line_number] = [
                'pin' => $cells[0],
                'serial_number' => $cells[1] ?? null,
                'hash' => ResultCheckerPin::hash($cells[0]),
            ];
        }

        return [$rows, $problems];
    }

    /**
     * Lines that repeat an earlier line of the same batch.
     *
     * @param  array<int, array{pin: string, serial_number: string|null, hash: string}>  $rows
     * @return array<int, string>
     */
    private static function duplicates_within_batch(array $rows): array
    {
        $first_line_by_hash = [];
        $problems = [];

        foreach ($rows as $line_number => $row) {
            if (isset($first_line_by_hash[$row['hash']])) {
                $problems[$line_number] = "Line {$line_number}: duplicate of line {$first_line_by_hash[$row['hash']]}.";

                continue;
            }

            $first_line_by_hash[$row['hash']] = $line_number;
        }

        return $problems;
    }

    /**
     * Lines whose PIN is already in stock (any service, any status).
     *
     * @param  array<int, array{pin: string, serial_number: string|null, hash: string}>  $rows
     * @return array<int, string>
     */
    private static function duplicates_in_stock(array $rows): array
    {
        $existing = [];

        foreach (array_chunk(array_unique(array_column($rows, 'hash')), 500) as $hashes) {
            $existing += ResultCheckerPin::query()
                ->with('service:id,name')
                ->whereIn('pin_hash', $hashes)
                ->get(['id', 'service_id', 'pin_hash'])
                ->mapWithKeys(fn (ResultCheckerPin $pin): array => [$pin->pin_hash => (string) $pin->service?->name])
                ->all();
        }

        $problems = [];

        foreach ($rows as $line_number => $row) {
            if (isset($existing[$row['hash']])) {
                $problems[$line_number] = "Line {$line_number}: this PIN is already in stock ({$existing[$row['hash']]}).";
            }
        }

        return $problems;
    }
}
