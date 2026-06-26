<?php

namespace Functional\Planning\Http\Controllers;

use Foutraz\GoogleCalendar\GoogleCalendarManager;
use Foutraz\Outlook\OutlookManager;
use Functional\Planning\Actions\FindOrCreateCalendarConnection;
use Functional\Planning\Exceptions\CalendarCallbackDeniedException;
use Functional\Planning\Exceptions\CalendarNotConnectedException;
use Functional\Planning\Exceptions\UnsupportedCalendarProviderException;
use Functional\Planning\Jobs\SyncGoogleEventsJob;
use Functional\Planning\Jobs\SyncOutlookEventsJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class CalendarConnectionController
{
    /**
     * The default Google Calendar scopes requested at connection time.
     *
     * @var array<int, string>
     */
    private array $googleScopes = ['https://www.googleapis.com/auth/calendar.readonly', 'openid', 'email'];

    /**
     * The default Outlook scopes requested at connection time.
     *
     * @var array<int, string>
     */
    private array $outlookScopes = ['Calendars.Read', 'offline_access', 'User.Read'];

    public function __construct(
        private GoogleCalendarManager $google,
        private OutlookManager $outlook,
    ) {}

    /**
     * Redirect the user to the provider authorization page with a signed state.
     *
     * @throws UnsupportedCalendarProviderException
     */
    public function connect(Request $request, string $provider): SymfonyRedirectResponse
    {
        $resolved = $this->resolveProvider($provider);

        $request->session()->put('planning_state', $state = bin2hex(random_bytes(16)));

        $url = $resolved === IntegrationProvider::GoogleCalendar
            ? $this->google->auth()->authorizeUrl($this->googleScopes)
            : $this->outlook->auth()->authorizeUrl($this->outlookScopes);

        return redirect()->away($url.'&state='.$state);
    }

    /**
     * Handle a provider callback by storing the connection and dispatching the sync.
     *
     * @throws CalendarCallbackDeniedException
     * @throws UnsupportedCalendarProviderException
     */
    public function callback(Request $request, string $provider, FindOrCreateCalendarConnection $finder): RedirectResponse
    {
        $resolved = $this->resolveProvider($provider);

        if ($request->has('error') || ! $request->filled('code')) {
            throw new CalendarCallbackDeniedException;
        }

        $token = $resolved === IntegrationProvider::GoogleCalendar
            ? $this->google->auth()->exchangeToken((string) $request->query('code'))
            : $this->outlook->auth()->exchangeToken((string) $request->query('code'));

        $scopes = $resolved === IntegrationProvider::GoogleCalendar ? $this->googleScopes : $this->outlookScopes;

        $connection = $finder((string) Auth::id(), $resolved, [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires_at' => $token->expiresAt,
            'scope' => $token->scope,
        ], $scopes);

        $this->dispatchSync($resolved, $connection->id);

        return redirect()->route('planning');
    }

    /**
     * Trigger an events sync for the authenticated user's connection.
     *
     * @throws CalendarNotConnectedException
     * @throws UnsupportedCalendarProviderException
     */
    public function syncNow(string $provider): RedirectResponse
    {
        $resolved = $this->resolveProvider($provider);

        $connection = IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', $resolved)
            ->first();

        if ($connection === null) {
            throw new CalendarNotConnectedException;
        }

        $this->dispatchSync($resolved, $connection->id);

        return redirect()->route('planning');
    }

    /**
     * Dispatch the sync job matching the resolved provider.
     */
    private function dispatchSync(IntegrationProvider $provider, string $connectionId): void
    {
        if ($provider === IntegrationProvider::GoogleCalendar) {
            SyncGoogleEventsJob::dispatch($connectionId);

            return;
        }

        SyncOutlookEventsJob::dispatch($connectionId);
    }

    /**
     * Resolve the supported calendar provider from the route parameter.
     *
     * @throws UnsupportedCalendarProviderException
     */
    private function resolveProvider(string $provider): IntegrationProvider
    {
        return match ($provider) {
            'google' => IntegrationProvider::GoogleCalendar,
            'outlook' => IntegrationProvider::OutlookCalendar,
            default => throw new UnsupportedCalendarProviderException,
        };
    }
}
