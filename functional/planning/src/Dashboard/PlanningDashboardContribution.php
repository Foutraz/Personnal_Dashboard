<?php

namespace Functional\Planning\Dashboard;

use Functional\Planning\Models\CalendarEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class PlanningDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z';

    /**
     * Summarise the user's upcoming events and calendar connection state.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $upcoming = CalendarEvent::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('starts_at', '>', Carbon::now())
            ->count();
        $connected = IntegrationConnection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('provider', [IntegrationProvider::GoogleCalendar, IntegrationProvider::OutlookCalendar])
            ->exists();

        return new DashboardSummary(
            key: 'planning',
            title: 'Planning',
            accent: 'violet',
            icon: self::ICON,
            href: route('planning'),
            order: 50,
            available: $connected,
            metricValue: (string) $upcoming,
            metricUnit: 'à venir',
            secondaryLines: [$upcoming.' événements planifiés'],
            callToAction: $connected ? null : 'Connecter un calendrier',
        );
    }

    /**
     * Expose the planning navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Planning', route: 'planning', icon: self::ICON, order: 50);
    }
}
