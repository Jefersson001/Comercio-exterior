<?php

namespace App\Livewire\Pages\Configuration\Factories;

use App\Models\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function boot(): void
    {
        Gate::authorize('manage-factories');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);

        return view('pages.configuration.factories.index', [
            'factories' => Factory::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')->orderBy('id')->paginate(15),
        ])->layout('layouts.dashboard', ['title' => 'Fábricas', 'heading' => 'Fábricas']);
    }
}
