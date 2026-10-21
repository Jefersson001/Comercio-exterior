<section class="mx-auto max-w-6xl">
    <div class="mb-6"><p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-300">Configuración / Seguimiento</p><h2 class="mt-2 text-3xl font-semibold">Historial de cambios</h2><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Consulta las creaciones, modificaciones y eliminaciones. Las entradas son de solo lectura.</p></div>
    <div class="record-history">
        <div class="record-history-heading grid gap-4 sm:grid-cols-2">
            <div class="min-w-0"><label for="history-search" class="mb-2 block text-sm font-medium">Buscar registro o persona</label><input id="history-search" type="search" wire:model.live.debounce.350ms="search" class="w-full" placeholder="Nombre del registro o de quien lo modificó…"></div>
            <div class="min-w-0"><label for="history-type" class="mb-2 block text-sm font-medium">Módulo</label><x-forms.select id="history-type" wire:model.live="type"><option value="">Todos los módulos</option>@foreach(['msc' => 'MSC', 'product' => 'Productos', 'category' => 'Categorías', 'factory' => 'Fábricas', 'vessel' => 'Buques', 'user' => 'Usuarios'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-forms.select></div>
        </div>
        <div class="record-history-body">
            @forelse($entries as $entry)<x-history.entry :entry="$entry" show-subject />@empty<p class="record-history-empty">No hay eventos que coincidan con la búsqueda.</p>@endforelse
        </div>
        <footer class="record-history-footer">{{ $entries->links() }}</footer>
    </div>
</section>
