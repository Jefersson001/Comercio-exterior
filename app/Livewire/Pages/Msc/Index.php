<?php

namespace App\Livewire\Pages\Msc;

use App\Concerns\InteractsWithMscContext;
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

    public function boot(): void
    {
        Gate::authorize('manage-msc');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(bool $foreignTrade = false): void
    {
        $this->initializeMscContext($foreignTrade);
    }

    public function render(): View
    {
        return view('pages.msc.index', [
            'shipments' => MscShipment::query()->with($this->foreignTrade ? ['product', 'category', 'factoryRecord', 'vessel'] : ['product', 'factoryRecord', 'vessel'])
                ->search($this->search, $this->foreignTrade)->orderByDesc('id')->paginate(20),
        ])->layout('layouts.dashboard', ['title' => 'MSC', 'heading' => 'MSC']);
    }
}
