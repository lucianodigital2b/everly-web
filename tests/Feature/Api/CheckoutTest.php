<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_opens_a_pix_charge_for_a_paid_event(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_cents' => 4900, 'currency' => 'brl']);
        $event = Event::factory()->for($user)->for($plan)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/checkout")
            ->assertOk()
            ->assertJsonPath('status', Payment::STATUS_PENDING)
            ->assertJsonStructure(['status', 'invoiceUrl']);

        $payment = $event->payments()->sole();

        $this->assertSame(4900, $payment->amount_cents);
        $this->assertSame('brl', $payment->currency);
        $this->assertNotEmpty($payment->invoice_url);
    }

    /**
     * The client can retry checkout (backgrounded app, flaky network); that must
     * not open a second charge for the same event.
     */
    public function test_checkout_is_idempotent_while_a_charge_is_open(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_cents' => 4900]);
        $event = Event::factory()->for($user)->for($plan)->create();

        $first = $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/checkout");
        $second = $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/checkout");

        $this->assertSame($first->json('invoiceUrl'), $second->json('invoiceUrl'));
        $this->assertSame(1, $event->payments()->count());
    }

    public function test_a_free_event_needs_no_checkout(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->free()->create();
        $event = Event::factory()->for($user)->for($plan)->active()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/checkout")
            ->assertOk()
            ->assertJsonPath('status', Payment::STATUS_PAID)
            ->assertJsonPath('invoiceUrl', null);

        $this->assertSame(0, $event->payments()->count());
    }

    public function test_payment_is_null_before_checkout(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->for(Plan::factory())->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/payment")
            ->assertOk()
            ->assertJsonPath('payment', null);
    }

    /**
     * The whole point of the poll: when the charge settles, the event goes live.
     */
    public function test_polling_a_settled_charge_activates_the_event(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_cents' => 4900, 'duration_days' => 7]);
        $event = Event::factory()->for($user)->for($plan)->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/checkout")->assertOk();

        // The fake gateway settles a charge once it's a few seconds old.
        $this->travelTo(Date::now()->addMinute());

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/payment")
            ->assertOk()
            ->assertJsonPath('payment.status', Payment::STATUS_PAID)
            ->assertJsonStructure(['payment' => ['status', 'amount_cents', 'currency', 'invoiceUrl', 'created_at']]);

        $event->refresh();

        $this->assertSame(Event::STATUS_ACTIVE, $event->status);
        $this->assertNotNull($event->activated_at);
        $this->assertNotNull($event->expires_at);
        // Lifetime comes from the plan.
        $this->assertSame(7, (int) $event->activated_at->diffInDays($event->expires_at, absolute: true));
    }

    public function test_another_users_checkout_is_not_reachable(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for(User::factory())->for(Plan::factory())->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/checkout")->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson("/api/events/{$event->id}/payment")->assertNotFound();
    }

    public function test_a_referral_code_can_be_redeemed_once(): void
    {
        $user = User::factory()->create();
        $referral = Referral::factory()->create([
            'code' => 'EVERLY10',
            'reward' => ['type' => 'free_event'],
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/referrals/redeem', ['code' => 'EVERLY10'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reward.type', 'free_event');

        $this->assertSame($user->id, $referral->fresh()->user_id);

        // Second redemption of the same code is refused.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/referrals/redeem', ['code' => 'EVERLY10'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_an_unknown_referral_code_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/referrals/redeem', ['code' => 'NOPE'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }
}
