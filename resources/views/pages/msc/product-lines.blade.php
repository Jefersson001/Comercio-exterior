<section class="mt-6 rounded-2xl border border-slate-200 p-5 dark:border-white/10">
    <h3 class="mb-4 text-lg font-semibold">Productos del embarque</h3>
    @if($foreignTrade)
        <label for="category_id" class="mb-2 block text-sm font-medium">Categoría *</label>
        <select id="category_id" wire:model.live="pendingCategoryId" wire:loading.attr="disabled" class="mb-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900 dark:text-white">
            <option value="">Selecciona una categoría</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id')<p role="alert" class="mb-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
        @error('pendingCategoryId')<p role="alert" class="mb-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
        @if($confirmingCategoryChange)
            <div role="alert" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                <p>Al cambiar la categoría se retirarán los productos que no pertenezcan a ella y sus variantes. Los cambios se aplicarán al guardar el embarque.</p>
                <div class="mt-3 flex gap-4">
                    <button type="button" wire:click="applyCategoryChange" wire:loading.attr="disabled" class="font-semibold underline">Confirmar cambio</button>
                    <button type="button" wire:click="cancelCategoryChange" wire:loading.attr="disabled" class="font-semibold underline">Cancelar cambio</button>
                </div>
            </div>
        @endif
    @else
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Este embarque contiene varios productos. Administra sus líneas desde Comercio Exterior → MSC.</p>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead class="bg-slate-50 dark:bg-white/5"><tr>
                <th class="p-3" scope="col">Model / Producto *</th>
                <th class="p-3" scope="col">Cant. contenedores *</th>
                <th class="p-3" scope="col">Cant. unidades</th>
                @if($foreignTrade)<th class="p-3" scope="col">Acción</th>@endif
            </tr></thead>
            <tbody>
                @forelse($productLines as $index => $line)
                    @php($lineProduct = $lineProducts->get($line['product_id'] ?? null))
                    <tr wire:key="shipment-product-{{ $index }}" class="border-b border-slate-200 dark:border-white/10">
                        <td class="p-3">
                            @if($foreignTrade)
                                <select aria-label="Producto línea {{ $index + 1 }}" wire:model.live="productLines.{{ $index }}.product_id" @disabled($category_id === null || $confirmingCategoryChange) class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                                    <option value="">Selecciona un producto</option>
                                    @if(isset($line['product_id']) && $line['product_id'] && ! $products->contains('id', $line['product_id']))
                                        <option value="{{ $line['product_id'] }}">{{ $lineProducts->get($line['product_id'])?->name }} (revisa su categoría)</option>
                                    @endif
                                    @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                                </select>
                                @error("productLines.$index.product_id")<p role="alert" class="mt-2 text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                            @else
                                {{ $lineProducts->get($line['product_id'])?->name }}
                            @endif
                        </td>
                        <td class="p-3">
                            <input type="number" aria-label="Contenedores línea {{ $index + 1 }}" wire:model.live.debounce.350ms="productLines.{{ $index }}.container_qty" min="1" max="2147483647" step="1" @disabled(! $foreignTrade || $confirmingCategoryChange) class="w-32 rounded-lg border border-slate-200 bg-transparent px-3 py-2 dark:border-white/10">
                            @error("productLines.$index.container_qty")<p role="alert" class="mt-2 text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                        </td>
                        <td class="p-3">
                            <input type="number" aria-label="Unidades línea {{ $index + 1 }}" wire:model.live.debounce.350ms="productLines.{{ $index }}.units_qty" min="1" max="2147483647" step="1" placeholder="Automático" @disabled(! $foreignTrade || $confirmingCategoryChange) class="w-32 rounded-lg border border-slate-200 bg-transparent px-3 py-2 dark:border-white/10">
                            @if(! $lineProduct)
                                <p class="mt-2 max-w-xs text-xs text-slate-500">Selecciona un producto para calcular las unidades.</p>
                            @elseif($lineProduct->units_per_container === null)
                                <p class="mt-2 max-w-xs text-xs text-amber-700 dark:text-amber-300">Este producto no tiene unidades por contenedor configuradas. Ingresa las unidades manualmente o configura su capacidad.</p>
                                @can('manage-products')
                                    <a href="{{ route('configuration.products.edit', $lineProduct) }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-semibold text-indigo-600 underline dark:text-indigo-300">Configurar producto</a>
                                @endcan
                            @else
                                <p class="mt-2 text-xs text-slate-500">{{ $lineProduct->units_per_container }} unidades por contenedor.</p>
                            @endif
                            @error("productLines.$index.units_qty")<p role="alert" class="mt-2 text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                        </td>
                        @if($foreignTrade)<td class="p-3"><button type="button" wire:click="removeProductLine({{ $index }})" wire:loading.attr="disabled" @disabled($confirmingCategoryChange) class="font-semibold text-rose-600 dark:text-rose-300">Eliminar</button></td>@endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $foreignTrade ? 4 : 3 }}" class="p-4 text-slate-500">{{ $category_id ? 'Agrega un producto de la categoría seleccionada.' : 'Selecciona primero una categoría.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @error('productLines')<p role="alert" class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
    @if($foreignTrade && $selectedCategory && ($selectedCategory->allows_multiple_products || count($productLines) === 0) && count($productLines) < 100)
        <button type="button" wire:click="addProductLine" wire:loading.attr="disabled" @disabled($confirmingCategoryChange) class="mt-4 text-sm font-semibold text-indigo-600 dark:text-indigo-300">Agregar línea de producto</button>
    @endif
    @if($foreignTrade && $category_id && $products->isEmpty())
        <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">Asigna productos a esta categoría desde Configuración → Productos.</p>
    @endif
    <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">Total contenedores: <strong>{{ $totalContainers }}</strong> · Total unidades: <strong>{{ $totalLineUnits ?? 'Por definir' }}</strong></p>
    <p class="mt-2 text-xs text-slate-500">Los contenedores calculan las unidades según la capacidad del producto. Al ingresar unidades manualmente se ajustan los contenedores necesarios. Vacía las unidades para volver al cálculo automático.</p>
</section>
