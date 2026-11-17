<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GuestPackPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded RevenueCat webhook (or sync call) touching an event's guest
 * capacity. Doubles as the audit log and the ledger the effective
 * participant_limit is recomputed from.
 *
 * @property int $id
 * @property int|null $event_id
 * @property int|null $user_id
 * @property string|null $rc_event_id
 * @property string|null $transaction_id
 * @property string $type
 * @property string|null $product_id
 * @property int|null $participants_delta
 * @property bool $grants_unlimited
 * @property string $status
 * @property string $source
 * @property array<string, mixed> $raw
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Event|null $event
 */
#[Fillable([
    'event_id', 'user_id', 'rc_event_id', 'transaction_id', 'type', 'product_id',
    'participants_delta', 'grants_unlimited', 'status', 'source', 'raw',
])]
class GuestPackPurchase extends Model
{
    /** @use HasFactory<GuestPackPurchaseFactory> */
    use HasFactory;

    // RevenueCat event types we act on.
    public const TYPE_PURCHASE = 'NON_RENEWING_PURCHASE';

    // RevenueCat has no REFUND event: a refunded purchase arrives as a
    // CANCELLATION whose cancel_reason is CUSTOMER_SUPPORT (Apple support
    // granted the refund). Other cancel reasons don't apply to a consumable.
    public const TYPE_CANCELLATION = 'CANCELLATION';

    public const CANCEL_REASON_REFUND = 'CUSTOMER_SUPPORT';

    // Row lifecycle.
    /** A purchase currently crediting capacity. */
    public const STATUS_APPLIED = 'applied';

    /** A purchase whose credit has been undone by a refund. */
    public const STATUS_REVERSED = 'reversed';

    /** The refund row itself (audit; carries the negative delta). */
    public const STATUS_REVERSAL = 'reversal';

    /** Recorded but not credited: unknown SKU, duplicate, or no-op. */
    public const STATUS_IGNORED = 'ignored';

    /** Recorded but could not be tied to an event. */
    public const STATUS_UNMATCHED = 'unmatched';

    public const SOURCE_WEBHOOK = 'webhook';

    public const SOURCE_SYNC = 'sync';

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'participants_delta' => 'integer',
            'grants_unlimited' => 'boolean',
            'raw' => 'array',
        ];
    }
}
