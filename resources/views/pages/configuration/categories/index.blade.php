<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Configuración</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">Categorías</h2>
            <p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">Consulta y administra los categorías del sistema.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('configuration.categories.template') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Descargar plantilla</a>
            <a href="{{ route('configuration.categories.export', ['search' => $search]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Exportar categorías</a>
            <a href="{{ route('configuration.categories.import') }}" class="inline-flex items-center justify-center rounded-xl border border-indigo-200 px-5 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50 dark:border-indigo-400/20 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Importar categorías</a>
        <a href="{{ route('configuration.categories.create') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-indigo-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Crear categoría
        </a>
        </div>
    </div>
    @if (session('status'))
        <div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">{{ session('status') }}</div>
    @endif
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
        <div class="border-b border-slate-200 p-5 dark:border-white/10">
            <label for="category-search" class="sr-only">Buscar categorías</label>
            <input id="category-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por nombre..." class="w-full max-w-lg rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-950 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-950/60 dark:text-white">
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">La exportación incluye todos los categorías que coincidan con la búsqueda.</p>
        </div>
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-950/40 dark:text-slate-400"><tr><th scope="col" class="px-6 py-4 font-semibold">Nombre</th><th scope="col" class="w-28 px-6 py-4 font-semibold">Acciones</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}" data-clickable-row data-href="{{ route('configuration.categories.edit', $category) }}" tabindex="0" aria-label="Editar {{ $category->name }}" class="cursor-pointer transition hover:bg-indigo-50/60 focus:bg-indigo-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 dark:hover:bg-indigo-500/5 dark:focus:bg-indigo-500/5">
                            <td class="px-6 py-4"><a href="{{ route('configuration.categories.edit', $category) }}" class="font-semibold hover:text-indigo-600 hover:underline dark:hover:text-indigo-300">{{ $category->name }}</a></td>
                            <td class="px-6 py-4"><a href="{{ route('configuration.categories.edit', $category) }}" aria-label="Editar {{ $category->name }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Editar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-6 py-14 text-center"><p class="font-semibold">No se encontraron categorías</p><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Prueba con otra búsqueda o crea una categoría.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4 dark:border-white/10">
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">{{ $categories->total() }} {{ $categories->total() === 1 ? 'categoría' : 'categorías' }}</p>
            {{ $categories->links() }}
        </div>
    </div>
</section>
