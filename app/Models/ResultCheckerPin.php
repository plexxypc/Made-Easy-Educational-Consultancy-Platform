<?php

namespace App\Models;

use App\Enums\PinStatus;
use Database\Factories\ResultCheckerPinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One result checker PIN (and optional card serial number) in stock.
 *
 * `pin` and `serial_number` are encrypted at rest (encrypted cast) and hidden
 * from toArray()/JSON. They are only ever shown in plaintext to the customer on
 * their own order, or to staff/admin through the audited "Show PIN" action on
 * that order (LogPinAccess). List screens show MASK instead.
 *
 * Nothing is mass-assignable except service_id: status, order and the PIN are
 * set by ImportPinBatch, AllocatePinsToOrder and DeliverOneOffPin.
 *
 * @property int $id
 * @property int $service_id
 * @property string $pin Decrypted PIN.
 * @property string|null $serial_number Decrypted card serial number, if the batch had one.
 * @property string $pin_hash HMAC of the normalised PIN (duplicate detection).
 * @property PinStatus $status
 * @property int|null $order_id
 * @property string|null $batch_id ULID shared by the PINs of one upload (null for one-off PINs).
 * @property int|null $added_by User who uploaded or entered it.
 * @property Carbon|null $allocated_at
 * @property Carbon|null $revealed_at
 * @property Carbon $created_at
 */
#[Fillable(['service_id'])]
class ResultCheckerPin extends Model
{
    /** @use HasFactory<ResultCheckerPinFactory> */
    use HasFactory;

    /**
     * What list screens show instead of the PIN (same length for every PIN).
     *
     * @var string
     */
    public const MASK = '••••••••••••';

    /**
     * Longest PIN/serial accepted, after removing spaces.
     *
     * @var int
     */
    public const MAX_LENGTH = 64;

    /**
     * Never serialise the secret columns.
     *
     * @var array<int, string>
     */
    protected $hidden = ['pin', 'serial_number', 'pin_hash'];

    /**
     * Default attribute values (mirrors the column default).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'available',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pin' => 'encrypted',
            'serial_number' => 'encrypted',
            'status' => PinStatus::class,
            'order_id' => 'integer',
            'allocated_at' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    /**
     * Canonical form of a PIN or serial: whitespace removed, upper case.
     * Assumption: PINs are compared case-insensitively and printed cards may
     * space the digits ("1234 5678 9012"); dashes are kept as typed.
     *
     * @param  string  $value
     * @return string
     */
    public static function normalise(string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/u', '', $value));
    }

    /**
     * Keyed hash of a PIN, used by the unique index and duplicate checks.
     *
     * Business rule: keyed with APP_KEY, so the hashes alone can't be
     * brute-forced back into PINs. Rotating APP_KEY needs a re-hash (and the
     * old key in APP_PREVIOUS_KEYS to keep decrypting existing PINs).
     *
     * @param  string  $pin
     * @return string
     */
    public static function hash(string $pin): string
    {
        return hash_hmac('sha256', self::normalise($pin), (string) config('app.key'));
    }

    /**
     * Unallocated PINs.
     *
     * @param  Builder<ResultCheckerPin>  $query
     * @return void
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('status', PinStatus::Available->value)->whereNull('order_id');
    }

    /**
     * The result checker service this PIN is for.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The order it was allocated to (null while available).
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    /**
     * Who uploaded or entered it.
     *
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Staff/admin views of this PIN (shared audit table with documents).
     *
     * @return HasMany<DocumentAccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class);
    }
}
