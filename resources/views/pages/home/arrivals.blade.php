<div class="dashboard-section-heading">
    <div>
        <p class="dashboard-eyebrow">Seguimiento por ETA</p>
        <h3>Próximos embarques en llegar</h3>
    </div>
    <div>
        <label for="arrival-notice-days" class="block text-sm font-medium">Días de anticipación</label>
        <input id="arrival-notice-days" type="number" min="0" max="365" step="1"
            wire:model.live.debounce.600ms="arrivalDays" class="mt-2 w-28" aria-describedby="arrival-notice-help">
        <span wire:loading wire:target="arrivalDays" role="status" class="dashboard-muted text-xs">Guardando...</span>
        @error('arrivalDays')<p role="alert" class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
    </div>
</div>
<p id="arrival-notice-help" class="dashboard-muted mb-4 text-sm">Se guarda automáticamente para tu usuario. Cada embarque aparece {{ $arrivalNoticeDays }} días antes de su ETA y permanece hasta la llegada. Fechas según la hora de Venezuela.</p>
<div class="dashboard-grid">
    <div class="dashboard-panel" data-tone="cyan">
        <div class="dashboard-panel-heading">
            <h3>Por llegar</h3>
            <span class="dashboard-total">{{ $upcomingArrivals->total() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr><th class="px-3">MSC / BL Master</th><th class="px-3">Buque</th><th class="px-3">ETA</th><th class="px-3">Tiempo restante</th></tr></thead>
                <tbody>
                    @forelse($upcomingArrivals as $arrival)
                        <tr wire:key="upcoming-arrival-{{ $arrival->id }}">
                            <td class="px-3"><a href="{{ route($shipmentEditRoute, $arrival) }}" class="font-semibold text-[color:var(--brand)]">MSC No. {{ $arrival->id }}</a><p class="dashboard-muted mt-1">{{ $arrival->bl_master }}</p></td>
                            <td class="px-3">{{ $arrival->vessel?->name ?? 'Sin buque' }}</td>
                            <td class="whitespace-nowrap px-3">{{ $arrival->eta->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-3 font-semibold">@php($daysRemaining = (int) $arrivalToday->diffInDays(\Illuminate\Support\Carbon::parse($arrival->eta->format('Y-m-d'), 'America/Caracas')))
                                {{ $daysRemaining === 0 ? 'Llega hoy' : 'Faltan '.$daysRemaining.' '.($daysRemaining === 1 ? 'día' : 'días') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="dashboard-muted px-3">No hay embarques por llegar dentro de los próximos {{ $arrivalNoticeDays }} días.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $upcomingArrivals->links() }}</div>
    </div>
    <div class="dashboard-panel" data-tone="teal">
        <div class="dashboard-panel-heading">
            <h3>Embarques llegados</h3>
            <span class="dashboard-total">{{ $arrivedShipments->total() }}</span>
        </div>
        <p class="dashboard-muted mb-4 text-xs">Clasificación automática según la ETA. La llegada real debe confirmarse en el seguimiento del embarque.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr><th class="px-3">MSC / BL Master</th><th class="px-3">Buque</th><th class="px-3">ETA</th><th class="px-3">Seguimiento</th></tr></thead>
                <tbody>
                    @forelse($arrivedShipments as $arrival)
                        <tr wire:key="arrived-shipment-{{ $arrival->id }}">
                            <td class="px-3"><a href="{{ route($shipmentEditRoute, $arrival) }}" class="font-semibold text-[color:var(--brand)]">MSC No. {{ $arrival->id }}</a><p class="dashboard-muted mt-1">{{ $arrival->bl_master }}</p></td>
                            <td class="px-3">{{ $arrival->vessel?->name ?? 'Sin buque' }}</td>
                            <td class="whitespace-nowrap px-3">{{ $arrival->eta->format('d/m/Y') }}</td>
                            <td class="px-3"><span class="dashboard-total whitespace-nowrap text-xs">Embarque llegado</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="dashboard-muted px-3">No hay embarques con la ETA cumplida.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $arrivedShipments->links() }}</div>
    </div>
</div>
