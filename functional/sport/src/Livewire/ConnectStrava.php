<?php

namespace Functional\Sport\Livewire;

use Functional\Sport\Models\SportActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class ConnectStrava extends Component
{
    /**
     * Resolve the authenticated user's Strava connection.
     */
    public function connection(): ?IntegrationConnection
    {
        return IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', IntegrationProvider::Strava)
            ->first();
    }

    /**
     * Determine the timestamp of the most recent synced activity.
     */
    public function lastSyncedAt(IntegrationConnection $connection): ?Carbon
    {
        return SportActivity::query()
            ->where('integration_connection_id', $connection->id)
            ->latest('updated_at')
            ->value('updated_at');
    }

    /**
     * Render the connection card showing the connect or connected state.
     */
    public function render(): View
    {
        $connection = $this->connection();

        return view('sport::livewire.connect-strava', [
            'connection' => $connection,
            'lastSyncedAt' => $connection !== null ? $this->lastSyncedAt($connection) : null,
        ]);
    }
}
