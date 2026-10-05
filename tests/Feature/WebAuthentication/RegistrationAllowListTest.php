<?php

namespace Tests\Feature\WebAuthentication;

use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Services\RegistrationAllowList;
use Tests\TestCase;

class RegistrationAllowListTest extends TestCase
{
    private const VARIABLE = 'REGISTRATION_ALLOWED_EMAILS';

    private ?string $originalVariable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalVariable = $_ENV[self::VARIABLE] ?? null;
    }

    protected function tearDown(): void
    {
        $this->defineVariable($this->originalVariable);

        parent::tearDown();
    }

    private function defineVariable(?string $variable): void
    {
        if ($variable === null) {
            unset($_ENV[self::VARIABLE], $_SERVER[self::VARIABLE]);
            putenv(self::VARIABLE);

            return;
        }

        $_ENV[self::VARIABLE] = $variable;
        $_SERVER[self::VARIABLE] = $variable;
        putenv(self::VARIABLE.'='.$variable);
    }

    /**
     * @return array{registration: array{allowed_emails: mixed}}
     */
    private function declaredConfiguration(): array
    {
        return require base_path('technical/web-authentication/config/web-authentication.php');
    }

    private function allowList(?string $configured): RegistrationAllowList
    {
        config(['web-authentication.registration.allowed_emails' => $configured]);

        return app(RegistrationAllowList::class);
    }

    #[Test]
    public function it_keeps_registration_closed_when_the_variable_is_absent(): void
    {
        $this->defineVariable(null);

        $this->assertSame('', $this->declaredConfiguration()['registration']['allowed_emails']);
    }

    #[Test]
    public function it_reads_the_allow_list_from_the_registration_allowed_emails_variable(): void
    {
        $this->defineVariable('owner@example.com,friend@example.com');

        $this->assertSame('owner@example.com,friend@example.com', $this->declaredConfiguration()['registration']['allowed_emails']);
    }

    #[Test]
    public function it_loads_the_declared_configuration_into_the_application(): void
    {
        $this->assertSame(
            $this->declaredConfiguration()['registration'],
            config('web-authentication.registration'),
        );
    }

    #[Test]
    public function it_permits_a_listed_email(): void
    {
        $allowList = $this->allowList('owner@example.com,friend@example.com');

        $this->assertTrue($allowList->permits('friend@example.com'));
    }

    #[Test]
    public function it_refuses_an_unlisted_email(): void
    {
        $allowList = $this->allowList('owner@example.com');

        $this->assertFalse($allowList->permits('stranger@example.com'));
    }

    #[Test]
    public function it_ignores_the_case_of_both_the_list_and_the_email(): void
    {
        $allowList = $this->allowList('Owner@Example.com');

        $this->assertTrue($allowList->permits('OWNER@example.COM'));
    }

    #[Test]
    public function it_trims_the_entries_of_the_list_and_the_email(): void
    {
        $allowList = $this->allowList('  other@example.com ,  owner@example.com  ');

        $this->assertTrue($allowList->permits(' owner@example.com '));
    }

    #[Test]
    public function it_does_not_treat_a_substring_of_a_listed_email_as_listed(): void
    {
        $allowList = $this->allowList('owner@example.com');

        $this->assertFalse($allowList->permits('owner@example.co'));
        $this->assertFalse($allowList->permits('wowner@example.com'));
    }

    #[Test]
    public function it_refuses_every_email_when_the_list_is_empty(): void
    {
        $allowList = $this->allowList('');

        $this->assertFalse($allowList->permits('owner@example.com'));
        $this->assertFalse($allowList->permits(''));
    }

    #[Test]
    public function it_refuses_every_email_when_the_list_only_holds_separators(): void
    {
        $allowList = $this->allowList(' , ,');

        $this->assertFalse($allowList->permits(''));
        $this->assertFalse($allowList->permits('owner@example.com'));
    }

    #[Test]
    public function it_refuses_every_email_when_the_list_is_not_configured(): void
    {
        $allowList = $this->allowList(null);

        $this->assertFalse($allowList->permits('owner@example.com'));
    }
}
