<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $event_id
 * @property string $guest_token
 * @property int $upload_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['event_id', 'guest_token', 'upload_count'])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Guest $guest): void {
            $guest->guest_token ??= self::newGuestToken();
        });
    }

    public static function newGuestToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (self::where('guest_token', $token)->exists());

        return $token;
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<EventPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class);
    }

    /**
     * `shot_limit` is per guest, not per event.
     */
    public function hasReachedShotLimit(): bool
    {
        $limit = $this->event->shot_limit;

        return $limit !== null && $limit > 0 && $this->upload_count >= $limit;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'upload_count' => 'integer',
        ];
    }
}
