<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">{{ $foreignTrade ? 'Comercio exterior' : 'Comercio Internacional' }} / MSC</p><h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ $shipmentId ? 'MSC No. '.$shipmentId : 'Crear MSC' }}</h2><p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">Completa la información del embarque. Los campos con * son obligatorios.</p></div>
        <a href="{{ $this->mscRoute('index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Volver a la lista</a>
    </div>
    <form wire:submit="save" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-8">
        <div class="mb-6 border-b border-slate-200 bg-slate-50/70 pb-6 dark:border-white/10 dark:bg-slate-950/30">
            <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Status del embarque</p>
            <x-msc.status-bar :status="$status" interactive />
        </div>
        @if($foreignTrade)
            <div class="mb-6 border-b border-slate-200 bg-slate-50/70 pb-6 dark:border-white/10 dark:bg-slate-950/30">
                <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Status BL</p>
                <x-msc.status-bl-bar :status="$status_bl" interactive />
            </div>
        @endif
        <fieldset wire:loading.attr="disabled" class="grid min-w-0 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <legend class="sr-only">Información MSC</legend>
            <x-forms.input name="no" label="No." :value="$shipmentId ?? 'Se asignará al guardar'" readonly hint="Numeración automática del sistema." />
            <x-forms.input name="etd" label="ETD *" type="date" wire:model="etd" required />
            <x-forms.input name="eta" label="ETA *" type="date" wire:model="eta" required />
            <x-forms.input name="bl_master" label="BL Master *" wire:model="bl_master" maxlength="255" required />
            <x-forms.input name="bl_house" label="BL House" wire:model="bl_house" maxlength="255" />
            <div>
                <label for="factory_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Fábrica</label>
                <select id="factory_id" wire:model="factory_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    <option value="">Sin fábrica asignada</option>
                    @foreach($factories as $factory)<option value="{{ $factory->id }}">{{ $factory->name }}</option>@endforeach
                </select>
                @error('factory_id')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                @if($factories->isEmpty())<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Carga las fábricas desde Configuración → Fábricas.</p>@endif
            </div>
            <div>
                <label for="product_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Model *</label>
                <select id="product_id" wire:model.live="product_id" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    <option value="">Selecciona un producto</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                </select>
                @error('product_id')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                @if($products->isEmpty())<p class="mt-2 text-sm text-amber-600 dark:text-amber-300">Registra primero los modelos en Configuración → Productos.</p>@endif
            </div>
            <x-forms.input name="container_qty" label="Cant contenedores *" type="number" wire:model.live="container_qty" min="1" max="2147483647" step="1" required />
            <div>
                <label for="units_qty" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Cant Unidades</label>
                <input id="units_qty" name="units_qty" type="number" wire:model.live="units_qty" min="0" max="2147483647" step="1" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                @error('units_qty')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
            </div>
            <x-forms.input name="free_time" label="Free time (días) *" type="number" wire:model="free_time" min="0" max="2147483647" step="1" required />
            <div>
                <label for="vessel_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Buque</label>
                <select id="vessel_id" wire:model="vessel_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    <option value="">Sin buque asignado</option>
                    @foreach($vessels as $vessel)<option value="{{ $vessel->id }}">{{ $vessel->name }}</option>@endforeach
                </select>
                @error('vessel_id')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                @if($vessels->isEmpty())<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Carga los buques desde Configuración → Buques.</p>@endif
            </div>
            <div><x-forms.input name="released" label="RELEASED" wire:model="released" maxlength="255" /></div>
        </fieldset>
        @if($foreignTrade)
            <div data-foreign-trade-notebook class="mt-8 min-w-0 rounded-2xl border border-slate-200 dark:border-white/10">
                <div role="tablist" aria-label="Fases del comercio exterior" class="flex overflow-x-auto border-b border-slate-200 bg-slate-50 px-3 dark:border-white/10 dark:bg-white/5">
                    @foreach(['phase-1' => 'Fase 1', 'phase-2' => 'Fase 2', 'phase-3' => 'Fase 3', 'phase-4' => 'Fase 4', 'notes' => 'Notas'] as $tab => $label)
                        <button
                            type="button"
                            id="foreign-trade-tab-{{ $tab }}"
                            role="tab"
                            aria-controls="foreign-trade-panel-{{ $tab }}"
                            aria-selected="{{ $activeForeignTradeTab === $tab ? 'true' : 'false' }}"
                            tabindex="{{ $activeForeignTradeTab === $tab ? '0' : '-1' }}"
                            wire:click="selectForeignTradeTab('{{ $tab }}')"
                            wire:loading.attr="disabled"
                            @class([
                                'shrink-0 border-b-2 border-transparent px-4 py-4 text-sm font-medium transition hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-slate-400 dark:hover:text-indigo-300',
                                'border-indigo-600 bg-white text-indigo-700 dark:border-indigo-400 dark:bg-slate-900 dark:text-indigo-300' => $activeForeignTradeTab === $tab,
                                'text-slate-500 dark:text-slate-400' => $activeForeignTradeTab !== $tab,
                            ])
                        >
                            {{ $label }}
                    </button>
                    @endforeach
                    </div>
                    <div
                            id="foreign-trade-panel-{{ $activeForeignTradeTab }}"
                            role="tabpanel"
                            aria-labelledby="foreign-trade-tab-{{ $activeForeignTradeTab }}"
                            data-foreign-trade-panel="{{ $activeForeignTradeTab }}"
                            class="min-h-40 min-w-0 p-5 sm:p-6"
                    >
                            @if($activeForeignTradeTab === 'phase-1')
                                <div class="grid min-w-0 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <x-forms.input name="exp_number" label="N°EXP." wire:model="exp_number" maxlength="255" />
                                    <x-forms.input name="dua_intr" label="DUA/INTR" wire:model="dua_intr" maxlength="255" />
                                    <x-forms.input name="seabridge" label="SEABRIDGE" wire:model="seabridge" maxlength="255" />
                                    <x-forms.input name="sencamer" label="SENCAMER" wire:model="sencamer" maxlength="255" />
                                    <x-forms.input name="comex" label="COMEX" wire:model="comex" maxlength="255" />
                                    <x-forms.input name="recognition_date" label="Fecha Reconocimiento" type="date" wire:model="recognition_date" />
                                    <x-forms.input name="receipt_date" label="Fecha Recepción" type="date" wire:model="receipt_date" />

                                    <div>
                                        <label for="category_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Categoría</label>
                                        <select id="category_id" wire:model="category_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                                            <option value="">Sin categoría</option>
                                            @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                                        </select>
                                        @error('category_id')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                                        @if($categories->isEmpty())<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Crea las categorías en Configuración → Categorías.</p>@endif
                                    </div>

                                    <section class="sm:col-span-2 lg:col-span-3">
                                        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10">
                                            <div class="grid grid-cols-[minmax(0,1fr)_3rem] border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                                                <span>Contenedor</span>
                                                <span class="sr-only">Acciones</span>
                                            </div>
                                            @forelse($containerLines as $index => $line)
                                                <div wire:key="msc-container-line-{{ $index }}" class="grid grid-cols-[minmax(0,1fr)_3rem] items-center border-b border-slate-200 px-4 py-1 last:border-b-0 dark:border-white/10">
                                                    <div class="min-w-0">
                                                        <label for="container-line-{{ $index }}" class="sr-only">Contenedor {{ $index + 1 }}</label>
                                                        <input id="container-line-{{ $index }}" type="text" wire:model="containerLines.{{ $index }}.name" maxlength="255" placeholder="Escribe el número de contenedor..." class="w-full border-0 bg-transparent px-0 py-2.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:ring-0 dark:text-white dark:placeholder:text-slate-500">
                                                        @error("containerLines.$index.name")<p role="alert" class="pb-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                                                    </div>
                                                    <button type="button" wire:click="removeContainerLine({{ $index }})" wire:loading.attr="disabled" aria-label="Eliminar contenedor {{ $index + 1 }}" title="Eliminar línea" class="inline-flex size-8 items-center justify-center rounded text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50 dark:hover:bg-rose-500/10 dark:hover:text-rose-300">
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @empty
                                                <p class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No hay contenedores.</p>
                                            @endforelse
                                            <div class="border-t border-slate-200 px-4 py-3 dark:border-white/10">
                                                <button type="button" wire:click="addContainerLine" wire:loading.attr="disabled" class="text-sm font-medium text-indigo-600 transition hover:text-indigo-800 disabled:opacity-50 dark:text-indigo-300 dark:hover:text-indigo-200">Agregar una línea</button>
                                            </div>
                                        </div>
                                        @error('containerLines')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                                    </section>

                                    <x-forms.input name="shipment_year" label="Año" type="number" wire:model="shipment_year" min="1000" max="9999" step="1" />
                                    <x-forms.input name="etd_vnzl" label="ETD" type="date" wire:model="etd_vnzl" />
                                    <x-forms.input name="eta_vnzl" label="ETA VNZL" type="date" wire:model="eta_vnzl" />
                                    <x-forms.input name="port" label="Puerto" wire:model="port" maxlength="255" />
                                    <x-forms.input name="trasegado" label="TRASEGADO" wire:model="trasegado" maxlength="255" />
                                    <x-forms.input name="in_bond_date" label="In bond" type="date" wire:model="in_bond_date" />
                                    <x-forms.input name="free_days" label="Días libres" type="number" wire:model="free_days" min="0" max="2147483647" step="1" />
                                    <x-forms.input name="delay_date" label="Días demora" type="date" wire:model="delay_date" />
                                    <x-forms.input name="legal_abandonment_date" label="Abandono legal" type="date" wire:model="legal_abandonment_date" />
                                </div>
                            @elseif($activeForeignTradeTab === 'phase-2')
                                <div class="grid min-w-0 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <x-forms.input name="registration_date" label="REGISTRO" type="date" wire:model="registration_date" />
                                    <x-forms.input name="phase_two_recognition_date" label="RECONOCIMIENTO" type="date" wire:model="phase_two_recognition_date" />
                                    <x-forms.input name="validation_date" label="VALIDACIÓN" type="date" wire:model="validation_date" />
                                    <x-forms.input name="plant_receipt_date" label="RECEPCIÓN PLANTA" type="date" wire:model="plant_receipt_date" />
                                    <x-forms.input name="location" label="UBICACIÓN" wire:model="location" maxlength="255" />
                                    <x-forms.input name="return_date" label="DEVOLUCIÓN" type="date" wire:model="return_date" />
                                </div>
                            @endif
                    </div>
            </div>
        @endif
        <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-white/10">
            @if($shipmentId)<button type="button" wire:click="delete" wire:confirm="¿Eliminar este registro MSC? Esta acción no se puede deshacer." wire:loading.attr="disabled" class="mr-auto rounded-xl border border-rose-200 px-5 py-3 font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/10">Eliminar MSC</button>@endif
            <a href="{{ $this->mscRoute('index') }}" class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-400 disabled:opacity-60"><span wire:loading.remove wire:target="save">Guardar MSC</span><span wire:loading wire:target="save">Guardando...</span></button>
        </div>
    </form>
</section>
