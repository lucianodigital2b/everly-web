<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property string $qr_code_token
 * @property string $status
 * @property bool $public
 * @property string $name
 * @property string|null $title
 * @property CarbonImmutable|null $event_date
 * @property int|null $shot_limit
 * @property int|null $participant_limit
 * @property int|null $base_participant_limit
 * @property string $reveal_time
 * @property CarbonImmutable|null $reveal_at
 * @property string $filter
 * @property string|null $tier
 * @property bool $is_revealed
 * @property string|null $cover_image_url
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Plan $plan
 */
#[Fillable([
    'name', 'title', 'plan_id', 'event_date', 'shot_limit', 'participant_limit',
    'reveal_time', 'reveal_at', 'filter', 'tier', 'is_revealed', 'cover_image_url',
    'public', 'expires_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const REVEAL_INSTANT = 'Instant';

    public const REVEAL_END_OF_EVENT = 'End of Event';

    public const REVEAL_SCHEDULED = 'Scheduled';

    /**
     * Mirror the DB defaults in memory, so a freshly created event serialises
     * these as booleans rather than nulls — the client's types expect bools.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'public' => false,
        'is_revealed' => false,
        'status' => self::STATUS_PENDING_PAYMENT,
        'reveal_time' => self::REVEAL_INSTANT,
        'filter' => 'none',
    ];

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            $event->qr_code_token ??= self::newQrToken();
        });
    }

    public static function newQrToken(): string
    {
        do {
            $token = Str::lower(Str::random(24));
        } while (self::where('qr_code_token', $token)->exists());

        return $token;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<EventPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class);
    }

    /** @return HasMany<Guest, $this> */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<GuestPackPurchase, $this> */
    public function guestPackPurchases(): HasMany
    {
        return $this->hasMany(GuestPackPurchase::class);
    }

    /**
     * Recompute `participant_limit` from the guest-pack ledger and persist it.
     *
     * Capacity is the event's pre-pack base plus every applied pack, or
     * unlimited (null) if the base was unlimited or any applied pack grants it.
     * Because it is derived from the ledger rather than mutated in place, a
     * refund that flips a pack to "reversed" simply drops out of the sum — the
     * limit reverses correctly, and the baseline floor guarantees it never
     * falls below the free tier's 5 guests.
     */
    public function recomputeParticipantLimit(): void
    {
        $applied = $this->guestPackPurchases()
            ->where('status', GuestPackPurchase::STATUS_APPLIED)
            ->get();

        if ($applied->contains(fn (GuestPackPurchase $p): bool => $p->grants_unlimited)) {
            $this->participant_limit = null;
            $this->save();

            return;
        }

        // A null captured base means the event was already unlimited; packs
        // can't add to infinity, so it stays unlimited.
        if ($this->base_participant_limit === null) {
            $this->participant_limit = null;
            $this->save();

            return;
        }

        $baseline = (int) config('guest_packs.baseline_participants');
        $total = $this->base_participant_limit + (int) $applied->sum('participants_delta');

        $this->participant_limit = max($baseline, $total);
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Flip the event live. Called when a free plan is chosen at creation time,
     * or when a PIX payment settles. `expires_at` is only derived from the plan
     * when the client didn't pin an explicit end date on the event.
     */
    public function activate(?CarbonImmutable $at = null): void
    {
        $at ??= Date::now();

        $this->status = self::STATUS_ACTIVE;
        $this->activated_at ??= $at;
        $this->expires_at ??= $at->copy()->addDays($this->plan->duration_days);

        $this->syncRevealState();
        $this->save();
    }

    /**
     * Whether the gallery should currently be visible to the owner, per the
     * reveal rules in the spec. `is_revealed` is a cached projection of this;
     * once true it stays true, so an owner never loses access to a gallery
     * they've already seen.
     */
    public function shouldBeRevealed(): bool
    {
        if ($this->is_revealed) {
            return true;
        }

        if (! $this->isActive()) {
            return false;
        }

        return match ($this->reveal_time) {
            self::REVEAL_INSTANT => true,
            self::REVEAL_END_OF_EVENT => $this->hasExpired(),
            self::REVEAL_SCHEDULED => $this->reveal_at !== null && $this->reveal_at->isPast(),
            default => false,
        };
    }

    /**
     * Recompute `is_revealed` without writing. Callers persist it themselves so
     * a read path can refresh the flag in one save alongside other changes.
     */
    public function syncRevealState(): void
    {
        $this->is_revealed = $this->shouldBeRevealed();
    }

    /**
     * Guests may still upload while the event is live and un-expired, regardless
     * of whether the owner can see the gallery yet.
     */
    public function acceptsUploads(): bool
    {
        if (! $this->isActive() || $this->hasExpired()) {
            return false;
        }

        $cap = $this->uploadCap();

        return $cap === null || $this->photos()->count() < $cap;
    }

    /**
     * Total photos allowed on the event: whichever of the plan cap is set.
     */
    public function uploadCap(): ?int
    {
        return $this->plan->uploadCap();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public' => 'boolean',
            'is_revealed' => 'boolean',
            'event_date' => 'datetime',
            'reveal_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'shot_limit' => 'integer',
            'participant_limit' => 'integer',
            'base_participant_limit' => 'integer',
        ];
    }
}
