<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stand in for R2 so nothing leaves the machine. Storage::fake() builds
        // its driver purely from the config handed to it, so the public base URL
        // is passed here — that's what makes it resolve absolute URLs the way
        // the real bucket does.
        config()->set('everly.media.disk', 'r2');

        Storage::fake('r2', ['url' => 'https://media.everly.test']);
    }

    /**
     * Headers set with withHeader() persist onto later requests in the same
     * test, which would silently reuse one guest's token for the next guest —
     * so the guest token is passed per request instead.
     *
     * @return array<string, string>
     */
    private function asGuest(string $token): array
    {
        return ['X-Guest-Token' => $token];
    }

    private function activeEvent(array $attributes = [], array $planAttributes = []): Event
    {
        return Event::factory()
            ->for(User::factory())
            ->for(Plan::factory()->create($planAttributes))
            ->active()
            ->create($attributes);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('party.jpg', 900, 675);
    }

    public function test_the_upload_token_endpoint_reports_the_event_as_open(): void
    {
        $event = $this->activeEvent(['name' => 'Sarah & James', 'title' => null]);

        $this->getJson("/api/upload/{$event->qr_code_token}")
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('event.name', 'Sarah & James')
            // Title falls back to the name.
            ->assertJsonPath('event.title', 'Sarah & James')
            ->assertJsonPath('event.canUpload', true);
    }

    public function test_an_unknown_qr_token_is_a_404(): void
    {
        $this->getJson('/api/upload/not-a-real-token')->assertNotFound();
    }

    public function test_a_pending_payment_event_does_not_accept_uploads(): void
    {
        $event = Event::factory()
            ->for(User::factory())
            ->for(Plan::factory())
            ->create(['status' => Event::STATUS_PENDING_PAYMENT]);

        $this->getJson("/api/upload/{$event->qr_code_token}")
            ->assertOk()
            ->assertJsonPath('active', false);

        $this->postJson("/api/upload/{$event->qr_code_token}", ['photo' => $this->photo()])
            ->assertStatus(422);

        $this->assertDatabaseCount('event_photos', 0);
    }

    public function test_the_first_upload_issues_a_guest_token_and_stores_the_media(): void
    {
        $event = $this->activeEvent();

        $response = $this->postJson("/api/upload/{$event->qr_code_token}", [
            'photo' => $this->photo(),
        ]);

        $response->assertCreated()->assertJsonStructure(['guest_token']);

        $photo = $event->photos()->sole();

        $this->assertSame('image', $photo->media_type);
        $this->assertSame(900, $photo->width);
        $this->assertSame(675, $photo->height);

        // Original and generated thumbnail both land on the disk.
        Storage::disk('r2')->assertExists($photo->path);
        Storage::disk('r2')->assertExists($photo->thumbnail_path);

        // The client is handed absolute URLs off the bucket's public domain,
        // not the object keys we store.
        $this->assertSame('https://media.everly.test/'.$photo->path, $photo->url());
        $this->assertSame('https://media.everly.test/'.$photo->thumbnail_path, $photo->thumbnailUrl());
    }

    public function test_the_same_guest_token_is_reused_across_uploads(): void
    {
        $event = $this->activeEvent(['shot_limit' => 5]);

        $first = $this->postJson("/api/upload/{$event->qr_code_token}", ['photo' => $this->photo()]);
        $token = $first->json('guest_token');

        $this->postJson(
            "/api/upload/{$event->qr_code_token}",
            ['photo' => $this->photo()],
            $this->asGuest($token),
        )
            ->assertCreated()
            ->assertJsonPath('guest_token', $token);

        // One guest, two photos.
        $this->assertDatabaseCount('guests', 1);
        $this->assertSame(2, $event->photos()->count());
        $this->assertSame(2, Guest::whereGuestToken($token)->sole()->upload_count);
    }

    public function test_shot_limit_is_enforced_per_guest(): void
    {
        $event = $this->activeEvent(['shot_limit' => 1]);

        $token = $this->postJson("/api/upload/{$event->qr_code_token}", ['photo' => $this->photo()])
            ->json('guest_token');

        $this->postJson(
            "/api/upload/{$event->qr_code_token}",
            ['photo' => $this->photo()],
            $this->asGuest($token),
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');

        $this->assertSame(1, $event->photos()->count());

        // A *different* guest still has their own allowance.
        $this->postJson("/api/upload/{$event->qr_code_token}", ['photo' => $this->photo()])
            ->assertCreated();

        $this->assertSame(2, $event->photos()->count());
    }

    public function test_a_guest_token_from_another_event_does_not_carry_over(): void
    {
        $event = $this->activeEvent();
        $foreign = Guest::factory()->for($this->activeEvent())->create();

        $this->postJson(
            "/api/upload/{$event->qr_code_token}",
            ['photo' => $this->photo()],
            $this->asGuest($foreign->guest_token),
        )
            ->assertCreated()
            // A fresh guest is admitted for this event rather than reusing theirs.
            ->assertJsonPath('guest_token', fn (string $token): bool => $token !== $foreign->guest_token);
    }

    public function test_the_participant_limit_caps_the_number_of_guests(): void
    {
        $event = $this->activeEvent(['participant_limit' => 1]);

        $this->postJson("/api/upload/{$event->qr_code_token}", ['photo' => $this->photo()])
            ->assertCreated();

        // A second, distinct guest (no token) is turned away.
        $this->postJson("/api/upload/{$event->qr_code_token}", ['photo' => $this->photo()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('guests', 1);
    }

    public function test_the_plan_upload_cap_closes_the_event(): void
    {
        $event = $this->activeEvent([], ['max_uploads' => 1]);

        $this->postJson("/api/upload/{$event->qr_code_token}", ['photo' => $this->photo()])
            ->assertCreated();

        $this->postJson("/api/upload/{$event->qr_code_token}", ['photo' => $this->photo()])
            ->assertStatus(422);

        $this->assertSame(1, $event->photos()->count());
    }

    public function test_a_non_media_file_is_rejected(): void
    {
        $event = $this->activeEvent();

        $this->postJson("/api/upload/{$event->qr_code_token}", [
            'photo' => UploadedFile::fake()->create('payload.php', 8, 'text/x-php'),
        ])->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('event_photos', 0);
    }
}
