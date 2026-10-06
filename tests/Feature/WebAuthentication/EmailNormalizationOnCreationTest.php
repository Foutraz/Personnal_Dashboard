<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Actions\FindOrCreateSocialUser;
use Technical\WebAuthentication\Actions\RegisterUser;
use Technical\WebAuthentication\Exceptions\EmailAlreadyTakenException;
use Tests\Feature\WebAuthentication\Concerns\FakesGoogleSignIn;
use Tests\Feature\WebAuthentication\Concerns\OpensRegistration;
use Tests\TestCase;

class EmailNormalizationOnCreationTest extends TestCase
{
    use FakesGoogleSignIn, OpensRegistration, RefreshDatabase;

    #[Test]
    public function it_stores_a_registered_email_trimmed_and_in_lower_case(): void
    {
        app(RegisterUser::class)(['name' => 'Jane Doe', 'email' => ' Jane@Example.COM ', 'password' => 'password123']);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    #[Test]
    public function it_refuses_to_register_a_case_variant_of_a_taken_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->expectException(EmailAlreadyTakenException::class);

        app(RegisterUser::class)(['name' => 'John Doe', 'email' => 'Taken@Example.COM', 'password' => 'password123']);
    }

    #[Test]
    public function it_stores_the_email_of_a_new_google_user_trimmed_and_in_lower_case(): void
    {
        $this->allowRegistrationFor('owner@example.com');

        app(FindOrCreateSocialUser::class)($this->googleUser('google-owner', ' Owner@Example.COM '));

        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'google_id' => 'google-owner']);
    }
}
