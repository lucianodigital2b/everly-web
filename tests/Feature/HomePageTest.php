<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_the_landing_page_is_public(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Plan the wedding.', false)
            ->assertSee('app-id=6759682374', false);
    }

    public function test_every_store_button_goes_through_the_tracked_link(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('apps.apple.com', $html);
        $this->assertStringContainsString('/app?utm_source=website&amp;utm_medium=landing&amp;utm_campaign=hero', $html);
    }

    public function test_the_adobe_fonts_kit_loads_only_when_configured(): void
    {
        config()->set('everly.web.adobe_fonts_kit', null);
        $this->get('/')->assertDontSee('use.typekit.net', false);

        config()->set('everly.web.adobe_fonts_kit', 'abc1234');
        $this->get('/')->assertSee('https://use.typekit.net/abc1234.css', false);
    }
}
