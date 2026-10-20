<?php

namespace App\Livewire\Pages\Configuration\Products;

use App\Models\Product;
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
        Gate::authorize('manage-products');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);

        return view('pages.configuration.products.index', [
            'products' => Product::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                ->with('variants')
                ->orderBy('name')->orderBy('id')->paginate(15),
        ])->layout('layouts.dashboard', ['title' => 'Productos', 'heading' => 'Productos']);
    }
}
