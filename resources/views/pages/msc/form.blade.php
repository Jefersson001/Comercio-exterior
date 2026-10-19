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
            <div class="sm:col-span-2 lg:col-span-3"><x-forms.input name="released" label="RELEASED" wire:model="released" maxlength="255" /></div>
            @if($foreignTrade)
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="category_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Categoría</label>
                    <select id="category_id" wire:model="category_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                        <option value="">Sin categoría</option>
                        @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                    @error('category_id')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                    @if($categories->isEmpty())<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Crea las categorías en Configuración → Categorías.</p>@endif
                </div>
            @endif
        </fieldset>
        <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-white/10">
            @if($shipmentId)<button type="button" wire:click="delete" wire:confirm="¿Eliminar este registro MSC? Esta acción no se puede deshacer." wire:loading.attr="disabled" class="mr-auto rounded-xl border border-rose-200 px-5 py-3 font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/10">Eliminar MSC</button>@endif
            <a href="{{ $this->mscRoute('index') }}" class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-400 disabled:opacity-60"><span wire:loading.remove wire:target="save">Guardar MSC</span><span wire:loading wire:target="save">Guardando...</span></button>
        </div>
    </form>
</section>
