<?php

namespace App\Livewire\Pages\Configuration\Products;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $productId = null;

    public string $name = '';

    public string $units_per_container = '';

    public function boot(): void
    {
        Gate::authorize('manage-products');
    }

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->productId = $product->id;
            $this->name = $product->name;
            $this->units_per_container = $product->units_per_container === null ? '' : (string) $product->units_per_container;
        }
    }

    public function save(): void
    {
        Gate::authorize('manage-products');
        $this->name = trim($this->name);
        $this->units_per_container = trim($this->units_per_container);
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Product::class)->ignore($this->productId)],
            'units_per_container' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ], [
            'name.required' => 'Ingresa el nombre del producto.',
            'name.unique' => 'Ya existe un producto con este nombre.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
        ]);

        $product = $this->productId ? Product::findOrFail($this->productId) : new Product;
        $product->name = $this->name;
        $product->units_per_container = $this->units_per_container === '' ? null : (int) $this->units_per_container;
        $product->save();

        session()->flash('status', $this->productId ? 'Producto actualizado correctamente.' : 'Producto creado correctamente.');
        $this->redirectRoute('configuration.products.index');
    }

    public function delete(): void
    {
        Gate::authorize('manage-products');
        abort_if($this->productId === null, 404);
        $product = Product::findOrFail($this->productId);
        if ($product->mscShipments()->exists()) {
            $this->addError('name', 'No se puede eliminar un producto utilizado en MSC.');

            return;
        }
        $product->delete();

        session()->flash('status', 'Producto eliminado correctamente.');
        $this->redirectRoute('configuration.products.index');
    }

    public function render(): View
    {
        $title = $this->productId ? 'Editar producto' : 'Crear producto';

        return view('pages.configuration.products.form')
            ->layout('layouts.dashboard', ['title' => $title, 'heading' => $title]);
    }
}
