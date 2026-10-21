<div class="dashboard-summary" aria-label="Totales generales">
    @if($showTrade)
        <x-dashboard.metric label="Embarques MSC" :value="$shipmentTotal" hint="Registros únicos de embarques" :href="route($mscRoute)" />
    @endif
    @if($showConfiguration)
        <x-dashboard.metric label="Productos registrados" :value="$productTotal" hint="Productos del catálogo" icon="products" tone="teal" :href="route('configuration.products.index')" />
        <x-dashboard.metric label="Categorías" :value="$categoryTotal" hint="Incluye categorías sin registros" icon="categories" tone="amber" :href="route('configuration.categories.index')" />
        <x-dashboard.metric label="Buques registrados" :value="$vessels->count()" hint="Buques disponibles en el catálogo" tone="cyan" :href="route('configuration.vessels.index')" />
        <x-dashboard.metric label="Fábricas registradas" :value="$factories->count()" hint="Fábricas disponibles en el catálogo" icon="factory" tone="teal" :href="route('configuration.factories.index')" />
    @endif
    @if($showUsers)
        <x-dashboard.metric label="Usuarios registrados" :value="$userTotal" :hint="$activeUsers.' activos · '.$inactiveUsers.' inactivos'" icon="users" tone="slate" :href="route('configuration.users.index')" />
    @endif
</div>

@if($showTrade)
    <div class="dashboard-section-heading"><div><p class="dashboard-eyebrow">Seguimiento operativo</p><h3>Panorama de embarques</h3></div><a href="{{ route($mscRoute) }}">Ver registros <span aria-hidden="true">→</span></a></div>
    <div class="dashboard-grid">
        <x-dashboard.breakdown id="shipment-statuses" title="Status del embarque" description="Cada embarque se cuenta una sola vez, según su estado actual." :rows="$shipmentStatuses" :total="$shipmentTotal" eyebrow="Seguimiento" />
        @if($showExterior)
            <x-dashboard.breakdown id="bl-statuses" title="Status BL" description="Distribución por estado de la documentación." :rows="$blStatuses" :total="$shipmentTotal" eyebrow="Documentación" tone="teal" />
        @endif
    </div>
@endif

@if($showExterior || $showConfiguration)
    <div class="dashboard-section-heading"><div><p class="dashboard-eyebrow">Clasificación</p><h3>Distribución por categorías</h3></div><span class="dashboard-muted text-xs">Las categorías nuevas aparecen automáticamente</span></div>
    <div class="dashboard-grid dashboard-category-grid">
        @if($showExterior)
            <x-dashboard.breakdown id="shipment-categories" title="Embarques por categoría" description="Categoría asignada a cada registro MSC; no cuenta líneas de productos." :rows="$shipmentCategories" :total="$shipmentTotal" eyebrow="Comercio exterior" />
        @endif
        @if($showConfiguration)
            <x-dashboard.breakdown id="product-categories" title="Productos por categoría" description="Productos del catálogo agrupados por su categoría." :rows="$productCategories" :total="$productTotal" eyebrow="Configuración" tone="teal" />
        @endif
        @if($showConfiguration)
            <article class="dashboard-panel" data-dashboard-section="vessels" data-tone="cyan">
                <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Transporte marítimo</p><h3>Buques registrados</h3><p class="dashboard-muted mt-1 text-xs">{{ $showTrade ? 'Cantidad de embarques asociados a cada buque.' : 'Catálogo de buques disponibles.' }}</p></div><span class="dashboard-total">{{ $vessels->count() }}</span></div>
                <div class="dashboard-vessels">
                    @forelse($vessels as $vessel)
                        <a class="dashboard-vessel" href="{{ route('configuration.vessels.edit', $vessel) }}" data-vessel="{{ $vessel->id }}">
                            <span class="dashboard-icon"><x-dashboard.icon /></span><span class="min-w-0 grow break-words">{{ $vessel->name }}</span>
                            @if($showTrade)<span class="shrink-0 text-right"><strong>{{ $vessel->msc_shipments_count }}</strong><span class="dashboard-muted ml-1 text-xs">embarques</span></span>@else<span aria-hidden="true">↗</span>@endif
                        </a>
                    @empty
                        <p class="dashboard-empty">Todavía no hay buques registrados.</p>
                    @endforelse
                </div>
                @if($showTrade)<p class="dashboard-footnote">{{ $shipmentsWithoutVessel }} embarques sin buque asignado.</p>@endif
                <a class="dashboard-panel-link" href="{{ route('configuration.vessels.index') }}">Ver catálogo de buques <span aria-hidden="true">→</span></a>
            </article>
        @endif
    </div>
