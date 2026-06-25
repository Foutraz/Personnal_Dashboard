<?php

namespace Functional\Finance\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class FinanceDashboard extends Component
{
    /**
     * Render the full-page finance command deck composing the finance panels.
     */
    #[Layout('layouts.app')]
    #[Title('Finance')]
    public function render(): View
    {
        return view('finance::finance');
    }
}
