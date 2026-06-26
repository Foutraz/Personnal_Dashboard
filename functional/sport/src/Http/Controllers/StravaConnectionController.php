<?php

namespace Functional\Sport\Http\Controllers;

use Foutraz\Strava\StravaManager;
use Functional\Sport\Actions\FindOrCreateConnection;
use Functional\Sport\Exceptions\StravaCallbackDeniedException;
use Functional\Sport\Exceptions\StravaNotConnectedException;
use Functional\Sport\Jobs\SyncStravaActivitiesJob;
use Functional\Sport\Jobs\SyncStravaAthleteJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class StravaConnectionController
{
    /**
     * The default Strava scopes requested at connection time.
     *
     * @var array<int, string>
     */
    private array $scopes = ['read', 'activity:read_all'];

    public function __construct(private StravaManager $manager) {}

    /**
     * Redirect the user to the Strava authorization page with a signed state.
     */
    public function connect(Request $request): SymfonyRedirectResponse
    {
        $request->session()->put('strava_state', $state = bin2hex(random_bytes(16)));

        return redirect()->away($this->manager->auth()->authorizeUrl($this->scopes, 'force').'&state='.$state);
    }

    /**
     * Handle the Strava callback by storing the connection and dispatching the athlete sync.
     *
     * @throws StravaCallbackDeniedException
     */
    public function callback(Request $request, FindOrCreateConnection $finder): RedirectResponse
    {
        if ($request->has('error') || ! $request->filled('code')) {
            throw new StravaCallbackDeniedException;
        }

        $expectedState = $request->session()->pull('strava_state');

        if ($expectedState === null || ! hash_equals((string) $expectedState, (string) $request->query('state'))) {
            throw new StravaCallbackDeniedException;
        }

        $token = $this->manager->auth()->exchangeToken((string) $request->query('code'));

        $connection = $finder((string) Auth::id(), $token, $this->scopes);

        SyncStravaAthleteJob::dispatch($connection->id);

        return redirect()->route('sport');
    }

    /**
     * Trigger an activities sync for the authenticated user's connection.
     *
     * @throws StravaNotConnectedException
     */
    public function syncNow(): RedirectResponse
    {
        $connection = IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', IntegrationProvider::Strava)
            ->first();

        if ($connection === null) {
            throw new StravaNotConnectedException;
        }

        SyncStravaActivitiesJob::dispatch($connection->id);

        return redirect()->route('sport');
    }
}
