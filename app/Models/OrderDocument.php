<?php

namespace App\Models;

use Database\Factories\OrderDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A file uploaded against an order, stored on the private "local" disk.
 *
 * @property int $id
 * @property int $order_id
 * @property string|null $field_key The service "file" field this answers, if any.
 * @property string $original_filename
 * @property string $stored_path Relative to the private "local" disk.
 * @property string $mime_type
 * @property int $size Bytes.
 * @property Carbon $uploaded_at
 */
#[Fillable(['order_id', 'field_key', 'original_filename', 'stored_path', 'mime_type', 'size'])]
class OrderDocument extends Model
{
    /** @use HasFactory<OrderDocumentFactory> */
    use HasFactory;

    /**
     * Disk every order document lives on. Must never be a public disk.
     *
     * @var string
     */
    public const DISK = 'local';

    /**
     * `uploaded_at` doubles as created_at; there is no updated_at.
     *
     * @var string
     */
    public const CREATED_AT = 'uploaded_at';

    /**
     * @var null
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * The order this document belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Audit trail of staff/admin views.
     *
     * @return HasMany<DocumentAccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class);
    }
}
