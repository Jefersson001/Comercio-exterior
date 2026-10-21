<section class="record-history mt-8" wire:poll.30s.visible aria-label="Historial del registro">
    <header class="record-history-heading"><div><p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-300">Seguimiento automático</p><h3 class="mt-1 text-lg font-semibold">Historial del registro</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Quién realizó cada cambio, cuándo y qué información modificó.</p></div><span class="record-history-count">{{ $entries->total() }} eventos</span></header>
    <div class="record-history-body">
        @forelse($entries as $entry)
            <x-history.entry :entry="$entry" :key="$entry->id" />
        @empty
            <p class="record-history-empty">Todavía no hay cambios registrados. El seguimiento comienza desde su activación; los cambios anteriores no están disponibles.</p>
        @endforelse
    </div>
    @if($entries->hasPages())<footer class="record-history-footer">{{ $entries->links() }}</footer>@endif
</section>
