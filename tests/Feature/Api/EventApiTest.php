<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_index_returns_a_bare_array_not_a_data_envelope(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->for(Plan::factory())->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/events');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonMissingPath('data');

        // The nested plan is part of the contract.
        $response->assertJsonStructure([['id', 'qr_code_token', 'status', 'plan' => ['id', 'name']]]);
    }

    public function test_index_only_returns_the_callers_own_events(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Event::factory()->for($other)->for(Plan::factory())->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_a_free_plan_event_is_activated_on_create(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->free()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/events', [
            'name' => 'Sarah & James',
            'plan_id' => $plan->id,
            'reveal_time' => 'Instant',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', Event::STATUS_ACTIVE)
            ->assertJsonPath('is_revealed', true)
            // Defaults derived from the plan.
            ->assertJsonPath('tier', 'Free')
            ->assertJsonPath('participant_limit', 5)
            // Booleans, never null — the client's types depend on it.
            ->assertJsonPath('public', false);

        $this->assertNotNull($response->json('activated_at'));
        $this->assertNotNull($response->json('expires_at'));
        $this->assertNotEmpty($response->json('qr_code_token'));
    }

    public function test_a_paid_plan_event_is_created_pending_payment(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_cents' => 4900]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['name' => 'Gala', 'plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('status', Event::STATUS_PENDING_PAYMENT)
            ->assertJsonPath('activated_at', null);
    }

    public function test_creating_an_event_requires_a_name_and_a_real_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['plan_id' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'plan_id']);
    }

    public function test_a_scheduled_reveal_requires_a_reveal_at(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->free()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', [
                'name' => 'Gala',
                'plan_id' => $plan->id,
                'reveal_time' => 'Scheduled',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reveal_at');
    }

    public function test_show_returns_the_event_detail_envelope(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['allow_download' => true, 'allow_slideshow' => false, 'white_label' => false]);
        $event = Event::factory()->for($user)->for($plan)->active()->create();

        EventPhoto::factory()->count(3)->for($event)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonStructure([
                'event' => ['id', 'plan'],
                'gates' => ['canUpload', 'canDownload', 'canSlideshow', 'whiteLabel'],
                'uploadCount',
                'latestPayment',
            ])
            ->assertJsonPath('uploadCount', 3)
            ->assertJsonPath('latestPayment', null)
            // Gates mirror the plan flags.
            ->assertJsonPath('gates.canDownload', true)
            ->assertJsonPath('gates.canSlideshow', false)
            ->assertJsonPath('gates.whiteLabel', false);
    }

    public function test_gates_close_uploads_once_the_plan_cap_is_reached(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['max_uploads' => 2]);
        $event = Event::factory()->for($user)->for($plan)->active()->create();

        EventPhoto::factory()->count(2)->for($event)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('gates.canUpload', false);
    }

    public function test_photos_are_withheld_until_the_gallery_is_revealed(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->free()->create();

        $event = Event::factory()->for($user)->for($plan)->active()->create([
            'reveal_time' => Event::REVEAL_END_OF_EVENT,
            'is_revealed' => false,
            'expires_at' => Date::now()->addDay(),
        ]);

        EventPhoto::factory()->count(2)->for($event)->create();

        // Sealed: the photos must not be on the wire at all.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/photos")
            ->assertOk()
            ->assertJsonCount(0, 'photos');
    }

    public function test_an_end_of_event_gallery_reveals_once_the_event_has_expired(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->free()->create();

        $event = Event::factory()->for($user)->for($plan)->expired()->create([
            'reveal_time' => Event::REVEAL_END_OF_EVENT,
            'is_revealed' => false,
        ]);

        EventPhoto::factory()->count(2)->for($event)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/photos")
            ->assertOk()
            ->assertJsonCount(2, 'photos')
            ->assertJsonStructure(['photos' => [[
                'id', 'url', 'thumbnail_url', 'media_type',
                'duration_seconds', 'width', 'height', 'created_at',
            ]]]);

        // The reveal is persisted, not just computed for the response.
        $this->assertTrue($event->fresh()->is_revealed);
    }

    public function test_a_scheduled_gallery_reveals_once_its_reveal_at_has_passed(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->free()->create();

        $event = Event::factory()->for($user)->for($plan)->active()->create([
            'reveal_time' => Event::REVEAL_SCHEDULED,
            'reveal_at' => Date::now()->subMinute(),
            'is_revealed' => false,
        ]);

        EventPhoto::factory()->for($event)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/photos")
            ->assertOk()
            ->assertJsonCount(1, 'photos');
    }

    public function test_an_event_can_be_updated(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->for(Plan::factory())->active()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/events/{$event->id}", [
                'title' => 'New Title',
                'public' => true,
                'filter' => 'mono',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'New Title')
            ->assertJsonPath('public', true)
            ->assertJsonPath('filter', 'mono');
    }

    public function test_an_event_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->for(Plan::factory())->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    /**
     * Another user's event must be indistinguishable from one that doesn't
     * exist — a 403 would confirm it's there.
     */
    public function test_another_users_event_is_not_reachable(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for(User::factory())->for(Plan::factory())->active()->create();

        $this->actingAs($user, 'sanctum')->getJson("/api/events/{$event->id}")->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson("/api/events/{$event->id}/photos")->assertNotFound();
        $this->actingAs($user, 'sanctum')->patchJson("/api/events/{$event->id}", ['title' => 'x'])->assertNotFound();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/events/{$event->id}")->assertNotFound();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_plans_are_listed_as_a_bare_array(): void
    {
        $user = User::factory()->create();
        Plan::factory()->count(2)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/plans')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([['id', 'name', 'price_cents', 'currency', 'allow_download', 'duration_days']]);
    }
}
