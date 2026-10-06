<?php

namespace Tests\Feature\WebAuthentication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Exceptions\SocialProviderDeniedException;
use Technical\WebAuthentication\Exceptions\SocialSignInRefusedException;
use Technical\WebAuthentication\Exceptions\SocialStateMismatchException;
use Tests\TestCase;

class SocialCallbackFailureTest extends TestCase
{
    use RefreshDatabase;

    private function assertSentBackToLoginWithTheGenericMessage(): void
    {
        $this->assertFalse(Auth::guard('web')->check());
        Log::shouldHaveReceived('info')->once();
        Log::shouldNotHaveReceived('error');
    }

    private function signInFailsWith(InvalidStateException $failure): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow($failure);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function it_sends_the_visitor_who_cancels_on_google_back_to_the_login_screen(): void
    {
        Log::spy();

        $response = $this->get('/auth/google/callback?error=access_denied');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('web-authentication::auth.social_link_refused')]);
        $this->assertSentBackToLoginWithTheGenericMessage();
    }

    #[Test]
    public function it_sends_the_visitor_with_an_invalid_state_back_to_the_login_screen(): void
    {
        Log::spy();
        $this->signInFailsWith(new InvalidStateException);

        $response = $this->get('/auth/google/callback?state=stale&code=abc');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('web-authentication::auth.social_link_refused')]);
        $this->assertSentBackToLoginWithTheGenericMessage();
    }

    #[Test]
    public function it_gives_the_denied_and_state_refusals_the_generic_social_sign_in_family(): void
    {
        $this->assertInstanceOf(SocialSignInRefusedException::class, new SocialProviderDeniedException);
        $this->assertInstanceOf(SocialSignInRefusedException::class, new SocialStateMismatchException(new InvalidStateException));
    }

    #[Test]
    public function it_keeps_the_socialite_state_failure_as_the_previous_exception(): void
    {
        $failure = new InvalidStateException;

        $this->assertSame($failure, (new SocialStateMismatchException($failure))->getPrevious());
    }
}
