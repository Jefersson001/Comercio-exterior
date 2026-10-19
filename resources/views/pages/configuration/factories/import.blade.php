<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Configuración / Fábricas</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">Importar fábricas</h2>
            <p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">Carga masivamente el catálogo de fábricas desde Excel.</p>
        </div>
        <a href="{{ route('configuration.factories.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Volver a la lista</a>
    </div>
    <div class="mb-6 rounded-2xl border border-indigo-200 bg-indigo-50 p-6 text-sm leading-7 text-indigo-800 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-200">
        <p class="font-semibold">Prepara tu archivo</p>
        <ol class="mt-2 list-inside list-decimal space-y-1">
            <li>Descarga la plantilla y conserva la columna <strong>Nombre</strong>.</li>
            <li>Escribe una fábrica por fila debajo del encabezado.</li>
            <li>Guarda el archivo como Excel (.xlsx) y selecciónalo aquí.</li>
        </ol>
        <p class="mt-3">Las fábricas existentes, repetidas en el archivo y las filas vacías se omiten. Si una fila tiene errores, no se guarda ningún cambio.</p>
        <a href="{{ route('configuration.factories.template') }}" class="mt-4 inline-flex rounded-xl border border-indigo-200 bg-white px-5 py-2.5 font-semibold transition hover:bg-indigo-100 dark:border-indigo-400/20 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20">Descargar plantilla</a>
    </div>
    <form wire:submit="import" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-8" x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false">
        <label for="factory-file" class="mb-3 block text-sm font-semibold text-slate-700 dark:text-slate-200">Archivo de fábricas *</label>
        <input id="factory-file" type="file" wire:model="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required wire:loading.attr="disabled" wire:target="import" aria-describedby="factory-file-help @error('file') factory-file-error @enderror" class="block w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-100 file:px-4 file:py-2 file:font-semibold file:text-indigo-700 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300 dark:file:bg-indigo-500/15 dark:file:text-indigo-300">
        <p id="factory-file-help" class="mt-3 text-sm text-slate-500 dark:text-slate-400">Formato .xlsx. Tamaño máximo: 10 MB.</p>
        <p wire:loading wire:target="file" role="status" class="mt-3 text-sm text-indigo-600 dark:text-indigo-300">Subiendo archivo...</p>
        @error('file')<p id="factory-file-error" role="alert" class="mt-3 text-sm font-medium text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
        <div class="mt-8 flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-6 dark:border-white/10">
            <a href="{{ route('configuration.factories.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</a>
            <button type="submit" x-bind:disabled="uploading" wire:loading.attr="disabled" wire:target="file,import" class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400 disabled:cursor-wait disabled:opacity-60"><span wire:loading.remove wire:target="import">Importar fábricas</span><span wire:loading wire:target="import">Importando...</span></button>
        </div>
    </form>
</section>
