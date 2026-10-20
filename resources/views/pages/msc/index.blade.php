<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div><p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">{{ $foreignTrade ? 'Comercio exterior' : 'Comercio Internacional' }} / Embarques</p><h2 class="mt-3 text-3xl font-semibold tracking-tight">MSC</h2><p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">Consulta y administra tus embarques MSC.</p></div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ $this->mscRoute('template') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Descargar plantilla</a>
            <a href="{{ route('msc.export', ['search' => $search]) }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Reporte Control Carga Interno</a>
            <button type="button" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 dark:border-white/10 dark:text-slate-300">Reporte Control Carga Direct</button>
            <a href="{{ $this->mscRoute('import') }}" class="rounded-xl border border-indigo-200 px-5 py-3 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-400/20 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Importar MSC</a>
            <a href="{{ $this->mscRoute('create') }}" class="rounded-xl bg-indigo-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400">Crear MSC</a>
        </div>
    </div>
    @if(session('status'))<div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">{{ session('status') }}</div>@endif
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
        <div class="border-b border-slate-200 p-5 dark:border-white/10">
            <label for="msc-search" class="sr-only">Buscar MSC</label><input id="msc-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por No., BL Master, BL House, Fábrica, Buque o Model..." class="w-full max-w-xl rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-950 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-950/60 dark:text-white">
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">La exportación incluye todos los resultados de la búsqueda. Pulsa una fila para abrir su formulario.</p>
        </div>
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="w-full min-w-[105rem] text-left text-sm">
                <thead class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-950/40 dark:text-slate-400"><tr>
                    @foreach(\App\Support\MscFields::headers($foreignTrade) as $label)<th scope="col" class="px-5 py-4 font-semibold">{{ $label === 'Factory' ? 'Fábrica' : $label }}</th>@endforeach
                    @if($foreignTrade)<th scope="col" class="px-5 py-4 font-semibold">Status BL</th>@endif
                    <th scope="col" class="px-5 py-4 font-semibold">Acciones</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse($shipments as $shipment)
                        <tr wire:key="msc-{{ $shipment->id }}" data-clickable-row data-href="{{ $this->mscRoute('edit', ['shipment' => $shipment]) }}" tabindex="0" aria-label="Abrir MSC No. {{ $shipment->id }}" class="cursor-pointer transition hover:bg-indigo-50/60 focus:bg-indigo-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 dark:hover:bg-indigo-500/5 dark:focus:bg-indigo-500/5">
                            <td class="px-5 py-4"><a href="{{ $this->mscRoute('edit', ['shipment' => $shipment]) }}" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-300">{{ $shipment->id }}</a></td>
                            <td class="whitespace-nowrap px-5 py-4">{{ $shipment->etd->format('d/m/Y') }}</td><td class="whitespace-nowrap px-5 py-4">{{ $shipment->eta->format('d/m/Y') }}</td>
                            <td class="whitespace-pre-line px-5 py-4 font-medium">{{ $shipment->bl_master }}</td><td class="whitespace-pre-line px-5 py-4">{{ $shipment->bl_house ?: '—' }}</td><td class="whitespace-pre-line px-5 py-4">{{ $shipment->factoryRecord?->name ?? '—' }}</td>
                            <td class="px-5 py-4">{{ $shipment->product->name }}</td><td class="px-5 py-4">{{ $shipment->container_qty }}</td><td class="px-5 py-4">{{ $shipment->totalUnits() ?? '—' }}</td><td class="whitespace-nowrap px-5 py-4">{{ $shipment->free_time }} días</td>
                            <td class="px-5 py-4">{{ $shipment->vessel?->name ?? '—' }}</td><td class="px-5 py-4">{{ $shipment->released ?: '—' }}</td>
                            <td class="min-w-80 px-5 py-4"><x-msc.status-bar :status="$shipment->status" /></td>
                            @if($foreignTrade)
                                <td class="min-w-96 px-5 py-4"><x-msc.status-bl-bar :status="$shipment->status_bl" /></td>
                                <td class="px-5 py-4">{{ $shipment->category?->name ?? 'Sin categoría' }}</td>
                            @endif
                            <td class="px-5 py-4"><a href="{{ $this->mscRoute('edit', ['shipment' => $shipment]) }}" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-300">Editar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $foreignTrade ? 16 : 14 }}" class="px-6 py-14 text-center"><p class="font-semibold">No se encontraron registros MSC</p><p class="mt-2 text-slate-500 dark:text-slate-400">Crea un registro o importa tu archivo de Excel.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4 dark:border-white/10"><p class="mb-3 text-sm text-slate-500 dark:text-slate-400">{{ $shipments->total() }} registros MSC</p>{{ $shipments->links() }}</div>
    </div>
</section>
