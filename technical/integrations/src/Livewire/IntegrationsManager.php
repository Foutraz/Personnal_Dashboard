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

        session()->flash('integrations.status', __(':provider a été déconnecté.', ['provider' => $integrationProvider->label()]));
    }

    /**
     * Merge the provider catalogue with the authenticated user's connection state.
     *
     * @return array<int, array{provider: string, label: string, description: string, accent: string, icon: string, available: bool, connect_route: ?string, sync_route: ?string, connected: bool}>
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
                'connect_route' => $card['connect_route'],
                'sync_route' => $card['sync_route'],
                'connected' => in_array($card['provider']->value, $connectedProviders, true),
            ],
            $this->catalogue(),
        );
    }

    /**
     * Describe the external providers exposed on the integrations page.
     *
     * @return array<int, array{provider: IntegrationProvider, description: string, accent: string, icon: string, available: bool, connect_route: ?string, sync_route: ?string}>
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
                'sync_route' => 'sport.strava.sync',
            ],
            [
                'provider' => IntegrationProvider::GoogleCalendar,
                'description' => 'Agrégez vos événements Google Calendar dans le planning.',
                'accent' => 'violet',
                'icon' => 'M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z',
                'available' => false,
                'connect_route' => null,
                'sync_route' => null,
            ],
            [
                'provider' => IntegrationProvider::OutlookCalendar,
                'description' => 'Synchronisez votre agenda Outlook dans le planning.',
                'accent' => 'cyan',
                'icon' => 'M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z',
                'available' => false,
                'connect_route' => null,
                'sync_route' => null,
            ],
            [
                'provider' => IntegrationProvider::LibertyRider,
                'description' => 'Reliez Liberty Rider pour enrichir vos trajets moto.',
                'accent' => 'cyan',
                'icon' => 'M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3Z',
                'available' => false,
                'connect_route' => null,
                'sync_route' => null,
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
