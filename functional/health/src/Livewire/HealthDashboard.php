<?php

namespace Functional\Health\Livewire;

use Functional\Health\Enums\MeasurementType;
use Functional\Health\Models\BodyMeasurement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class HealthDashboard extends Component
{
    /**
     * Resolve the authenticated user's Withings connection.
     */
    public function connection(): ?IntegrationConnection
    {
        return IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', IntegrationProvider::Withings)
            ->first();
    }

    /**
     * Resolve the latest weight measurement for the authenticated user.
     */
    public function latestWeight(): ?BodyMeasurement
    {
        return BodyMeasurement::query()
            ->where('user_id', Auth::id())
            ->where('type', MeasurementType::Weight)
            ->latest('measured_at')
            ->first();
    }

    /**
     * Determine the timestamp of the most recent synced measurement.
     */
    public function lastSyncedAt(IntegrationConnection $connection): ?Carbon
    {
        return BodyMeasurement::query()
            ->where('integration_connection_id', $connection->id)
            ->latest('updated_at')
            ->value('updated_at');
    }

    /**
     * Render the health command deck with connection state and latest measurements.
     */
    #[Layout('layouts.app')]
    #[Title('Santé')]
    public function render(): View
    {
        $connection = $this->connection();

        return view('health::health', [
            'connection' => $connection,
            'latestWeight' => $this->latestWeight(),
            'lastSyncedAt' => $connection !== null ? $this->lastSyncedAt($connection) : null,
        ]);
    }
}
