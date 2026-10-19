<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">{{ $foreignTrade ? 'Comercio exterior' : 'Comercio Internacional' }} / MSC</p><h2 class="mt-3 text-3xl font-semibold tracking-tight">Importar MSC</h2><p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">Carga embarques desde Excel (.xlsx).</p></div><a href="{{ $this->mscRoute('index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Volver a la lista</a></div>
    <div class="mb-6 rounded-2xl border border-indigo-200 bg-indigo-50 p-6 text-sm leading-7 text-indigo-800 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-200">
        <p class="font-semibold">Antes de importar</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            <li>El No. del archivo se ignora: cada registro nuevo recibe el siguiente número del sistema.</li>
            <li>Model debe coincidir con un producto registrado. Se normalizan espacios y saltos de línea al buscarlo.</li>
            <li>Factory se asigna si coincide con una fábrica de Configuración → Fábricas; si no coincide, queda sin asignar.</li>
            <li>Buque se asigna si coincide con un buque de Configuración → Buques; si no coincide, queda sin asignar. También se aceptan archivos anteriores con el encabezado Ship Agent.</li>
            <li>Cant Unidades puede indicarse en el archivo o dejarse vacía para calcularse según las unidades por contenedor del producto y Cant contenedores.</li>
            <li>Usa las columnas BL Master y BL House. En archivos anteriores, la primera línea del Bill of Lading se toma como BL Master y la segunda como BL House.</li>
            @if($foreignTrade)<li>La columna Categoría es opcional y debe coincidir con una categoría registrada en Configuración → Categorías.</li>@endif
            <li>Usa fechas de Excel o el formato AAAA-MM-DD. Free time acepta días numéricos o valores como «21 days».</li>
            <li>Los pares BL Master / BL House ya registrados se omiten. Los registros existentes no se modifican.</li>
            <li>Se procesa la primera hoja. Se admiten el formato de la plantilla y el archivo original, omitiendo títulos, notas y totales.</li>
        </ul>
        <p class="mt-3">Si alguna fila es inválida, se cancela toda la carga y se indica la fila que debes corregir.</p>
        <a href="{{ $this->mscRoute('template') }}" class="mt-4 inline-flex rounded-xl border border-indigo-200 bg-white px-5 py-2.5 font-semibold hover:bg-indigo-100 dark:border-indigo-400/20 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20">Descargar plantilla</a>
    </div>
    <form wire:submit="import" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-8" x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false">
        <label for="msc-file" class="mb-3 block text-sm font-semibold">Archivo MSC *</label><input id="msc-file" type="file" wire:model="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required wire:loading.attr="disabled" wire:target="import" class="block w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-100 file:px-4 file:py-2 file:font-semibold file:text-indigo-700 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300 dark:file:bg-indigo-500/15 dark:file:text-indigo-300">
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Excel .xlsx. Tamaño máximo: 10 MB.</p><p wire:loading wire:target="file" role="status" class="mt-3 text-sm text-indigo-600 dark:text-indigo-300">Subiendo archivo...</p>
        @error('file')<p role="alert" class="mt-3 text-sm font-medium text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
        <div class="mt-8 flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-6 dark:border-white/10"><a href="{{ $this->mscRoute('index') }}" class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</a><button type="submit" x-bind:disabled="uploading" wire:loading.attr="disabled" wire:target="file,import" class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-400 disabled:opacity-60"><span wire:loading.remove wire:target="import">Importar MSC</span><span wire:loading wire:target="import">Importando...</span></button></div>
    </form>
</section>
