<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_the_privacy_policy_is_public_and_names_the_contact(): void
    {
        config()->set('everly.support_email', 'help@everly.test');

        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('help@everly.test')
            ->assertSee('Delete Account');
    }

    public function test_the_support_page_is_public(): void
    {
        $this->get('/support')->assertOk()->assertSee('How can we');
    }
}
