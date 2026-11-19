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

    public function test_the_portuguese_page_uses_its_own_copy_screens_and_links(): void
    {
        $html = $this->get('/pt')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="pt-BR">', $html);
        $this->assertStringContainsString('Organize o casamento.', $html);
        $this->assertStringNotContainsString('Plan the wedding.', $html);
        $this->assertStringContainsString('/images/landing/pt/hero-hand.webp', $html);
        $this->assertStringContainsString('/app?utm_source=website_pt&amp;utm_medium=landing&amp;utm_campaign=hero', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/pt').'">', $html);
    }

    public function test_the_english_page_links_to_portuguese_and_stays_english(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('hreflang="pt-BR" href="'.url('/pt').'"', $html);
        $this->assertStringContainsString('/images/landing/hero-hand.webp', $html);
        $this->assertStringNotContainsString('/images/landing/pt/', $html);
    }

    public function test_every_string_in_the_template_has_a_portuguese_translation(): void
    {
        $template = file_get_contents(resource_path('views/home.blade.php'));
        $pt = json_decode(file_get_contents(lang_path('pt_BR.json')), true);

        preg_match_all("/__\('((?:[^'\\\\]|\\\\.)*)'\)/", $template, $matches);
        $missing = array_diff(array_map('stripslashes', $matches[1]), array_keys($pt));

        $this->assertNotEmpty($matches[1]);
        $this->assertSame([], array_values($missing));
        $this->assertStringNotContainsString("\u{2014}", implode(' ', $pt));
    }
}
