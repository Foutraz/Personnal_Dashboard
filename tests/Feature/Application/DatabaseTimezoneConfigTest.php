<?php

namespace Tests\Feature\Application;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseTimezoneConfigTest extends TestCase
{
    #[Test]
    public function it_pins_the_mysql_session_timezone_to_utc_by_default(): void
    {
        $this->assertSame('+00:00', config('database.connections.mysql.timezone'));
    }

    #[Test]
    public function it_converts_timestamp_columns_in_the_application_timezone(): void
    {
        $applicationOffset = now((string) config('app.timezone'))->format('P');

        $this->assertSame($applicationOffset, config('database.connections.mysql.timezone'));
    }
}
