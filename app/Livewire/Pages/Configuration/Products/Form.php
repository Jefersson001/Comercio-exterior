<?php

namespace App\Livewire\Pages\Configuration\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

    /** @var array<int, array{id?: int|string|null, color: string}> */
    public array $variants = [];

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
            $this->variants = $product->variants
                ->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'color' => $variant->color,
                ])
                ->values()
                ->all();
        }
    }

    public function addVariant(): void
    {
        $this->variants[] = ['id' => null, 'color' => ''];
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function save(): void
    {
        Gate::authorize('manage-products');
        $this->name = trim($this->name);
        $this->units_per_container = trim($this->units_per_container);
        $existingVariants = $this->productId === null
            ? collect()
            : Product::findOrFail($this->productId)->variants()->get()->keyBy('id');

        foreach ($this->variants as $index => $variant) {
            if (isset($variant['color']) && is_string($variant['color'])) {
                $this->variants[$index]['color'] = trim($variant['color']);
            }

            $variantId = $variant['id'] ?? null;
            if ($variantId !== null && $variantId !== '' && ! $existingVariants->has((int) $variantId)) {
                $this->addError("variants.$index.id", 'La variante seleccionada no pertenece a este producto.');

                return;
            }
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique(Product::class)->ignore($this->productId)],
            'units_per_container' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'variants' => ['array'],
            'variants.*' => ['array:id,color'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.color' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
        ];

        $data = $this->validate($rules, [
            'name.required' => 'Ingresa el nombre del producto.',
            'name.unique' => 'Ya existe un producto con este nombre.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'variants.*.color.required' => 'Ingresa el color de la variante.',
            'variants.*.color.distinct' => 'No repitas el color en este producto.',
        ]);

        $product = DB::transaction(function () use ($data): Product {
            $product = $this->productId ? Product::findOrFail($this->productId) : new Product;
            $product->name = $data['name'];
            $product->units_per_container = in_array($data['units_per_container'], [null, ''], true)
                ? null
                : (int) $data['units_per_container'];
            $product->save();

            $savedVariantIds = [];
            foreach ($data['variants'] as $variantData) {
                $variantId = $variantData['id'] ?? null;
                $variant = $variantId === null
                    ? new ProductVariant
                    : $product->variants()->findOrFail($variantId);

                $variant->color = $variantData['color'];
                $product->variants()->save($variant);
                $savedVariantIds[] = $variant->id;
            }

            $variantsToRemove = $product->variants();
            if ($savedVariantIds !== []) {
                $variantsToRemove->whereNotIn('id', $savedVariantIds)->delete();
            } else {
                $variantsToRemove->delete();
            }

            return $product;
        });

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
