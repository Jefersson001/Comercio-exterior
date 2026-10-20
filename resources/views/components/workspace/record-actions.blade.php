@props(['href', 'label' => 'registro'])
<div class="inline-flex items-center gap-3 whitespace-nowrap">
    <a href="{{ $href }}" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-300">Editar</a>
    <span class="h-4 border-l border-slate-200 dark:border-white/10" aria-hidden="true"></span>
    <a href="{{ $href }}#record-actions" class="inline-flex items-center gap-1.5 text-xs font-medium text-rose-700 hover:underline dark:text-rose-300"
        aria-label="Ir a eliminar {{ $label }}" title="Abrir la ficha y revisar la eliminación de {{ $label }}">
        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg>
        Eliminar…
    </a>
</div>
