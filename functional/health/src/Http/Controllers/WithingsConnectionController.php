<?php

namespace Functional\Health\Http\Controllers;

use Foutraz\Withings\WithingsManager;
use Functional\Health\Actions\FindOrCreateWithingsConnection;
use Functional\Health\Exceptions\WithingsCallbackDeniedException;
use Functional\Health\Exceptions\WithingsNotConnectedException;
use Functional\Health\Jobs\SyncWithingsUserJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class WithingsConnectionController
{
    /**
     * The default Withings scopes requested at connection time.
     *
     * @var array<int, string>
     */
    private array $scopes = ['user.metrics'];

    public function __construct(private WithingsManager $manager) {}

    /**
     * Redirect the user to the Withings authorization page with a signed state.
     */
    public function connect(Request $request): SymfonyRedirectResponse
    {
        $request->session()->put('withings_state', $state = bin2hex(random_bytes(16)));

        return redirect()->away($this->manager->auth()->authorizeUrl($this->scopes, $state));
    }

    /**
     * Handle the Withings callback by storing the connection and dispatching the user sync.
     *
     * @throws WithingsCallbackDeniedException
     */
    public function callback(Request $request, FindOrCreateWithingsConnection $finder): RedirectResponse
    {
        if ($request->has('error') || ! $request->filled('code')) {
            throw new WithingsCallbackDeniedException;
        }

        $expectedState = $request->session()->pull('withings_state');

        if ($expectedState === null || ! hash_equals((string) $expectedState, (string) $request->query('state'))) {
            throw new WithingsCallbackDeniedException;
        }

        $token = $this->manager->auth()->exchangeToken((string) $request->query('code'));

        $connection = $finder((string) Auth::id(), $token, $this->scopes);

        SyncWithingsUserJob::dispatch($connection->id);

        return redirect()->route('health');
    }

    /**
     * Trigger a measurements sync for the authenticated user's connection.
     *
     * @throws WithingsNotConnectedException
     */
    public function syncNow(): RedirectResponse
    {
        $connection = IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', IntegrationProvider::Withings)
            ->first();

        if ($connection === null) {
            throw new WithingsNotConnectedException;
        }

        SyncWithingsUserJob::dispatch($connection->id);

        return redirect()->route('health');
    }
}
