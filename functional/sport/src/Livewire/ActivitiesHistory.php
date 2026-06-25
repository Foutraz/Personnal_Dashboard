<?php

namespace Functional\Sport\Livewire;

use Functional\Sport\Enums\SportType;
use Functional\Sport\Models\SportActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ActivitiesHistory extends Component
{
    use WithPagination;

    /**
     * The active sport type filter value.
     */
    public string $sportType = '';

    /**
     * Reset pagination when the filter changes.
     */
    public function updatedSportType(): void
    {
        $this->resetPage();
    }

    /**
     * Paginate the authenticated user's activities applying the active filter.
     *
     * @return LengthAwarePaginator<int, SportActivity>
     */
    public function activities(): LengthAwarePaginator
    {
        return SportActivity::query()
            ->where('user_id', Auth::id())
            ->when($this->sportType !== '', fn ($query) => $query->where('sport_type', $this->sportType))
            ->latest('started_at')
            ->paginate(10);
    }

    /**
     * Render the paginated activities history with its sport type filter.
     */
    public function render(): View
    {
        return view('sport::livewire.activities-history', [
            'activities' => $this->activities(),
            'sportTypes' => SportType::cases(),
        ]);
    }
}
