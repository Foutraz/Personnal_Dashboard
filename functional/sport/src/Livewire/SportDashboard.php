<?php

namespace Functional\Sport\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class SportDashboard extends Component
{
    /**
     * Render the sport command deck composing the sport sub-components.
     */
    #[Layout('layouts.app')]
    #[Title('Sport')]
    public function render(): View
    {
        return view('sport::sport');
    }
}
