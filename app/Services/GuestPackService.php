<?php

namespace App\Services;

use App\Models\Event;
use App\Models\GuestPackPurchase;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Credits (and reverses) guest-pack capacity on events. Both the RevenueCat
 * webhook and the client-driven sync endpoint funnel through here so they
 * share one idempotent path: a transaction is credited at most once, no matter
 * which arrives first or how many times a webhook is retried.
 *
 * Every call records a row in `guest_pack_purchases` — the audit log — even
 * when nothing is credited, with a `status` that says why.
 */
class GuestPackService
{
    /**
     * Handle a decoded RevenueCat webhook body. Returns the recorded row, or
     * null when the delivery was a retry we had already processed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookEvent(array $payload): ?GuestPackPurchase
    {
        /** @var array<string, mixed> $event */
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];

        $rcEventId = isset($event['id']) ? (string) $event['id'] : null;

        // Webhook-level dedup: RevenueCat retries reuse the same event id.
        if ($rcEventId !== null && GuestPackPurchase::where('rc_event_id', $rcEventId)->exists()) {
            return null;
        }

        $type = (string) ($event['type'] ?? '');
        $transactionId = isset($event['transaction_id']) ? (string) $event['transaction_id'] : null;
        $productId = isset($event['product_id']) ? (string) $event['product_id'] : null;
        $targetEvent = $this->resolveEvent($event);

        $context = [
            'source' => GuestPackPurchase::SOURCE_WEBHOOK,
            'rc_event_id' => $rcEventId,
            'transaction_id' => $transactionId,
            'product_id' => $productId,
            'user_id' => $targetEvent !== null ? $targetEvent->user_id : $this->resolveUserId($event),
            'raw' => $payload,
        ];

        return match ($type) {
            GuestPackPurchase::TYPE_PURCHASE => $this->creditPurchase($targetEvent, $type, $context),
            GuestPackPurchase::TYPE_REFUND => $this->reverseRefund($type, $context),
            // Every webhook is recorded; types we don't act on are audited as ignored.
            default => $this->record($context, $type, GuestPackPurchase::STATUS_IGNORED, $targetEvent?->id),
        };
    }

    /**
     * Instant credit from the client (event known from the URL, purchase claimed
     * in the body). Shares the idempotent purchase path with the webhook.
     */
    public function syncPurchase(Event $event, User $user, string $productId, string $transactionId): GuestPackPurchase
    {
        return $this->creditPurchase($event, GuestPackPurchase::TYPE_PURCHASE, [
            'source' => GuestPackPurchase::SOURCE_SYNC,
            'rc_event_id' => null,
            'transaction_id' => $transactionId,
            'product_id' => $productId,
            'user_id' => $user->id,
            'raw' => [
                'source' => 'sync',
                'event_id' => $event->id,
                'product_id' => $productId,
                'transaction_id' => $transactionId,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function creditPurchase(?Event $event, string $type, array $context): GuestPackPurchase
    {
        $productId = $context['product_id'];
        $packs = config('guest_packs.packs');

        // Unknown SKU: audit it, credit nothing. Note that null is a *valid*
        // mapped value (unlimited), so check key presence, not the value.
        if ($productId === null || ! array_key_exists($productId, $packs)) {
            return $this->record($context, $type, GuestPackPurchase::STATUS_IGNORED, $event?->id);
        }

        if ($event === null) {
            return $this->record($context, $type, GuestPackPurchase::STATUS_UNMATCHED, null);
        }

        // Cross-path idempotency: this transaction's purchase was already
        // credited (via the other source, or an earlier delivery). Audit the
        // duplicate, don't credit twice.
        if ($context['transaction_id'] !== null && $this->purchaseAlreadyProcessed($context['transaction_id'])) {
            return $this->record($context, $type, GuestPackPurchase::STATUS_IGNORED, $event->id);
        }

        $credit = $packs[$productId]; // int, or null for unlimited

        return DB::transaction(function () use ($event, $type, $context, $credit): GuestPackPurchase {
            $this->captureBaseIfFirst($event);

            $row = $this->record(
                $context,
                $type,
                GuestPackPurchase::STATUS_APPLIED,
                $event->id,
                participantsDelta: $credit,
                grantsUnlimited: $credit === null,
            );

            $event->recomputeParticipantLimit();

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reverseRefund(string $type, array $context): GuestPackPurchase
    {
        $transactionId = $context['transaction_id'];

        /** @var Collection<int, GuestPackPurchase> $originals */
        $originals = $transactionId === null
            ? collect()
            : GuestPackPurchase::where('transaction_id', $transactionId)
                ->where('type', GuestPackPurchase::TYPE_PURCHASE)
                ->where('status', GuestPackPurchase::STATUS_APPLIED)
                ->get();

        // Nothing live to reverse: refund arrived before the purchase, for an
        // unknown transaction, or the purchase was already reversed.
        if ($originals->isEmpty()) {
            return $this->record($context, $type, GuestPackPurchase::STATUS_IGNORED, null);
        }

        return DB::transaction(function () use ($originals, $type, $context): GuestPackPurchase {
            /** @var Event $event */
            $event = $originals->first()->event()->firstOrFail();

            $reversedDelta = 0;
            foreach ($originals as $original) {
                $original->status = GuestPackPurchase::STATUS_REVERSED;
                $original->save();
                $reversedDelta += (int) $original->participants_delta;
            }

            $row = $this->record(
                $context,
                $type,
                GuestPackPurchase::STATUS_REVERSAL,
                $event->id,
                // Negative for audit symmetry; recompute ignores reversal rows.
                participantsDelta: $reversedDelta === 0 ? null : -$reversedDelta,
                grantsUnlimited: false,
            );

            $event->recomputeParticipantLimit();

            return $row;
        });
    }

    /**
     * Freeze the event's pre-pack capacity the first time it's touched, so
     * refunds have something to reverse back to. Null (unlimited) is captured
     * as-is; recompute treats a null base as "stays unlimited".
     */
    private function captureBaseIfFirst(Event $event): void
    {
        $alreadyTracked = $event->guestPackPurchases()
            ->whereIn('status', [
                GuestPackPurchase::STATUS_APPLIED,
                GuestPackPurchase::STATUS_REVERSED,
            ])
            ->exists();

        if (! $alreadyTracked) {
            $event->base_participant_limit = $event->participant_limit;
            $event->save();
        }
    }

    private function purchaseAlreadyProcessed(string $transactionId): bool
    {
        return GuestPackPurchase::where('transaction_id', $transactionId)
            ->where('type', GuestPackPurchase::TYPE_PURCHASE)
            ->whereIn('status', [
                GuestPackPurchase::STATUS_APPLIED,
                GuestPackPurchase::STATUS_REVERSED,
            ])
            ->exists();
    }

    /**
     * Pull the target event out of a webhook's subscriber attributes.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveEvent(array $event): ?Event
    {
        $attribute = config('guest_packs.event_attribute');
        $attributes = $event['subscriber_attributes'] ?? [];

        if (! is_array($attributes) || ! isset($attributes[$attribute]['value'])) {
            return null;
        }

        $eventId = $attributes[$attribute]['value'];

        if (! is_numeric($eventId)) {
            return null;
        }

        return Event::find((int) $eventId);
    }

    /**
     * Best-effort map of RevenueCat's app_user_id to a local user. The webhook
     * still credits the event even when this is null.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveUserId(array $event): ?int
    {
        $appUserId = $event['app_user_id'] ?? null;

        return is_numeric($appUserId) && User::whereKey((int) $appUserId)->exists()
            ? (int) $appUserId
            : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(
        array $context,
        string $type,
        string $status,
        ?int $eventId,
        ?int $participantsDelta = null,
        bool $grantsUnlimited = false,
    ): GuestPackPurchase {
        return GuestPackPurchase::create([
            'event_id' => $eventId,
            'user_id' => $context['user_id'],
            'rc_event_id' => $context['rc_event_id'],
            'transaction_id' => $context['transaction_id'],
            'type' => $type,
            'product_id' => $context['product_id'],
            'participants_delta' => $participantsDelta,
            'grants_unlimited' => $grantsUnlimited,
            'status' => $status,
            'source' => $context['source'],
            'raw' => $context['raw'],
        ]);
    }
}
