<?php

namespace Technical\WebAuthentication\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class Dashboard extends Component
{
    /**
     * Provide the module catalogue rendered as a responsive card grid.
     *
     * @return array<int, array{key: string, title: string, description: string, accent: string, icon: string, available: bool}>
     */
    public function modules(): array
    {
        return [
            ['key' => 'sport', 'title' => 'Sport', 'description' => 'Synchronisez Strava et suivez vos performances.', 'accent' => 'cyan', 'available' => false, 'icon' => 'M4 7h3l2-3h6l2 3h3M5 7v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7M9 13a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z'],
            ['key' => 'weather', 'title' => 'Météo & Moto', 'description' => 'Score « moto friendly » et créneaux favorables.', 'accent' => 'violet', 'available' => false, 'icon' => 'M3 15a4 4 0 0 0 4 4h9a4 4 0 0 0 0-8 6 6 0 0 0-11.7-1.8A4 4 0 0 0 3 15Z'],
            ['key' => 'finance', 'title' => 'Finance', 'description' => 'Portefeuille, performance et simulateur DCA.', 'accent' => 'lime', 'available' => false, 'icon' => 'M3 17l5-5 4 4 8-8M21 8v5h-5'],
            ['key' => 'deadlines', 'title' => 'Échéances', 'description' => 'Vos charges récurrentes et rappels automatiques.', 'accent' => 'cyan', 'available' => false, 'icon' => 'M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z'],
            ['key' => 'planning', 'title' => 'Planning', 'description' => 'Agrégez Outlook et Google Calendar.', 'accent' => 'violet', 'available' => false, 'icon' => 'M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['key' => 'todo', 'title' => 'To-Do', 'description' => 'Tâches, priorités et rappels intelligents.', 'accent' => 'lime', 'available' => false, 'icon' => 'M9 11l3 3 8-8M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
            ['key' => 'goals', 'title' => 'Objectifs', 'description' => 'Jauges de progression alimentées par vos modules.', 'accent' => 'cyan', 'available' => false, 'icon' => 'M12 12a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0 0a9 9 0 1 1 0 18 9 9 0 0 1 0-18Zm0 6v0M12 3v3'],
            ['key' => 'maps', 'title' => 'Cartes', 'description' => 'Heatmap de couverture et exploration de trajets.', 'accent' => 'violet', 'available' => false, 'icon' => 'M9 6 3 4v14l6 2 6-2 6 2V6l-6-2-6 2Zm0 0v14m6-12v14'],
        ];
    }

    /**
     * Render the authenticated dashboard shell.
     */
    #[Layout('layouts.app')]
    #[Title('Dashboard')]
    public function render(): View
    {
        return view('web-authentication::livewire.dashboard', [
            'modules' => $this->modules(),
        ]);
    }
}
