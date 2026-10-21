<?php

namespace App\Livewire\Pages\History;

use App\Models\RecordChange;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Overview extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $search = '';

    public function boot(): void
    {
        Gate::authorize('manage-users');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('manage-users');
        $search = trim($this->search);

        return view('pages.history.index', [
            'entries' => RecordChange::query()->when($this->type !== '', fn ($query) => $query->where('subject_type', $this->type))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('subject_name', 'like', '%'.$search.'%')->orWhere('actor_name', 'like', '%'.$search.'%')))
                ->orderByDesc('id')->paginate(15),
        ])->layout('layouts.dashboard', ['title' => 'Historial de cambios', 'heading' => 'Historial de cambios']);
    }
}
