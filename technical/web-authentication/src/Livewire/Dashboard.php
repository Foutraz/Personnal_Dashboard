<?php

namespace Technical\WebAuthentication\Livewire;

use Carbon\CarbonPeriod;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Technical\WebAuthentication\Services\AgendaCollector;
use Technical\WebAuthentication\Services\DashboardSummaryCollector;

class Dashboard extends Component
{
    /**
     * Render the authenticated dashboard shell with collected module summaries and agenda items.
     */
    #[Layout('layouts.app')]
    #[Title('Dashboard')]
    public function render(DashboardSummaryCollector $collector, AgendaCollector $agendaCollector): View
    {
        /** @var Authenticatable $user */
        $user = auth('web')->user();

        return view('web-authentication::livewire.dashboard', [
            'summaries' => $collector->for($user),
            'agenda' => $agendaCollector->for($user, CarbonPeriod::create(Carbon::now(), Carbon::now()->addDays(14))),
        ]);
    }
}
