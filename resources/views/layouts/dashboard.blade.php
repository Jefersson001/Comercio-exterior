<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="@yield('description', 'Panel principal del sistema.')">
        <title>{{ $title ?? trim(View::yieldContent('title', 'Panel')) }} | {{ config('app.name') }}</title>
        <script>
            try {
                const sidebarPreference = localStorage.getItem('dashboard-sidebar');
                if (sidebarPreference === 'closed' || sidebarPreference === 'open') {
                    document.documentElement.dataset.sidebarState = sidebarPreference;
                }
            } catch {}
            const selectedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', selectedTheme === 'dark' || (! selectedTheme && prefersDark));
        </script>
        <style>
            html[data-sidebar-state="closed"] [data-sidebar] { transform: translateX(-100%) !important; }
            html[data-sidebar-state="closed"] [data-sidebar-overlay] { display: none !important; }
            html[data-sidebar-state="closed"] [data-dashboard-content] { padding-left: 0 !important; }
        </style>
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-950 antialiased transition-colors duration-300 dark:bg-slate-950 dark:text-white">
        <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-slate-950/60 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

        <aside id="dashboard-sidebar" data-sidebar class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col overflow-y-auto border-r border-slate-200 bg-white transition-transform duration-300 ease-out lg:translate-x-0 dark:border-white/10 dark:bg-slate-900">
            <a href="{{ route('dashboard') }}" class="flex h-20 shrink-0 items-center gap-3 border-b border-slate-200 px-6 dark:border-white/10">
                <span class="grid size-10 place-items-center rounded-xl bg-indigo-500 font-semibold text-white shadow-lg shadow-indigo-500/25">C</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold leading-tight tracking-tight">{{ config('app.name') }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Panel administrativo</p>
                </div>
            </a>

            <div class="flex grow flex-col px-4 py-6">
                <p class="px-3 text-xs font-semibold tracking-[0.2em] text-slate-400 uppercase">Menú</p>
                <nav class="mt-4 flex flex-col gap-2" aria-label="Navegación del sistema">
                    <a href="{{ route('dashboard') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => request()->routeIs('dashboard'), 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs('dashboard')])>
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 11.25 9-7.5 9 7.5M5.25 9.375V20.25h13.5V9.375M9.75 20.25v-6h4.5v6"/></svg>
                        Inicio
                    </a>

                    @can('access-international-trade')
                    <details id="international-trade-menu" class="group" @if(request()->routeIs('international-trade.*', 'msc.*')) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a17 17 0 0 1 0 18 17 17 0 0 1 0-18Z"/></svg>
                            <span class="min-w-0 grow">Comercio Internacional</span>
                            <svg class="size-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="mt-2 ml-6 flex flex-col gap-1 border-l border-slate-200 pl-3 dark:border-white/10">
                            @include('partials.navigation.international-trade')
                        </div>
                    </details>

                    @endcan
                    @can('access-foreign-trade')
                    <details id="foreign-trade-menu" class="group" @if(request()->routeIs('foreign-trade.*')) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 5v8l-9 5-9-5V8l9-5Zm0 10v8M3 8l9 5 9-5M7.5 5.5l9 5"/></svg>
                            <span class="min-w-0 grow">Comercio exterior</span>
                            <svg class="size-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="mt-2 ml-6 flex flex-col gap-1 border-l border-slate-200 pl-3 dark:border-white/10">
                            @include('partials.navigation.foreign-trade')
                        </div>
                    </details>

                    @endcan
                    @can('manage-configuration')
                        @php($configurationOpen = request()->routeIs('configuration.*'))
                        <details id="configuration-menu" class="group" @if ($configurationOpen) open @endif>
                            <summary @class(['flex cursor-pointer list-none items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition [&::-webkit-details-marker]:hidden', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => $configurationOpen, 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! $configurationOpen])>
                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5l.75 2.25 2.25.75 2.25-.75 2.25 3.75-1.5 1.75v2.5l1.5 1.75-2.25 3.75-2.25-.75-2.25.75-.75 2.25h-4.5L9 19.5l-2.25-.75-2.25.75-2.25-3.75L3.75 14v-2.5L2.25 9.75 4.5 6l2.25.75L9 6l.75-2.25Z"/><circle cx="12" cy="12.75" r="3"/></svg>
                                <span class="grow">Configuración</span>
                                <svg class="size-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            <div class="mt-2 ml-6 flex flex-col gap-1 border-l border-slate-200 pl-3 dark:border-white/10">
                                @can('manage-users')
                                <a href="{{ route('configuration.users.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => request()->routeIs('configuration.users.*'), 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs('configuration.users.*')]) @if(request()->routeIs('configuration.users.*')) aria-current="page" @endif>Usuarios</a>
                                @endcan
                                @can('manage-products')
                                    <a href="{{ route('configuration.products.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => request()->routeIs('configuration.products.*'), 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs('configuration.products.*')]) @if(request()->routeIs('configuration.products.*')) aria-current="page" @endif>Productos</a>
                                @endcan
                                @can('manage-categories')
                                    <a href="{{ route('configuration.categories.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => request()->routeIs('configuration.categories.*'), 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs('configuration.categories.*')]) @if(request()->routeIs('configuration.categories.*')) aria-current="page" @endif>Categorías</a>
                                @endcan
                                @can('manage-factories')
                                    <a href="{{ route('configuration.factories.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => request()->routeIs('configuration.factories.*'), 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs('configuration.factories.*')]) @if(request()->routeIs('configuration.factories.*')) aria-current="page" @endif>Fábricas</a>
                                @endcan
                                @can('manage-vessels')
                                    <a href="{{ route('configuration.vessels.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => request()->routeIs('configuration.vessels.*'), 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs('configuration.vessels.*')]) @if(request()->routeIs('configuration.vessels.*')) aria-current="page" @endif>Buques</a>
                                @endcan
                            </div>
                        </details>
                    @endcan
                </nav>

                <div class="mt-auto border-t border-slate-200 pt-4 dark:border-white/10">
                    <div class="mb-4 flex items-center gap-3 rounded-2xl bg-slate-100 p-3 dark:bg-white/5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-indigo-100 font-semibold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ auth()->user()->initials() }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ auth()->user()->full_name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3-6 3 3m0 0-3 3m3-3H9"/></svg>
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        <script>
            document.querySelectorAll('[data-sidebar] details[id]').forEach((menu) => {
                const storageKey = 'dashboard-submenu:' + menu.id;
                try {
                    const savedState = localStorage.getItem(storageKey);
                    if (! menu.open && (savedState === 'open' || savedState === 'closed')) {
                        menu.open = savedState === 'open';
                    }
                } catch {}
                menu.addEventListener('toggle', () => {
                    try {
                        localStorage.setItem(storageKey, menu.open ? 'open' : 'closed');
                    } catch {}
                });
            });
        </script>

        <div data-dashboard-content class="min-h-screen transition-[padding] duration-300 lg:pl-72">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/80 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/80">
                <div class="flex h-20 items-center justify-between gap-4 px-6 lg:px-8">
                    <div class="flex items-center gap-4">
                        <button type="button" data-sidebar-toggle aria-controls="dashboard-sidebar" aria-expanded="true" class="grid size-11 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-indigo-300 hover:text-indigo-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                            <span class="sr-only">Abrir o cerrar menú</span>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div><p class="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-300">Sistema</p><h1 class="text-lg font-semibold">{{ $heading ?? trim(View::yieldContent('page-heading', 'Panel principal')) }}</h1></div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                    
                    <button type="button" data-theme-toggle class="grid size-11 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-indigo-300 hover:text-indigo-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                        <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5M12 19.5V21M21 12h-1.5M4.5 12H3m15.364-6.364-1.061 1.061M6.697 17.303l-1.061 1.061m12.728 0-1.061-1.061M6.697 6.697 5.636 5.636M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg>
                        <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                    </button>
                    </div>
                </div>
            </header>
            <main class="px-6 py-10 lg:px-8">@hasSection('content')@yield('content')@else{{ $slot ?? '' }}@endif</main>
        </div>
        @livewireScripts
        @fluxScripts
    </body>
</html>
