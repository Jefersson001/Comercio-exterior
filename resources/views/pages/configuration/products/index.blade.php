<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Configuración</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">Productos</h2>
            <p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">Consulta y administra los productos del sistema.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('configuration.products.template') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Descargar plantilla</a>
            <a href="{{ route('configuration.products.export', ['search' => $search]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Exportar productos</a>
            <a href="{{ route('configuration.products.import') }}" class="inline-flex items-center justify-center rounded-xl border border-indigo-200 px-5 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50 dark:border-indigo-400/20 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Importar productos</a>
        <a href="{{ route('configuration.products.create') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-indigo-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Crear producto
        </a>
        </div>
    </div>
    @if (session('status'))
        <div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">{{ session('status') }}</div>
    @endif
    <div data-list-panel class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
        <div class="border-b border-slate-200 p-5 dark:border-white/10">
            <label for="product-search" class="sr-only">Buscar productos</label>
            <input id="product-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por nombre..." class="w-full max-w-lg rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-950 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-950/60 dark:text-white">
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">La exportación incluye todos los productos que coincidan con la búsqueda.</p>
        </div>
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-950/40 dark:text-slate-400"><tr><th scope="col" class="px-6 py-4 font-semibold">Nombre</th><th scope="col" class="px-6 py-4 font-semibold">Categoría</th><th scope="col" class="px-6 py-4 font-semibold">Unidades por contenedor</th><th scope="col" class="px-6 py-4 font-semibold">Variantes</th><th scope="col" class="w-28 px-6 py-4 font-semibold">Acciones</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($products as $product)
                        <tr wire:key="product-{{ $product->id }}" data-clickable-row data-href="{{ route('configuration.products.edit', $product) }}" tabindex="0" aria-label="Editar {{ $product->name }}" class="cursor-pointer transition hover:bg-indigo-50/60 focus:bg-indigo-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 dark:hover:bg-indigo-500/5 dark:focus:bg-indigo-500/5">
                            <td class="px-6 py-4"><a href="{{ route('configuration.products.edit', $product) }}" class="font-semibold hover:text-indigo-600 hover:underline dark:hover:text-indigo-300">{{ $product->name }}</a></td>
                            <td class="px-6 py-4"><span class="inline-flex rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ $product->category?->name ?? 'Sin categoría' }}</span></td>
                            <td class="px-6 py-4">{{ $product->units_per_container ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if($product->category?->allows_multiple_products)
                                    <span class="text-slate-400">No aplica</span>
                                @else
                                @forelse($product->variants as $variant)
                                    <span class="mr-2 inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-300">
                                        {{ $variant->color }}
                                    </span>
                                @empty
                                    <span class="text-slate-400">Sin variantes</span>
                                @endforelse
                                @endif
                            </td>
                            <td class="px-6 py-4"><x-workspace.record-actions :href="route('configuration.products.edit', $product)" :label="$product->name" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-semibold">No se encontraron productos</p><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Prueba con otra búsqueda o crea un producto.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4 dark:border-white/10">
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">{{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}</p>
            {{ $products->links() }}
        </div>
    </div>
</section>
