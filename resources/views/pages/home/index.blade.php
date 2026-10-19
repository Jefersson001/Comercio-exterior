@extends('layouts.dashboard')

@section('title', 'Inicio')
@section('page-heading', 'Inicio')

@section('content')
    <section class="mx-auto max-w-6xl">
        <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-10">
            <div class="absolute -top-20 -right-20 size-64 rounded-full bg-indigo-500/10 blur-3xl" aria-hidden="true"></div>
            <div class="relative max-w-2xl">
                <span class="mb-6 grid size-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                </span>
                <p class="mb-3 text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Sesión activa</p>
                <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Bienvenido, {{ auth()->user()->full_name }}.</h2>
                <p class="mt-5 text-lg leading-8 text-slate-600 dark:text-slate-300">Bienvenido a {{ config('app.name') }}. Los módulos que tienes disponibles aparecen en el menú lateral.</p>
                @can('manage-users')
                    <a href="{{ route('configuration.users.index') }}" class="mt-8 inline-flex rounded-xl bg-indigo-500 px-5 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400">Administrar usuarios</a>
                @endcan
            </div>
        </div>
    </section>
@endsection
