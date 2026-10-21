@props(['entry', 'showSubject' => false])
@php
    $action = match($entry->event) { 'created' => 'Creó el registro', 'deleted' => 'Eliminó el registro', default => 'Modificó el registro' };
    $source = match($entry->source) { 'import' => 'Importación de Excel', 'system' => 'Sistema', default => 'Interfaz' };
@endphp
<article class="record-history-entry" wire:key="history-event-{{ $entry->id }}">
    <span class="record-history-avatar" aria-hidden="true">{{ mb_substr($entry->actor_name, 0, 1) }}</span>
    <div class="min-w-0 grow">
        <div class="flex flex-wrap items-center justify-between gap-2"><strong class="text-sm">{{ $entry->actor_name }}</strong><time class="text-xs text-slate-500 dark:text-slate-400" datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->timezone('America/Caracas')->format('d/m/Y H:i') }} · Venezuela</time></div>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $action }}@if($showSubject) <strong>{{ $entry->subject_name }}</strong> · #{{ $entry->subject_id }}@endif <span class="record-history-source">{{ $source }}</span></p>
        <details class="record-history-details" @if($entry->event === 'updated') open @endif>
            <summary>{{ count($entry->changes) }} {{ count($entry->changes) === 1 ? 'campo registrado' : 'campos registrados' }}</summary>
            <div class="record-history-changes">
                @foreach($entry->changes as $change)
                    <div class="record-history-change">
                        <strong>{{ $change['label'] }}</strong>
                        <div class="record-history-values"><span class="record-history-before"><span class="sr-only">Antes: </span>{{ $change['before'] }}</span><span aria-hidden="true">→</span><span class="record-history-after"><span class="sr-only">Después: </span>{{ $change['after'] }}</span></div>
                    </div>
                @endforeach
            </div>
        </details>
    </div>
</article>
