<?php

namespace Tests\Unit\Planning;

use DateTimeImmutable;
use Foutraz\GoogleCalendar\Dto\CalendarEvent as GoogleEvent;
use Foutraz\Outlook\Dto\CalendarEvent as OutlookEvent;
use Functional\Planning\Actions\UpsertCalendarEvent;
use Functional\Planning\Models\CalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class UpsertCalendarEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_maps_a_timed_google_event_to_the_local_model(): void
    {
        $connection = IntegrationConnection::factory()->create(['provider' => IntegrationProvider::GoogleCalendar]);

        $event = new GoogleEvent(
            'g-1',
            'confirmed',
            'Sprint review',
            'Weekly review',
            'Room A',
            new DateTimeImmutable('2026-07-01T10:00:00+00:00'),
            new DateTimeImmutable('2026-07-01T11:00:00+00:00'),
            false,
            'https://calendar.google.com/event?id=g-1',
            null,
        );

        $model = (new UpsertCalendarEvent)->fromGoogle($connection, $event);

        $this->assertSame('Sprint review', $model->title);
        $this->assertSame('Room A', $model->location);
        $this->assertSame(IntegrationProvider::GoogleCalendar, $model->provider);
        $this->assertFalse($model->all_day);
        $this->assertSame('2026-07-01 11:00:00', $model->ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame($connection->user_id, $model->user_id);
    }

    #[Test]
    public function it_maps_an_all_day_outlook_event_to_the_local_model(): void
    {
        $connection = IntegrationConnection::factory()->create(['provider' => IntegrationProvider::OutlookCalendar]);

        $event = new OutlookEvent(
            'o-1',
            null,
            'Day off',
            null,
            new DateTimeImmutable('2026-07-02T00:00:00+00:00'),
            null,
            true,
            'https://outlook.office.com/event?id=o-1',
            'Jane',
            null,
        );

        $model = (new UpsertCalendarEvent)->fromOutlook($connection, $event);

        $this->assertSame('(Sans titre)', $model->title);
        $this->assertTrue($model->all_day);
        $this->assertSame(IntegrationProvider::OutlookCalendar, $model->provider);
        $this->assertNull($model->ends_at);
    }

    #[Test]
    public function it_stays_idempotent_on_repeated_upserts(): void
    {
        $connection = IntegrationConnection::factory()->create(['provider' => IntegrationProvider::GoogleCalendar]);

        $event = new GoogleEvent(
            'g-2',
            'confirmed',
            'Standup',
            null,
            null,
            new DateTimeImmutable('2026-07-03T09:00:00+00:00'),
            new DateTimeImmutable('2026-07-03T09:15:00+00:00'),
            false,
            null,
            null,
        );

        $upsert = new UpsertCalendarEvent;
        $upsert->fromGoogle($connection, $event);
        $upsert->fromGoogle($connection, $event);

        $this->assertSame(1, CalendarEvent::query()->count());
    }
}