@endif

@if($showConfiguration || $showUsers)
    <div class="dashboard-section-heading"><div><p class="dashboard-eyebrow">Catálogos y equipo</p><h3>Fábricas y usuarios</h3></div></div>
    <div class="dashboard-grid">
    @if($showConfiguration)
    <article class="dashboard-panel" data-dashboard-section="factories" data-tone="teal">
        <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Configuración</p><h3>Fábricas registradas</h3><p class="dashboard-muted mt-1 text-xs">{{ $showTrade ? 'Cantidad de embarques asociados a cada fábrica.' : 'Catálogo de fábricas disponibles.' }}</p></div><span class="dashboard-total">{{ $factories->count() }}</span></div>
        <div class="dashboard-vessels">
            @forelse($factories as $factory)
                <div class="dashboard-vessel" data-factory="{{ $factory->id }}">
                    <a class="flex min-w-0 grow items-center gap-3" href="{{ route('configuration.factories.edit', $factory) }}">
                        <span class="dashboard-icon"><x-dashboard.icon name="factory" /></span><span class="min-w-0 break-words">{{ $factory->name }}</span>
                    </a>
                    @if($showTrade)
                        <a class="dashboard-factory-count shrink-0 text-right" href="{{ route($mscRoute, ['factory_id' => $factory->id]) }}" aria-label="Ver {{ $factory->msc_shipments_count }} embarques de {{ $factory->name }}">
                            <strong>{{ $factory->msc_shipments_count }}</strong><span class="ml-1 text-xs">embarques</span><span class="ml-1" aria-hidden="true">↗</span>
                        </a>
                    @else<span aria-hidden="true">↗</span>@endif
                </div>
            @empty
                <p class="dashboard-empty">Todavía no hay fábricas registradas.</p>
            @endforelse
        </div>
        @if($showTrade)<p class="dashboard-footnote">{{ $shipmentsWithoutFactory }} embarques sin fábrica asignada.</p>@endif
        <a class="dashboard-panel-link" href="{{ route('configuration.factories.index') }}">Ver catálogo de fábricas <span aria-hidden="true">→</span></a>
    </article>
    @endif

    @if($showUsers)
            <article class="dashboard-panel dashboard-team" data-dashboard-section="users">
                <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Administración</p><h3>Usuarios del sistema</h3><p class="dashboard-muted mt-1 text-xs">Todas las cuentas registradas, activas e inactivas.</p></div><span class="dashboard-icon"><x-dashboard.icon name="users" /></span></div>
                <div class="dashboard-team-total"><strong>{{ $userTotal }}</strong><span class="dashboard-muted">usuarios registrados</span></div>
                <div class="dashboard-user-states"><div><span class="dashboard-state-dot" aria-hidden="true"></span><span>Activos</span><strong>{{ $activeUsers }}</strong></div><div><span class="dashboard-state-dot is-inactive" aria-hidden="true"></span><span>Inactivos</span><strong>{{ $inactiveUsers }}</strong></div></div>
                <a class="dashboard-panel-link" href="{{ route('configuration.users.index') }}">Administrar usuarios <span aria-hidden="true">→</span></a>
            </article>
    @endif
    </div>
@endif
<p class="dashboard-muted mt-6 text-xs" role="status">Las cantidades se actualizan automáticamente cada minuto.</p>
