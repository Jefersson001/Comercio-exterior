<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Configuración / Productos</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ $productId ? 'Editar producto' : 'Crear producto' }}</h2>
            <p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">{{ $productId ? 'Actualiza la información, unidades por contenedor y variantes del producto.' : 'Ingresa la información, unidades por contenedor y variantes del producto.' }}</p>
        </div>
        <a href="{{ route('configuration.products.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Volver a la lista</a>
    </div>
    <form wire:submit="save" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-8">
        <div class="mb-8 border-b border-slate-200 pb-6 dark:border-white/10"><p class="text-xs font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Información del producto</p><h3 class="mt-2 text-2xl font-semibold">{{ $productId ? $name : 'Nuevo producto' }}</h3></div>
        <fieldset wire:loading.attr="disabled" class="grid min-w-0 gap-5 sm:grid-cols-2"><legend class="sr-only">Datos del producto</legend><x-forms.input name="name" label="Nombre *" wire:model="name" maxlength="255" required autofocus /><x-forms.input name="units_per_container" label="Unidades por contenedor" type="number" wire:model="units_per_container" min="1" max="2147483647" step="1" hint="Déjalo vacío si todavía no está definido." /></fieldset>
        <section class="mt-8 border-t border-slate-200 pt-6 dark:border-white/10">
            <h3 class="mb-4 text-lg font-semibold">Variantes por color</h3>
            <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10">
                <div class="grid grid-cols-[minmax(0,1fr)_3rem] border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                    <span>Color</span>
                    <span class="sr-only">Acciones</span>
                </div>
                @forelse($variants as $index => $variant)
                    <div wire:key="product-variant-{{ $variant['id'] ?? 'new-'.$index }}" class="grid grid-cols-[minmax(0,1fr)_3rem] items-center border-b border-slate-200 px-4 py-1 last:border-b-0 dark:border-white/10">
                        <input type="hidden" wire:model="variants.{{ $index }}.id">
                        <div class="min-w-0">
                            <label for="variant-color-{{ $index }}" class="sr-only">Color {{ $index + 1 }}</label>
                            <input id="variant-color-{{ $index }}" type="text" wire:model="variants.{{ $index }}.color" maxlength="255" placeholder="Escribe el color..." class="w-full border-0 bg-transparent px-0 py-2.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:ring-0 dark:text-white dark:placeholder:text-slate-500">
                            @error("variants.$index.color")<p role="alert" class="pb-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                        </div>
                        <button type="button" wire:click="removeVariant({{ $index }})" wire:loading.attr="disabled" aria-label="Eliminar color {{ $index + 1 }}" title="Eliminar línea" class="inline-flex size-8 items-center justify-center rounded text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50 dark:hover:bg-rose-500/10 dark:hover:text-rose-300">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg>
                        </button>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No hay colores agregados.</p>
                @endforelse
                <div class="border-t border-slate-200 px-4 py-3 dark:border-white/10">
                    <button type="button" wire:click="addVariant" wire:loading.attr="disabled" class="text-sm font-medium text-indigo-600 transition hover:text-indigo-800 disabled:opacity-50 dark:text-indigo-300 dark:hover:text-indigo-200">Agregar una línea</button>
                </div>
            </div>
            @error('variants')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
        </section>
        <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-white/10">
            @if ($productId)
                <button type="button" wire:click="delete" wire:confirm="¿Deseas eliminar este producto? Esta acción no se puede deshacer." wire:loading.attr="disabled" class="mr-auto rounded-xl border border-rose-200 px-5 py-3 font-semibold text-rose-600 transition hover:bg-rose-50 disabled:opacity-60 dark:border-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/10">Eliminar producto</button>
            @endif
            <a href="{{ route('configuration.products.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400 disabled:opacity-60"><span wire:loading.remove wire:target="save">{{ $productId ? 'Guardar cambios' : 'Crear producto' }}</span><span wire:loading wire:target="save">Guardando...</span></button>
        </div>
    </form>
</section>
