<?php

namespace Tests\Feature\Integrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class ConnectionEncryptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_the_access_token_encrypted_at_rest(): void
    {
        $plainToken = 'plain-access-token-value';

        $connection = IntegrationConnection::factory()->create([
            'access_token' => $plainToken,
        ]);

        $rawValue = DB::table('integration_connections')
            ->where('id', $connection->id)
            ->value('access_token');

        $this->assertNotSame($plainToken, $rawValue);
    }

    #[Test]
    public function it_decrypts_the_access_token_through_the_model_accessor(): void
    {
        $plainToken = 'plain-access-token-value';

        $connection = IntegrationConnection::factory()->create([
            'access_token' => $plainToken,
        ]);

        $this->assertSame($plainToken, $connection->fresh()->access_token);
    }
}
