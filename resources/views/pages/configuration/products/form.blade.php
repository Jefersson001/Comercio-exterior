<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Configuración / Productos</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ $productId ? 'Editar producto' : 'Crear producto' }}</h2>
            <p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">{{ $productId ? 'Actualiza el nombre y las unidades por contenedor.' : 'Ingresa el nombre y las unidades por contenedor del producto.' }}</p>
        </div>
        <a href="{{ route('configuration.products.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Volver a la lista</a>
    </div>
    <form wire:submit="save" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-8">
        <div class="mb-8 border-b border-slate-200 pb-6 dark:border-white/10"><p class="text-xs font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Información del producto</p><h3 class="mt-2 text-2xl font-semibold">{{ $productId ? $name : 'Nuevo producto' }}</h3></div>
        <fieldset wire:loading.attr="disabled" class="grid min-w-0 gap-5 sm:grid-cols-2"><legend class="sr-only">Datos del producto</legend><x-forms.input name="name" label="Nombre *" wire:model="name" maxlength="255" required autofocus /><x-forms.input name="units_per_container" label="Unidades por contenedor" type="number" wire:model="units_per_container" min="1" max="2147483647" step="1" hint="Déjalo vacío si todavía no está definido." /></fieldset>
        <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-white/10">
            @if ($productId)
                <button type="button" wire:click="delete" wire:confirm="¿Deseas eliminar este producto? Esta acción no se puede deshacer." wire:loading.attr="disabled" class="mr-auto rounded-xl border border-rose-200 px-5 py-3 font-semibold text-rose-600 transition hover:bg-rose-50 disabled:opacity-60 dark:border-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/10">Eliminar producto</button>
            @endif
            <a href="{{ route('configuration.products.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400 disabled:opacity-60"><span wire:loading.remove wire:target="save">{{ $productId ? 'Guardar cambios' : 'Crear producto' }}</span><span wire:loading wire:target="save">Guardando...</span></button>
        </div>
    </form>
</section>
