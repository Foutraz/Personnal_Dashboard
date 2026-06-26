<?php

namespace Functional\Planning\Jobs;

use Foutraz\GoogleCalendar\Dto\CalendarEvent;
use Functional\Planning\Actions\BuildUserGoogleCalendarManager;
use Functional\Planning\Actions\UpsertCalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Technical\Integrations\Models\IntegrationConnection;

class SyncGoogleEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $connectionId) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->connectionId)];
    }

    /**
     * Sync the connection's upcoming Google Calendar events into local storage.
     */
    public function handle(BuildUserGoogleCalendarManager $buildManager, UpsertCalendarEvent $upsert): void
    {
        $connection = IntegrationConnection::query()->find($this->connectionId);

        if ($connection === null) {
            return;
        }

        $windowDays = (int) config('planning.sync.window_days', 90);

        $events = $buildManager($connection)->events()->list('primary', [
            'timeMin' => Carbon::now()->toRfc3339String(),
            'timeMax' => Carbon::now()->addDays($windowDays)->toRfc3339String(),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
        ]);

        foreach ($events as $event) {
            /** @var CalendarEvent $event */
            $upsert->fromGoogle($connection, $event);
        }
    }
}
