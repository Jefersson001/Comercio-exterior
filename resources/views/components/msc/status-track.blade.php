@props(['statuses', 'currentStatus' => null, 'interactive' => false, 'field', 'label', 'caption', 'minimum' => '114px'])
@php($currentIndex = $currentStatus === null ? -1 : array_search($currentStatus, $statuses, true))
<div role="group" aria-label="{{ $label }}" class="w-full min-w-0">
    <div class="overflow-x-auto pb-1">
        <div class="status-track">
            @foreach($statuses as $index => $step)
                @php($active = $currentStatus === $step)
                @if($interactive)
                    <button type="button" wire:key="msc-{{ $field }}-option-{{ $index }}"
                        wire:click="$set('{{ $field }}', '{{ $step->value }}')" wire:loading.attr="disabled"
                        class="status-step" style="min-width: {{ $minimum }}"
                        aria-pressed="{{ $active ? 'true' : 'false' }}" data-complete="{{ $currentIndex > $index ? 'true' : 'false' }}">
                        <span class="status-step-number" aria-hidden="true">{{ $currentIndex > $index ? '✓' : $index + 1 }}</span>
                        {{ $step->value }}
                    </button>
                @else
                    <span wire:key="msc-{{ $field }}-step-{{ $index }}" class="status-step" style="min-width: {{ $minimum }}"
                        data-complete="{{ $currentIndex > $index ? 'true' : 'false' }}" @if($active) aria-current="step" @endif>
                        <span class="status-step-number" aria-hidden="true">{{ $currentIndex > $index ? '✓' : $index + 1 }}</span>
                        {{ $step->value }}
                    </span>
                @endif
            @endforeach
        </div>
    </div>
    @if($interactive)
        <p class="status-caption">{{ $caption }}: <strong>{{ $currentStatus?->value ?? ($field === 'status' ? 'Sin estado' : 'Sin status') }}</strong>. Guarda el embarque para aplicar el cambio.</p>
        @error($field)<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
    @elseif($currentStatus === null)
        <p class="status-caption">{{ $field === 'status' ? 'Sin estado asignado' : 'Sin status BL asignado' }}</p>
    @endif
</div>
