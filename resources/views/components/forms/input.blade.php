@props(['name', 'label', 'type' => 'text', 'hint' => null, 'required' => false])

<div class="min-w-0">
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @required($required) @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif {{ $attributes->class(['w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-950/60 dark:text-white']) }}>
    @if ($hint)<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
</div>
