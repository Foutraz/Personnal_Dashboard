<?php

namespace Technical\WebAuthentication\Livewire;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Technical\WebAuthentication\Services\DashboardSummaryCollector;

class Dashboard extends Component
{
    /**
     * Render the authenticated dashboard shell with collected module summaries.
     */
    #[Layout('layouts.app')]
    #[Title('Dashboard')]
    public function render(DashboardSummaryCollector $collector): View
    {
        /** @var Authenticatable $user */
        $user = auth('web')->user();

        return view('web-authentication::livewire.dashboard', [
            'summaries' => $collector->for($user),
        ]);
    }
}
