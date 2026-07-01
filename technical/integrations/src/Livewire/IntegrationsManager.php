<?php

namespace Technical\Integrations\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class IntegrationsManager extends Component
{
    /**
     * The transient confirmation message shown after a disconnect.
     */
    public string $status = '';

    /**
     * Disconnect the given provider by deleting the authenticated user's connection.
     */
    public function disconnect(string $provider): void
    {
        $integrationProvider = IntegrationProvider::tryFrom($provider);

        if ($integrationProvider === null) {
            return;
        }

        IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', $integrationProvider)
            ->get()
            ->each
            ->delete();

        $this->status = __(':provider a été déconnecté.', ['provider' => $integrationProvider->label()]);
    }

    /**
     * Merge the provider catalogue with the authenticated user's connection state.
     *
     * @return array<int, array{provider: string, label: string, description: string, accent: string, icon: string, available: bool, connect_url: ?string, sync_url: ?string, connected: bool}>
     */
    public function cards(): array
    {
        $connectedProviders = IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->get()
            ->map(fn (IntegrationConnection $connection): string => $connection->provider->value)
            ->all();

        return array_map(
            fn (array $card): array => [
                'provider' => $card['provider']->value,
                'label' => $card['provider']->label(),
                'description' => $card['description'],
                'accent' => $card['accent'],
                'icon' => $card['icon'],
                'available' => $card['available'],
                'connect_url' => $card['connect_route'] !== null ? route($card['connect_route'], $card['connect_params']) : null,
                'sync_url' => $card['sync_route'] !== null ? route($card['sync_route'], $card['sync_params']) : null,
                'connected' => in_array($card['provider']->value, $connectedProviders, true),
            ],
            $this->catalogue(),
        );
    }

    /**
     * Describe the external providers exposed on the integrations page.
     *
     * @return array<int, array{provider: IntegrationProvider, description: string, accent: string, icon: string, available: bool, connect_route: ?string, connect_params: array<string, string>, sync_route: ?string, sync_params: array<string, string>}>
     */
    private function catalogue(): array
    {
        return [
            [
                'provider' => IntegrationProvider::Strava,
                'description' => 'Importez automatiquement vos activités sportives et vos trajets.',
                'accent' => 'lime',
                'icon' => 'strava',
                'available' => true,
                'connect_route' => 'sport.strava.connect',
                'connect_params' => [],
                'sync_route' => 'sport.strava.sync',
                'sync_params' => [],
            ],
            [
                'provider' => IntegrationProvider::GoogleCalendar,
                'description' => 'Agrégez vos événements Google Calendar dans le planning.',
                'accent' => 'violet',
                'icon' => 'M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z',
                'available' => true,
                'connect_route' => 'planning.connect',
                'connect_params' => ['provider' => 'google'],
                'sync_route' => 'planning.sync',
                'sync_params' => ['provider' => 'google'],
            ],
            [
                'provider' => IntegrationProvider::OutlookCalendar,
                'description' => 'Synchronisez votre agenda Outlook dans le planning.',
                'accent' => 'cyan',
                'icon' => 'M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z',
                'available' => true,
                'connect_route' => 'planning.connect',
                'connect_params' => ['provider' => 'outlook'],
                'sync_route' => 'planning.sync',
                'sync_params' => ['provider' => 'outlook'],
            ],
            [
                'provider' => IntegrationProvider::Withings,
                'description' => 'Importez vos mesures corporelles et données de santé Withings.',
                'accent' => 'violet',
                'icon' => 'M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z',
                'available' => true,
                'connect_route' => 'health.withings.connect',
                'connect_params' => [],
                'sync_route' => 'health.withings.sync',
                'sync_params' => [],
            ],
            [
                'provider' => IntegrationProvider::GoCardless,
                'description' => 'Reliez vos comptes bancaires pour suivre vos soldes et opérations.',
                'accent' => 'cyan',
                'icon' => 'M3 6h18M3 10h18M5 6V4h14v2M5 20h14a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2z',
                'available' => true,
                'connect_route' => 'finance.gocardless.connect',
                'connect_params' => [],
                'sync_route' => 'finance.gocardless.sync',
                'sync_params' => [],
            ],
            [
                'provider' => IntegrationProvider::LibertyRider,
                'description' => 'Reliez Liberty Rider pour enrichir vos trajets moto.',
                'accent' => 'cyan',
                'icon' => 'M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3Z',
                'available' => false,
                'connect_route' => null,
                'connect_params' => [],
                'sync_route' => null,
                'sync_params' => [],
            ],
        ];
    }

    /**
     * Render the integrations management page.
     */
    #[Layout('layouts.app')]
    #[Title('Intégrations')]
    public function render(): View
    {
        return view('integrations::livewire.integrations-manager', [
            'cards' => $this->cards(),
            'googleAccount' => Auth::user(),
        ]);
    }
}
