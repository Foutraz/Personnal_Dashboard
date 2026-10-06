<?php

namespace Tests\Feature\WebAuthentication;

use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Services\RegistrationAllowList;
use Tests\Feature\WebAuthentication\Concerns\OpensRegistration;
use Tests\TestCase;

class LoginRegistrationLinkTest extends TestCase
{
    use OpensRegistration;

    private function registerLink(): string
    {
        return 'href="'.route('register').'"';
    }

    #[Test]
    public function it_links_to_the_registration_when_the_allow_list_has_an_entry(): void
    {
        $this->allowRegistrationFor('owner@example.com');

        $this->get('/login')->assertOk()->assertSee($this->registerLink(), false);
    }

    #[Test]
    public function it_hides_the_registration_link_when_the_allow_list_is_empty(): void
    {
        $this->allowRegistrationFor();

        $this->get('/login')->assertOk()->assertDontSee($this->registerLink(), false);
    }

    #[Test]
    public function it_hides_the_registration_link_when_the_allow_list_is_not_configured(): void
    {
        config(['web-authentication.registration.allowed_emails' => null]);

        $this->get('/login')->assertOk()->assertDontSee($this->registerLink(), false);
    }

    #[Test]
    public function it_hides_the_registration_link_when_the_allow_list_only_holds_separators(): void
    {
        config(['web-authentication.registration.allowed_emails' => ' , ,']);

        $this->get('/login')->assertOk()->assertDontSee($this->registerLink(), false);
    }

    #[Test]
    public function it_keeps_the_google_sign_in_available_when_registration_is_closed(): void
    {
        $this->allowRegistrationFor();

        $this->get('/login')->assertSee('href="'.route('auth.google.redirect').'"', false);
    }

    #[Test]
    public function it_tells_whether_registration_is_open_from_the_allow_list(): void
    {
        $this->allowRegistrationFor('owner@example.com');
        $open = app(RegistrationAllowList::class)->isOpen();
        $this->allowRegistrationFor();
        $closed = app(RegistrationAllowList::class)->isOpen();

        $this->assertTrue($open);
        $this->assertFalse($closed);
    }
}
