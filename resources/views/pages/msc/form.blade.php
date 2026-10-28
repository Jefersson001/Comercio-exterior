<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">{{ $foreignTrade ? 'Comercio exterior' : 'Comercio Internacional' }} / MSC</p><h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ $shipmentId ? 'MSC No. '.$shipmentId : 'Crear MSC' }}</h2><p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">Completa la información del embarque. Los campos con * son obligatorios.</p></div>
        <a href="{{ $this->mscRoute('index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Volver a la lista</a>
    </div>
    @if (session('status'))
        <div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">{{ session('status') }}</div>
    @endif
    <form data-record-form wire:submit="save" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-8">
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
            <x-forms.input name="etd" label="ETD" type="date" wire:model="etd" hint="Deja vacío si la fecha de salida no se conoce." />
            <x-forms.input name="eta" label="ETA *" type="date" wire:model="eta" required />
            <x-forms.input name="bl_master" label="BL Master *" wire:model="bl_master" maxlength="255" required />
            <x-forms.input name="bl_house" label="BL House" wire:model="bl_house" maxlength="255" />
            <div>
                <label for="factory_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Fábrica</label>
                <x-forms.select id="factory_id" wire:model="factory_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    <option value="">Sin fábrica asignada</option>
                    @foreach($factories as $factory)<option value="{{ $factory->id }}">{{ $factory->name }}</option>@endforeach
                </x-forms.select>
                @error('factory_id')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                @if($factories->isEmpty())<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Carga las fábricas desde Configuración → Fábricas.</p>@endif
            </div>
            @if(! $foreignTrade && ! $hasMultipleProducts)
            <div>
                <label for="product_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Model *</label>
                <x-forms.select id="product_id" wire:model.live="product_id" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    <option value="">Selecciona un producto</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                </x-forms.select>
                @error('product_id')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                @if($products->isEmpty())<p class="mt-2 text-sm text-amber-600 dark:text-amber-300">Registra primero los modelos en Configuración → Productos.</p>@endif
            </div>
            <x-forms.input name="container_qty" label="Cant contenedores *" type="number" wire:model.live="container_qty" min="1" max="2147483647" step="1" required />
            <div>
                <label for="units_qty" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Cant Unidades</label>
                <input id="units_qty" name="units_qty" type="number" wire:model.live="units_qty" min="0" max="2147483647" step="1" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                @error('units_qty')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
            </div>
            @endif
            <x-forms.input name="free_time" label="Free time (días) *" type="number" wire:model="free_time" min="0" max="2147483647" step="1" required />
            <div>
                <label for="vessel_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Buque</label>
                <x-forms.select id="vessel_id" wire:model="vessel_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    <option value="">Sin buque asignado</option>
                    @foreach($vessels as $vessel)<option value="{{ $vessel->id }}">{{ $vessel->name }}</option>@endforeach
                </x-forms.select>
                @error('vessel_id')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                @if($vessels->isEmpty())<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Carga los buques desde Configuración → Buques.</p>@endif
            </div>
            <div><x-forms.input name="released" label="RELEASED" wire:model="released" maxlength="255" /></div>
        </fieldset>
        @if($foreignTrade || $hasMultipleProducts)
            @include('pages.msc.product-lines')
        @endif
        @if($foreignTrade)
            <div data-foreign-trade-notebook class="mt-8 min-w-0 rounded-2xl border border-slate-200 dark:border-white/10">
                <div role="tablist" aria-label="Fases del comercio exterior" class="flex overflow-x-auto border-b border-slate-200 bg-slate-50 px-3 dark:border-white/10 dark:bg-white/5">
                    @foreach(['phase-1' => 'Fase 1', 'phase-2' => 'Fase 2', 'phase-3' => 'Fase 3', 'documents' => 'Documentos', 'notes' => 'Notas'] as $tab => $label)
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
                                    <x-forms.input name="receipt_date" label="Fecha Recepción" type="date" wire:model="receipt_date" />
                                    <x-forms.input name="recognition_date" label="Fecha Reconocimiento" type="date" wire:model="recognition_date" />
                                    



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

                                    @if($variantsEnabled)
                                    <section class="sm:col-span-2 lg:col-span-3">
                                        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10">
                                            <div class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                                                <span class="min-w-0 flex-1">Variante del producto</span>
                                                <span class="w-20 shrink-0 text-left">Cant</span>
                                                <span class="w-8 shrink-0" aria-hidden="true"></span>
                                            </div>
                                            @forelse($productVariantLines as $index => $line)
                                                <div wire:key="msc-product-variant-line-{{ $index }}" class="flex items-center gap-3 border-b border-slate-200 px-4 py-1 last:border-b-0 dark:border-white/10">
                                                    <div class="min-w-0 flex-1">
                                                        <label for="product-variant-line-{{ $index }}" class="sr-only">Variante {{ $index + 1 }}</label>
                                                        <x-forms.select id="product-variant-line-{{ $index }}" wire:model="productVariantLines.{{ $index }}.product_variant_id" class="w-full border-0 bg-transparent px-0 py-2.5 text-sm text-slate-950 outline-none focus:ring-0 dark:text-white">
                                                            <option value="">Selecciona color...</option>
                                                            @foreach($productVariants as $variant)
                                                                <option value="{{ $variant->id }}">{{ $variant->product->name }} ({{ $variant->color }})</option>
                                                            @endforeach
                                                        </x-forms.select>
                                                        @error("productVariantLines.$index.product_variant_id")<p role="alert" class="pb-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div class="w-20 shrink-0">
                                                        <label for="product-variant-quantity-{{ $index }}" class="sr-only">Cantidad de variante {{ $index + 1 }}</label>
                                                        <input id="product-variant-quantity-{{ $index }}" type="number" wire:model="productVariantLines.{{ $index }}.quantity" min="0" max="2147483647" step="1" class="w-full border-0 bg-transparent px-1 py-2.5 text-sm text-slate-950 outline-none focus:ring-0 dark:text-white">
                                                        @error("productVariantLines.$index.quantity")<p role="alert" class="pb-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                                                    </div>
                                                    <button type="button" wire:click="removeProductVariantLine({{ $index }})" wire:loading.attr="disabled" aria-label="Eliminar variante {{ $index + 1 }}" title="Eliminar línea" class="inline-flex size-8 shrink-0 items-center justify-center rounded text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50 dark:hover:bg-rose-500/10 dark:hover:text-rose-300">
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @empty
                                                <p class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No hay variantes agregadas.</p>
                                            @endforelse
                                            <div class="border-t border-slate-200 px-4 py-3 dark:border-white/10">
                                                <button type="button" wire:click="addProductVariantLine" wire:loading.attr="disabled" class="text-sm font-medium text-indigo-600 transition hover:text-indigo-800 disabled:opacity-50 dark:text-indigo-300 dark:hover:text-indigo-200">Agregar una línea</button>
                                            </div>
                                        </div>
                                        @error('productVariantLines')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                                    </section>
                                    @endif

                                    <x-forms.input name="shipment_year" label="Año" type="number" wire:model="shipment_year" min="1000" max="9999" step="1" />
                                    <x-forms.input name="etd_vnzl" label="ETD" type="date" wire:model="etd_vnzl" />
                                    <x-forms.input name="eta_vnzl" label="ETA VNZL" type="date" wire:model="eta_vnzl" />
                                    <x-forms.input name="port" label="Puerto" wire:model="port" maxlength="255" />
                                    <x-forms.input name="trasegado" label="TRASEGADO" wire:model="trasegado" maxlength="255" />
                                    <x-forms.input name="in_bond_date" label="In bond" type="date" wire:model="in_bond_date" />
                                    <x-forms.input name="free_days" label="Días libres" type="number" wire:model="free_days" min="0" max="2147483647" step="1" />
                                    <x-forms.input name="delay_date" label="Demora" type="date" wire:model="delay_date" />
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
                            @elseif($activeForeignTradeTab === 'phase-3')
                                <div class="grid min-w-0 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <x-forms.input name="original_docs_receipt_date" label="FECHA RECEP DE DOC ORIG" type="date" wire:model="original_docs_receipt_date" />
                                    <x-forms.input name="aa_instructions_sent_date" label="FECHA INSTRUCCIONES ENVIADAS AA" type="date" wire:model="aa_instructions_sent_date" />
                                    <x-forms.input name="bl_release_date" label="FECHA LIBERACION BL" type="date" wire:model="bl_release_date" />
                                    <x-forms.input name="sencamer_request_date" label="FECHA DE SOLICITUD DE SENCAMER" type="date" wire:model="sencamer_request_date" />
                                    <x-forms.input name="sencamer_receipt_date" label="FECHA RECEPCION DE SENCAMER" type="date" wire:model="sencamer_receipt_date" />
                                    <x-forms.input name="comex_request_date" label="FECHA SOLICITUD DE COMEX" type="date" wire:model="comex_request_date" />
                                    <x-forms.input name="comex_receipt_date" label="FECHA RECEPCION DE COMEX" type="date" wire:model="comex_receipt_date" />
                                </div>
                            @elseif($activeForeignTradeTab === 'documents')
                                <div class="min-w-0 space-y-6">
                                    @if(session('document_status'))
                                        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                                            {{ session('document_status') }}
                                        </div>
                                    @endif

                                    <div class="rounded-2xl border border-[color:var(--line)] bg-[var(--surface-soft)] p-5">
                                        <div class="mb-4">
                                            <h4 class="text-sm font-semibold text-[color:var(--ink)]">Adjuntar nuevo documento</h4>
                                            <p class="text-xs text-[color:var(--muted)]">Sube archivos PDF, imágenes o documentos de oficina (máx. 20 MB). Puedes agregar más documentos en cualquier momento.</p>
                                        </div>

                                        <div class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                                            <div class="min-w-0">
                                                <label for="new_document_file" class="mb-2 block text-sm font-medium text-[color:var(--ink)]">Archivo *</label>
                                                <input
                                                    id="new_document_file"
                                                    type="file"
                                                    wire:model="newDocumentFile"
                                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip"
                                                    class="block w-full border-0! bg-transparent! p-0! text-sm file:transition hover:file:bg-[var(--brand-soft)]"
                                                />
                                                <div wire:loading wire:target="newDocumentFile" class="mt-1 text-xs text-[color:var(--brand)]">
                                                    Cargando archivo...
                                                </div>
                                                @error('newDocumentFile')
                                                    <p role="alert" class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="flex items-end">
                                                <button
                                                    type="button"
                                                    wire:click="uploadDocument"
                                                    wire:loading.attr="disabled"
                                                    wire:target="uploadDocument, newDocumentFile"
                                                    class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition disabled:opacity-60"
                                                >
                                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                    </svg>
                                                    <span wire:loading.remove wire:target="uploadDocument">Adjuntar documento</span>
                                                    <span wire:loading wire:target="uploadDocument">Guardando...</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="mb-4 flex items-center justify-between">
                                            <h4 class="text-sm font-semibold uppercase tracking-wider text-[color:var(--ink)]">
                                                Documentos del embarque ({{ $documents->count() + count($pendingDocuments) }})
                                            </h4>
                                        </div>

                                        @if($documents->isEmpty() && count($pendingDocuments) === 0)
                                            <div class="rounded-2xl border border-dashed border-[color:var(--line)] p-8 text-center">
                                                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-[var(--surface-soft)] text-[color:var(--muted)]">
                                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                    </svg>
                                                </div>
                                                <h5 class="mt-3 text-sm font-semibold text-[color:var(--ink)]">No hay documentos registrados</h5>
                                                <p class="mt-1 text-sm text-[color:var(--muted)]">Puedes adjuntar facturas, copias de BL, notas y certificados arriba para mantener el expediente al día.</p>
                                            </div>
                                        @else
                                            <div class="divide-y divide-[color:var(--line)] overflow-hidden rounded-2xl border border-[color:var(--line)] bg-[var(--surface)]">
                                                @foreach($pendingDocuments as $pending)
                                                    <div wire:key="pending-doc-{{ $pending['temp_id'] }}" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between bg-amber-50/40 dark:bg-amber-500/5">
                                                        <div class="flex items-center gap-3 min-w-0">
                                                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="truncate text-sm font-semibold text-[color:var(--ink)]">{{ $pending['name'] }}</span>
                                                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">Pendiente de guardar</span>
                                                                </div>
                                                                <p class="truncate text-xs text-[color:var(--muted)]">{{ $pending['original_name'] }} • {{ number_format($pending['file_size'] / 1024, 1) }} KB</p>
                                                            </div>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            wire:click="removePendingDocument('{{ $pending['temp_id'] }}')"
                                                            wire:loading.attr="disabled"
                                                            title="Quitar de la lista"
                                                            class="inline-flex items-center gap-1.5 self-end rounded-lg px-3 py-1.5 text-xs font-semibold text-[color:var(--danger)] hover:bg-[color-mix(in_srgb,var(--danger)_10%,var(--surface))] sm:self-auto"
                                                        >
                                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            Quitar
                                                        </button>
                                                    </div>
                                                @endforeach

                                                @foreach($documents as $doc)
                                                    <div wire:key="saved-doc-{{ $doc->id }}" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between hover:bg-[var(--hover)]">
                                                        <div class="flex items-center gap-3 min-w-0">
                                                            <div @class([
'flex size-10 shrink-0 items-center justify-center rounded-xl',
'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400' => in_array($doc->extension(), ['pdf']),
'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' => in_array($doc->extension(), ['xls','xlsx','csv']),
'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' => in_array($doc->extension(), ['doc','docx','txt']),
'bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-400' => in_array($doc->extension(), ['jpg','jpeg','png','webp']),
'bg-[var(--surface-soft)] text-[color:var(--muted)]' => !in_array($doc->extension(), ['pdf','xls','xlsx','csv','doc','docx','txt','jpg','jpeg','png','webp']),
                                                            ])>
                                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                                </svg>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="truncate text-sm font-semibold text-[color:var(--ink)]">{{ $doc->name }}</p>
                                                                <p class="truncate text-xs text-[color:var(--muted)]">
                                                                    {{ $doc->original_name }} • {{ $doc->formattedSize() }} • {{ $doc->created_at->format('d/m/Y H:i') }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-2 self-end sm:self-auto">
                                                            <button
                                                                type="button"
                                                                wire:click="downloadDocument({{ $doc->id }})"
                                                                wire:loading.attr="disabled"
                                                                title="Descargar documento"
                                                                class="inline-flex items-center gap-1.5 rounded-lg border border-[color:var(--line)] bg-[var(--surface)] px-3 py-1.5 text-xs font-semibold text-[color:var(--ink)] shadow-sm transition hover:bg-[var(--hover)]"
                                                            >
                                                                <svg class="size-4 text-[color:var(--muted)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                                </svg>
                                                                Descargar
                                                            </button>
                                                            <button
                                                                type="button"
                                                                wire:click="deleteDocument({{ $doc->id }})"
                                                                wire:confirm="¿Seguro que deseas eliminar este documento? Esta acción no se puede deshacer."
                                                                wire:loading.attr="disabled"
                                                                title="Eliminar documento"
                                                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[color:var(--danger)] transition hover:bg-[color-mix(in_srgb,var(--danger)_10%,var(--surface))]"
                                                            >
                                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                                <span class="sr-only">Eliminar</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @elseif($activeForeignTradeTab === 'notes')
                                <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                                    <x-forms.textarea name="customs_agent_status" label="STATUS AGENTE DE ADUANA" wire:model="customs_agent_status" maxlength="10000" />
                                    <x-forms.textarea name="customs_agent_observation" label="OBSERVACION AGENTE DE ADUANA" wire:model="customs_agent_observation" maxlength="10000" />
                                </div>
                            @endif
                    </div>
            </div>
        @endif
        <div id="record-actions" data-form-actions class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-white/10">
            @if($shipmentId)<button type="button" wire:click="delete" wire:confirm="¿Eliminar este registro MSC? Esta acción no se puede deshacer." wire:loading.attr="disabled" class="mr-auto rounded-xl border border-rose-200 px-5 py-3 font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/10">Eliminar MSC</button>@endif
            <a href="{{ $this->mscRoute('index') }}" class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-400 disabled:opacity-60"><span wire:loading.remove wire:target="save">Guardar MSC</span><span wire:loading wire:target="save">Guardando...</span></button>
        </div>
    </form>
    @if($shipmentId)<livewire:record-history-panel subject-type="msc" :subject-id="$shipmentId" :foreign-trade="$foreignTrade" :key="'history-msc-'.$shipmentId" />@endif
</section>
