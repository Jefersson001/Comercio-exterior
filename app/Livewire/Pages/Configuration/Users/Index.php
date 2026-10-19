<?php

namespace App\Livewire\Pages\Configuration\Users;

use App\Models\User;
use App\UserRole;
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

    #[Url(except: '')]
    public string $role = '';

    public function boot(): void
    {
        Gate::authorize('manage-users');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);

        return view('pages.configuration.users.index', [
            'users' => User::query()
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                }))
                ->when($this->role !== '', fn ($query) => $query->where('role', $this->role))
                ->orderBy('name')->orderBy('last_name')->orderBy('id')->paginate(15),
            'roles' => UserRole::cases(),
        ])->layout('layouts.dashboard', ['title' => 'Usuarios', 'heading' => 'Usuarios']);
    }
}
