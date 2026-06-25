<?php

namespace Functional\Finance\Livewire;

use Functional\Finance\Actions\RecalculatePositionHoldings;
use Functional\Finance\Enums\AssetType;
use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Finance\Services\CapitalCalculator;
use Functional\Finance\Services\PerformanceCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class PortfolioOverview extends Component
{
    /**
     * The symbol of the position being created inline.
     */
    #[Validate('required|string|max:16')]
    public string $newSymbol = '';

    /**
     * The display name of the position being created inline.
     */
    #[Validate('required|string|max:255')]
    public string $newName = '';

    /**
     * The asset type of the position being created inline.
     */
    public string $newType = AssetType::Etf->value;

    /**
     * The current price of the position being created inline.
     */
    public ?string $newCurrentPrice = null;

    /**
     * The identifier of the position currently expanded for editing.
     */
    public ?string $editingPositionId = null;

    /**
     * The edited manual current price of the expanded position.
     */
    public ?string $editPrice = null;

    /**
     * The identifier of the position receiving a new transaction.
     */
    public ?string $transactionPositionId = null;

    /**
     * The type of the transaction being recorded.
     */
    public string $txType = TransactionType::Buy->value;

    /**
     * The quantity of the transaction being recorded.
     */
    public ?string $txQuantity = null;

    /**
     * The unit price of the transaction being recorded.
     */
    public ?string $txUnitPrice = null;

    /**
     * Create a position owned by the authenticated user from the inline form.
     */
    public function createPosition(): void
    {
        $this->validate([
            'newSymbol' => 'required|string|max:16',
            'newName' => 'required|string|max:255',
            'newType' => ['required', Rule::enum(AssetType::class)],
            'newCurrentPrice' => 'nullable|numeric|min:0',
        ]);

        Position::query()->create([
            'user_id' => Auth::id(),
            'asset_symbol' => strtoupper($this->newSymbol),
            'asset_name' => $this->newName,
            'asset_type' => $this->newType,
            'current_price' => $this->newCurrentPrice !== null && $this->newCurrentPrice !== '' ? $this->newCurrentPrice : null,
        ]);

        $this->reset('newSymbol', 'newName', 'newCurrentPrice');
        $this->newType = AssetType::Etf->value;

        $this->dispatch('portfolio-updated');
    }

    /**
     * Toggle the inline price editor of the given position.
     */
    public function startEditing(string $positionId): void
    {
        $position = $this->ownPosition($positionId);

        if ($position === null) {
            return;
        }

        $this->editingPositionId = $positionId;
        $this->editPrice = $position->current_price;
        $this->transactionPositionId = null;
    }

    /**
     * Persist the edited manual current price of the expanded position.
     */
    public function updatePrice(): void
    {
        $this->validate(['editPrice' => 'nullable|numeric|min:0']);

        $position = $this->ownPosition((string) $this->editingPositionId);

        if ($position === null) {
            return;
        }

        $position->update([
            'current_price' => $this->editPrice !== null && $this->editPrice !== '' ? $this->editPrice : null,
        ]);

        $this->editingPositionId = null;
        $this->dispatch('portfolio-updated');
    }

    /**
     * Toggle the inline transaction form of the given position.
     */
    public function startTransaction(string $positionId): void
    {
        $this->transactionPositionId = $this->transactionPositionId === $positionId ? null : $positionId;
        $this->editingPositionId = null;
        $this->reset('txQuantity', 'txUnitPrice');
        $this->txType = TransactionType::Buy->value;
    }

    /**
     * Record a transaction on the active position and recompute its holdings.
     */
    public function recordTransaction(RecalculatePositionHoldings $recalculate): void
    {
        $this->validate([
            'txType' => ['required', Rule::enum(TransactionType::class)],
            'txQuantity' => 'required|numeric|min:0.00000001',
            'txUnitPrice' => 'required|numeric|min:0',
        ]);

        $position = $this->ownPosition((string) $this->transactionPositionId);

        if ($position === null) {
            return;
        }

        InvestmentTransaction::query()->create([
            'position_id' => $position->id,
            'user_id' => $position->user_id,
            'type' => $this->txType,
            'quantity' => $this->txQuantity,
            'unit_price' => $this->txUnitPrice,
            'executed_at' => now(),
        ]);

        $recalculate->handle($position);

        $this->transactionPositionId = null;
        $this->reset('txQuantity', 'txUnitPrice');
        $this->dispatch('portfolio-updated');
    }

    /**
     * Delete the given position owned by the authenticated user.
     */
    public function deletePosition(string $positionId): void
    {
        $this->ownPosition($positionId)?->delete();

        $this->dispatch('portfolio-updated');
    }

    /**
     * Resolve a position ensuring it belongs to the authenticated user.
     */
    private function ownPosition(string $positionId): ?Position
    {
        return Position::query()
            ->where('user_id', Auth::id())
            ->whereKey($positionId)
            ->first();
    }

    /**
     * Load the authenticated user's positions.
     *
     * @return Collection<int, Position>
     */
    public function positions(): Collection
    {
        return Position::query()
            ->where('user_id', Auth::id())
            ->orderBy('asset_symbol')
            ->get();
    }

    /**
     * Render the portfolio totals and the position glass cards with inline CRUD.
     */
    public function render(PerformanceCalculator $performance, CapitalCalculator $capital): View
    {
        $positions = $this->positions();
        $global = $performance->globalPerformance($positions);
        $transactions = InvestmentTransaction::query()
            ->where('user_id', Auth::id())
            ->get();

        return view('finance::livewire.portfolio-overview', [
            'positions' => $positions,
            'global' => $global,
            'realizedGain' => $capital->realizedGain($transactions),
            'performance' => $performance,
            'assetTypes' => AssetType::cases(),
            'transactionTypes' => TransactionType::cases(),
        ]);
    }
}
