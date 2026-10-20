@props([
    'status' => null,
    'interactive' => false,
])

@php
    $currentStatus = $status instanceof \App\Enums\MscBillOfLadingStatus
        ? $status
        : \App\Enums\MscBillOfLadingStatus::tryFrom((string) $status);
    $statuses = \App\Enums\MscBillOfLadingStatus::cases();
    $currentIndex = $currentStatus === null
        ? -1
        : array_search($currentStatus, $statuses, true);
@endphp

<div role="group" aria-label="Status BL" class="w-full overflow-x-auto pb-2">
    <div @class([
        'flex min-w-max flex-nowrap overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900',
        'sm:min-w-full' => $interactive,
    ])>
        @foreach ($statuses as $index => $step)
            @php
                $complete = $currentIndex >= $index;
                $active = $currentStatus === $step;
                $stepClasses = $complete
                    ? 'bg-indigo-600 text-white dark:bg-indigo-400 dark:text-slate-950'
                    : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/5';
            @endphp
            @if ($interactive)
                <button
                    type="button"
                    wire:key="msc-status-bl-option-{{ $index }}"
                    wire:click="$set('status_bl', '{{ $step->value }}')"
                    wire:loading.attr="disabled"
                    aria-pressed="{{ $active ? 'true' : 'false' }}"
                    @class([
                        'min-w-[180px] flex-1 whitespace-nowrap px-3 py-3 text-center text-sm font-semibold transition focus:outline-none focus-visible:z-10 focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-indigo-500/30',
                        'border-l border-slate-200 dark:border-white/10' => ! $loop->first,
                        $stepClasses,
                    ])
                >
                    {{ $step->value }}
                </button>
            @else
                <span
                    wire:key="msc-status-bl-step-{{ $index }}"
                    @class([
                        'min-w-[180px] flex-1 whitespace-nowrap px-3 py-3 text-center text-sm font-semibold',
                        'border-l border-slate-200 dark:border-white/10' => ! $loop->first,
                        $stepClasses,
                    ])
                    @if ($active) aria-current="step" @endif
                >
                    {{ $step->value }}
                </span>
            @endif
        @endforeach
    </div>
    @if ($interactive)
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
            Status BL seleccionado: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $currentStatus?->value ?? 'Sin status' }}</span>. Guarda el embarque para aplicar el cambio.
        </p>
        @error('status_bl')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
    @elseif ($currentStatus === null)
        <p class="mt-2 text-center text-xs text-slate-500 dark:text-slate-400">Sin status BL asignado</p>
    @endif
</div>
