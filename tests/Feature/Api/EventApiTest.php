<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Guest;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cover_uploaded_on_create_becomes_the_events_cover_url(): void
    {
        config()->set('everly.media.disk', 'r2');
        Storage::fake('r2', ['url' => 'https://media.everly.test']);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_cents' => 0]);

        $response = $this->actingAs($user, 'sanctum')->post('/api/events', [
            'name' => 'Sarah & James',
            'plan_id' => $plan->id,
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 2400, 1600),
        ]);

        $response->assertCreated();

        $url = $response->json('cover_image_url');
        $this->assertNotNull($url, 'the uploaded cover was dropped');
        $this->assertStringStartsWith('https://media.everly.test/', $url);

        Storage::disk('r2')->assertExists(
            str_replace('https://media.everly.test/', '', $url),
        );
    }

    public function test_a_cover_can_be_replaced_after_the_fact(): void
    {
        config()->set('everly.media.disk', 'r2');
        Storage::fake('r2', ['url' => 'https://media.everly.test']);

        $user = User::factory()->create();
        $event = Event::factory()->for($user)->for(Plan::factory())->create([
            'cover_image_url' => null,
        ]);

        // A multipart body can't ride on a real PATCH, so the client posts it
        // with _method=PATCH. Both spellings have to reach the same handler.
        $response = $this->actingAs($user, 'sanctum')->post("/api/events/{$event->id}", [
            '_method' => 'PATCH',
            'name' => 'Renamed',
            'cover_image' => UploadedFile::fake()->image('new-cover.jpg', 1200, 900),
        ]);

        $response->assertOk()->assertJsonPath('name', 'Renamed');

        $event->refresh();
        $this->assertStringStartsWith('https://media.everly.test/', (string) $event->cover_image_url);
    }

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
            // Defaults derived from the base plan.
            ->assertJsonPath('tier', 'Free')
            ->assertJsonPath('participant_limit', 5)
            // Booleans, never null — the client's types depend on it.
            ->assertJsonPath('public', false);

        $this->assertNotNull($response->json('activated_at'));
        $this->assertNotNull($response->json('expires_at'));
        $this->assertNotEmpty($response->json('qr_code_token'));
    }

    public function test_an_event_picked_on_a_paid_tier_still_goes_live_with_the_free_allowance(): void
    {
        $user = User::factory()->create();
        $free = Plan::factory()->free()->create();
        $paid = Plan::factory()->create(['price_cents' => 4900]);

        // The tier is bought afterwards as a guest pack, so neither a paid plan
        // nor an id the backend has never had (the app sends 5, 6 and 8) may
        // hold the event back or hand it extra guests.
        foreach ([$paid->id, 999] as $planId) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/events', ['name' => 'Gala', 'plan_id' => $planId])
                ->assertCreated()
                ->assertJsonPath('status', Event::STATUS_ACTIVE)
                ->assertJsonPath('plan_id', $free->id)
                ->assertJsonPath('participant_limit', 5);
        }
    }

    public function test_the_client_cannot_set_its_own_guest_allowance(): void
    {
        $user = User::factory()->create();
        Plan::factory()->free()->create();

        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['name' => 'Gala', 'plan_id' => 1, 'participant_limit' => 500])
            ->assertCreated()
            ->assertJsonPath('participant_limit', 5)
            ->json('id');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/events/{$id}", ['participant_limit' => null, 'plan_id' => 4])
            ->assertOk()
            ->assertJsonPath('participant_limit', 5);
    }

    public function test_creating_an_event_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['plan_id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
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

    public function test_the_host_can_remove_a_photo_and_its_files(): void
    {
        config()->set('everly.media.disk', 'r2');
        Storage::fake('r2');

        $user = User::factory()->create();
        $event = Event::factory()->for($user)->for(Plan::factory())->active()->create();
        $guest = Guest::factory()->for($event)->create();
        [$photo, $kept] = EventPhoto::factory()->count(2)->for($event)->for($guest)->create();
        Storage::disk('r2')->put($photo->path, 'x');
        Storage::disk('r2')->put($photo->thumbnail_path, 'x');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}/photos/{$photo->id}")
            ->assertNoContent();

        $this->assertModelMissing($photo);
        $this->assertModelExists($kept);
        Storage::disk('r2')->assertMissing([$photo->path, $photo->thumbnail_path]);
        $this->assertFalse($guest->fresh()->isBlocked());
    }

    public function test_blocking_a_guest_removes_all_their_photos_and_stops_their_uploads(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->for(Plan::factory())->active()->create();
        $guest = Guest::factory()->for($event)->create();
        $other = Guest::factory()->for($event)->create();
        $photos = EventPhoto::factory()->count(2)->for($event)->for($guest)->create();
        $othersPhoto = EventPhoto::factory()->for($event)->for($other)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}/photos/{$photos[0]->id}?block_guest=1")
            ->assertNoContent();

        $this->assertSame(0, $guest->photos()->count());
        $this->assertModelExists($othersPhoto);
        $this->assertTrue($guest->fresh()->isBlocked());

        $this->postJson("/api/upload/{$event->qr_code_token}", [
            'photo' => UploadedFile::fake()->image('again.jpg', 900, 675),
        ], ['X-Guest-Token' => $guest->guest_token])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');

        $this->getJson("/api/upload/{$event->qr_code_token}", ['X-Guest-Token' => $guest->guest_token])
            ->assertOk()
            ->assertJsonPath('event.canUpload', false);
    }

    public function test_only_the_host_can_remove_a_photo_and_only_from_their_event(): void
    {
        $host = User::factory()->create();
        $event = Event::factory()->for($host)->for(Plan::factory())->active()->create();
        $photo = EventPhoto::factory()->for($event)->create();
        $otherEvent = Event::factory()->for($host)->for(Plan::factory())->active()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/events/{$event->id}/photos/{$photo->id}")
            ->assertNotFound();

        $this->actingAs($host, 'sanctum')
            ->deleteJson("/api/events/{$otherEvent->id}/photos/{$photo->id}")
            ->assertNotFound();

        $this->assertModelExists($photo);
    }

    public function test_deleting_an_event_removes_its_files_from_the_media_disk(): void
    {
        config()->set('everly.media.disk', 'r2');
        Storage::fake('r2');

        $user = User::factory()->create();
        $event = Event::factory()->for($user)->for(Plan::factory())->create();
        $other = Event::factory()->for($user)->for(Plan::factory())->create();
        Storage::disk('r2')->put("events/{$event->id}/photo.jpg", 'x');
        Storage::disk('r2')->put("events/{$event->id}/covers/cover.jpg", 'x');
        Storage::disk('r2')->put("events/{$other->id}/photo.jpg", 'x');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}")
            ->assertNoContent();

        Storage::disk('r2')->assertMissing("events/{$event->id}/photo.jpg");
        Storage::disk('r2')->assertMissing("events/{$event->id}/covers/cover.jpg");
        Storage::disk('r2')->assertExists("events/{$other->id}/photo.jpg");
    }

    public function test_deleting_the_account_removes_every_hosted_events_files(): void
    {
        config()->set('everly.media.disk', 'r2');
        Storage::fake('r2');

        $user = User::factory()->create();
        $event = Event::factory()->for($user)->for(Plan::factory())->create();
        Storage::disk('r2')->put("events/{$event->id}/photo.jpg", 'x');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/user')
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        Storage::disk('r2')->assertMissing("events/{$event->id}/photo.jpg");
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
