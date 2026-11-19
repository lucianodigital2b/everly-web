<?php

namespace Tests\Feature;

use Tests\TestCase;

class StoreLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('everly.invite.app_store_url', 'https://apps.apple.com/app/id6759682374');
    }

    public function test_it_sends_the_visitor_to_the_app_store(): void
    {
        config()->set('everly.invite.apple_provider_token', null);

        $this->get('/app?utm_source=instagram&utm_campaign=ig_bio')
            ->assertOk()
            ->assertSee('https://apps.apple.com/app/id6759682374', false)
            ->assertDontSee('pt=', false);
    }

    public function test_it_adds_apple_campaign_parameters_when_the_provider_token_is_set(): void
    {
        config()->set('everly.invite.apple_provider_token', '123456');

        $this->get('/app?utm_source=Instagram&utm_campaign=ig_bio')
            ->assertOk()
            ->assertSee('https://apps.apple.com/app/id6759682374?pt=123456&amp;ct=instagram_ig_bio&amp;mt=8', false);
    }

    public function test_the_campaign_falls_back_to_web(): void
    {
        config()->set('everly.invite.apple_provider_token', '123456');

        $this->get('/app')->assertOk()->assertSee('ct=web', false);
    }

    public function test_analytics_never_render_outside_production(): void
    {
        $this->get('/app')->assertOk()->assertDontSee('posthog.init', false);
        $this->get('/privacy')->assertOk()->assertDontSee('posthog.init', false);
    }
}
