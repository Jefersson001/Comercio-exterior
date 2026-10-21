<?php

namespace App\Livewire\Pages\Configuration\Categories;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $categoryId = null;

    public string $name = '';

    public bool $allows_multiple_products = false;

    public function boot(): void
    {
        Gate::authorize('manage-categories');
    }

    public function mount(?Category $category = null): void
    {
        if ($category?->exists) {
            $this->categoryId = $category->id;
            $this->name = $category->name;
            $this->allows_multiple_products = $category->allows_multiple_products;
        }
    }

    public function save(): void
    {
        Gate::authorize('manage-categories');
        $this->name = trim($this->name);
        $this->validate([
            'allows_multiple_products' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:255', Rule::unique(Category::class)->ignore($this->categoryId)],
        ], [
            'name.required' => 'Ingresa el nombre de la categoría.',
            'name.unique' => 'Ya existe una categoría con este nombre.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
        ]);

        $category = $this->categoryId ? Category::findOrFail($this->categoryId) : new Category;
        $category->name = $this->name;
        $category->allows_multiple_products = $this->allows_multiple_products;
        $category->save();

        session()->flash('status', $this->categoryId ? 'Categoría actualizada correctamente.' : 'Categoría creada correctamente.');
        if ($this->categoryId === null) {
            $this->redirectRoute('configuration.categories.index');
        } else {
            $this->dispatch('record-saved');
        }
    }

    public function delete(): void
    {
        Gate::authorize('manage-categories');
        abort_if($this->categoryId === null, 404);
        $category = Category::findOrFail($this->categoryId);
        if ($category->mscShipments()->exists()) {
            $this->addError('name', 'No se puede eliminar una categoría utilizada en MSC.');

            return;
        }
        $category->delete();

        session()->flash('status', 'Categoría eliminada correctamente.');
        $this->redirectRoute('configuration.categories.index');
    }

    public function render(): View
    {
        $title = $this->categoryId ? 'Editar categoría' : 'Crear categoría';

        return view('pages.configuration.categories.form')
            ->layout('layouts.dashboard', ['title' => $title, 'heading' => $title]);
    }
}
