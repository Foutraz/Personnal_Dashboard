<?php

namespace Functional\Health\Dashboard;

use Functional\Health\Enums\MeasurementType;
use Functional\Health\Models\BodyMeasurement;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class HealthDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z';

    /**
     * Summarise the user's latest weight measurement and Withings connection state.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $connected = IntegrationConnection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('provider', IntegrationProvider::Withings)
            ->exists();

        $latestWeight = BodyMeasurement::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('type', MeasurementType::Weight)
            ->latest('measured_at')
            ->first();

        $measurementCount = BodyMeasurement::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->count();

        return new DashboardSummary(
            key: 'health',
            title: 'Santé',
            accent: 'violet',
            icon: self::ICON,
            href: route('health'),
            order: 90,
            available: $connected,
            metricValue: $latestWeight !== null ? number_format($latestWeight->value, 1, ',', ' ') : '—',
            metricUnit: 'kg',
            secondaryLines: [
                $measurementCount.' mesure'.($measurementCount !== 1 ? 's' : ''),
            ],
            callToAction: $connected ? null : 'Connecter Withings',
        );
    }

    /**
     * Expose the health navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Santé', route: 'health', icon: self::ICON, order: 90);
    }
}
