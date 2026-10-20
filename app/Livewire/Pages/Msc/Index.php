<?php

namespace App\Livewire\Pages\Msc;

use App\Concerns\InteractsWithMscContext;
use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Models\Category;
use App\Models\MscShipment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithMscContext;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $status_bl = '';

    #[Url(except: '')]
    public string $category_id = '';

    public function boot(): void
    {
        Gate::authorize('manage-msc');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'status_bl', 'category_id'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('status', 'status_bl', 'category_id');
        $this->resetPage();
    }

    /** @return array<string, string|bool> */
    public function exportParameters(): array
    {
        return ['search' => $this->search, 'foreign_trade' => $this->foreignTrade,
            ...($this->status !== '' ? ['status' => $this->status] : []),
            ...($this->foreignTrade ? array_filter([
                'status_bl' => $this->status_bl,
                'category_id' => $this->category_id,
            ], fn (string $value): bool => $value !== '') : []),
        ];
    }

    public function mount(bool $foreignTrade = false): void
    {
        $this->initializeMscContext($foreignTrade);
    }

    public function render(): View
    {
        return view('pages.msc.index', [
            'statuses' => MscShipmentStatus::options(),
            'billOfLadingStatuses' => MscBillOfLadingStatus::cases(),
            'categories' => $this->foreignTrade ? Category::query()->orderBy('name')->get() : collect(),
            'shipments' => MscShipment::query()->with($this->foreignTrade ? ['product', 'productLines.product', 'category', 'factoryRecord', 'vessel'] : ['product', 'productLines.product', 'factoryRecord', 'vessel'])
                ->filters($this->status, $this->foreignTrade ? $this->status_bl : '', $this->foreignTrade ? $this->category_id : '')
                ->search($this->search, $this->foreignTrade)->orderByDesc('id')->paginate(20),
        ])->layout('layouts.dashboard', ['title' => 'MSC', 'heading' => 'MSC']);
    }
}
