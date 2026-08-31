<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\GuestPackPurchase;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GuestPackTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rc-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.revenuecat.webhook_auth' => self::SECRET,
            'guest_packs.baseline_participants' => 5,
        ]);
    }

    // --- webhook: purchases credit participant_limit per the §2 map ---------

    public function test_a_purchase_webhook_adds_capacity_additively(): void
    {
        $event = $this->eventWithCapacity(5);

        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_intimate'))
            ->assertOk()
            ->assertJsonPath('status', GuestPackPurchase::STATUS_APPLIED);

        // base 5 + intimate pack 30.
        $this->assertSame(35, $event->fresh()->participant_limit);
        $this->assertSame(5, $event->fresh()->base_participant_limit);
    }

    public function test_multiple_packs_stack(): void
    {
        $event = $this->eventWithCapacity(5);

        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_intimate'))->assertOk();
        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_celebration'))->assertOk();

        // base 5 + 30 + 100.
        $this->assertSame(135, $event->fresh()->participant_limit);
    }

    public function test_an_unlimited_pack_removes_the_cap(): void
    {
        $event = $this->eventWithCapacity(5);

        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_grand'))->assertOk();

        $this->assertNull($event->fresh()->participant_limit);
    }

    // --- webhook: refunds reverse and hold the baseline floor ---------------

    public function test_a_refund_reverses_the_credit_but_never_below_baseline(): void
    {
        $event = $this->eventWithCapacity(5);
        $txn = 'txn-refund-1';

        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_intimate', $txn))->assertOk();
        $this->assertSame(35, $event->fresh()->participant_limit);

        $this->sendWebhook($this->refundPayload($event, 'everly_guest_pack_intimate', $txn))
            ->assertOk()
            ->assertJsonPath('status', GuestPackPurchase::STATUS_REVERSAL);

        // Reversed all the way back to the baseline, not below it.
        $this->assertSame(5, $event->fresh()->participant_limit);
        $this->assertSame(
            GuestPackPurchase::STATUS_REVERSED,
            GuestPackPurchase::where('transaction_id', $txn)
                ->where('type', GuestPackPurchase::TYPE_PURCHASE)->sole()->status,
        );
    }

    public function test_refunding_an_unlimited_pack_restores_finite_capacity(): void
    {
        $event = $this->eventWithCapacity(5);

        // Buy a finite pack, then an unlimited one.
        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_intimate', 'txn-a'))->assertOk();
        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_grand', 'txn-b'))->assertOk();
        $this->assertNull($event->fresh()->participant_limit);

        // Refund the unlimited one: falls back to base 5 + the finite pack 30.
        $this->sendWebhook($this->refundPayload($event, 'everly_guest_pack_grand', 'txn-b'))->assertOk();

        $this->assertSame(35, $event->fresh()->participant_limit);
    }

    // --- webhook: idempotency, unknown SKU, unmatched event -----------------

    public function test_a_replayed_webhook_credits_once(): void
    {
        $event = $this->eventWithCapacity(5);
        $payload = $this->purchasePayload($event, 'everly_guest_pack_intimate');

        $this->sendWebhook($payload)->assertOk();
        $this->sendWebhook($payload)->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertSame(35, $event->fresh()->participant_limit);
        $this->assertSame(1, GuestPackPurchase::where('status', GuestPackPurchase::STATUS_APPLIED)->count());
    }

    public function test_an_unknown_sku_is_recorded_but_credits_nothing(): void
    {
        $event = $this->eventWithCapacity(5);

        $this->sendWebhook($this->purchasePayload($event, 'not_a_real_pack'))
            ->assertOk()
            ->assertJsonPath('status', GuestPackPurchase::STATUS_IGNORED);

        $this->assertSame(5, $event->fresh()->participant_limit);
    }

    public function test_a_purchase_for_an_unknown_event_is_recorded_unmatched(): void
    {
        $payload = $this->purchasePayload($this->eventWithCapacity(5), 'everly_guest_pack_intimate');
        // Point the subscriber attribute at an event that doesn't exist.
        $payload['event']['subscriber_attributes']['everly_event_id']['value'] = '999999';

        $this->sendWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', GuestPackPurchase::STATUS_UNMATCHED);

        $this->assertDatabaseHas('guest_pack_purchases', [
            'status' => GuestPackPurchase::STATUS_UNMATCHED,
            'event_id' => null,
        ]);
    }

    // --- webhook: auth ------------------------------------------------------

    public function test_a_webhook_with_the_wrong_secret_is_rejected(): void
    {
        $event = $this->eventWithCapacity(5);

        $this->postJson('/api/webhooks/revenuecat', $this->purchasePayload($event, 'everly_guest_pack_intimate'), [
            'Authorization' => 'wrong',
        ])->assertUnauthorized();

        $this->assertSame(5, $event->fresh()->participant_limit);
        $this->assertSame(0, GuestPackPurchase::count());
    }

    public function test_a_webhook_is_rejected_when_no_secret_is_configured(): void
    {
        config(['services.revenuecat.webhook_auth' => null]);
        $event = $this->eventWithCapacity(5);

        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_intimate'))
            ->assertUnauthorized();

        $this->assertSame(0, GuestPackPurchase::count());
    }

    // --- sync endpoint ------------------------------------------------------

    public function test_sync_credits_instantly_when_enabled(): void
    {
        config(['guest_packs.sync_enabled' => true]);
        $user = User::factory()->create();
        $event = $this->eventWithCapacity(5, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guest-packs/sync", [
                'product_id' => 'everly_guest_pack_intimate',
                'transaction_id' => 'txn-sync-1',
            ])
            ->assertOk()
            ->assertJsonPath('status', GuestPackPurchase::STATUS_APPLIED)
            ->assertJsonPath('event.participant_limit', 35);
    }

    public function test_sync_and_webhook_for_the_same_transaction_credit_once(): void
    {
        config(['guest_packs.sync_enabled' => true]);
        $user = User::factory()->create();
        $event = $this->eventWithCapacity(5, $user);
        $txn = 'txn-shared';

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guest-packs/sync", [
                'product_id' => 'everly_guest_pack_intimate',
                'transaction_id' => $txn,
            ])->assertOk();

        // The async webhook lands afterwards for the same purchase.
        $this->sendWebhook($this->purchasePayload($event, 'everly_guest_pack_intimate', $txn))
            ->assertOk()
            ->assertJsonPath('status', GuestPackPurchase::STATUS_IGNORED);

        $this->assertSame(35, $event->fresh()->participant_limit);
        $this->assertSame(1, GuestPackPurchase::where('status', GuestPackPurchase::STATUS_APPLIED)->count());
    }

    public function test_sync_is_unavailable_when_disabled(): void
    {
        config(['guest_packs.sync_enabled' => false]);
        $user = User::factory()->create();
        $event = $this->eventWithCapacity(5, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guest-packs/sync", [
                'product_id' => 'everly_guest_pack_intimate',
                'transaction_id' => 'txn-x',
            ])->assertNotFound();
    }

    public function test_sync_is_owner_only(): void
    {
        config(['guest_packs.sync_enabled' => true]);
        $event = $this->eventWithCapacity(5, User::factory()->create());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/events/{$event->id}/guest-packs/sync", [
                'product_id' => 'everly_guest_pack_intimate',
                'transaction_id' => 'txn-x',
            ])->assertNotFound();
    }

    public function test_sync_rejects_an_unknown_sku(): void
    {
        config(['guest_packs.sync_enabled' => true]);
        $user = User::factory()->create();
        $event = $this->eventWithCapacity(5, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guest-packs/sync", [
                'product_id' => 'bogus',
                'transaction_id' => 'txn-x',
            ])->assertStatus(422)->assertJsonValidationErrors('product_id');
    }

    // --- helpers ------------------------------------------------------------

    private function eventWithCapacity(int $limit, ?User $user = null): Event
    {
        return Event::factory()
            ->for($user ?? User::factory())
            ->for(Plan::factory())
            ->active()
            ->create(['participant_limit' => $limit]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(array $payload): TestResponse
    {
        return $this->postJson('/api/webhooks/revenuecat', $payload, [
            'Authorization' => self::SECRET,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(Event $event, string $productId, ?string $txn = null): array
    {
        return $this->payload(GuestPackPurchase::TYPE_PURCHASE, $event, $productId, $txn);
    }

    /**
     * @return array<string, mixed>
     */
    private function refundPayload(Event $event, string $productId, string $txn): array
    {
        return $this->payload(GuestPackPurchase::TYPE_REFUND, $event, $productId, $txn);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $type, Event $event, string $productId, ?string $txn): array
    {
        return [
            'api_version' => '1.0',
            'event' => [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'app_user_id' => (string) $event->user_id,
                'product_id' => $productId,
                'transaction_id' => $txn ?? 'txn-'.Str::random(8),
                'subscriber_attributes' => [
                    'everly_event_id' => ['value' => (string) $event->id],
                ],
            ],
        ];
    }
}
