<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Configuración</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">Usuarios</h2>
            <p class="mt-3 max-w-2xl leading-7 text-slate-600 dark:text-slate-400">Consulta, busca y administra las cuentas con acceso a {{ config('app.name') }}.</p>
        </div>
        <a href="{{ route('configuration.users.create') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-indigo-500 px-5 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Crear usuario
        </a>
    </div>

    @if (session('status'))
        <div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">{{ session('status') }}</div>
    @endif

    <div data-list-panel class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 dark:border-white/10 sm:flex-row sm:items-center">
            <div class="grow">
                <label for="user-search" class="sr-only">Buscar usuarios</label>
                <input id="user-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por nombre, apellido o correo..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-950 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 dark:border-white/10 dark:bg-slate-950/60 dark:text-white">
            </div>
            <div>
                <label for="role-filter" class="sr-only">Filtrar por rol</label>
                <x-forms.select id="role-filter" wire:model.live="role" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none focus:border-indigo-400 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    <option value="">Todos los roles</option>
                    @foreach ($roles as $userRole)<option value="{{ $userRole->value }}">{{ $userRole->label() }}</option>@endforeach
                </x-forms.select>
            </div>
        </div>
        <div class="overflow-x-auto" wire:loading.class="opacity-60" aria-label="Lista de usuarios">
            <table class="w-full min-w-2xl text-left">
                <thead class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-950/40 dark:text-slate-400">
                    <tr><th scope="col" class="px-6 py-4 font-semibold">Usuario</th><th scope="col" class="px-6 py-4 font-semibold">Rol</th><th scope="col" class="px-6 py-4 font-semibold">Estado</th><th scope="col" class="px-6 py-4 font-semibold">Registro</th><th scope="col" class="px-6 py-4 font-semibold">Acciones</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}" data-clickable-row data-href="{{ route('configuration.users.edit', $user) }}" tabindex="0" aria-label="Editar a {{ $user->full_name }}" class="cursor-pointer transition hover:bg-indigo-50/60 focus:bg-indigo-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 dark:hover:bg-indigo-500/5 dark:focus:bg-indigo-500/5">
                            <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ $user->initials() }}</span><div><a href="{{ route('configuration.users.edit', $user) }}" class="font-semibold hover:text-indigo-600 hover:underline focus-visible:underline dark:hover:text-indigo-300">{{ $user->full_name }}</a><p class="text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p></div></div></td>
                            <td class="px-6 py-4"><span @class(['inline-flex rounded-full px-3 py-1 text-xs font-semibold', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => $user->isAdministrator(), 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300' => ! $user->isAdministrator()])>{{ $user->role->label() }}</span></td>
                            <td class="px-6 py-4"><span @class(['inline-flex rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $user->is_active, 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => ! $user->is_active])>{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ $user->created_at?->format('d/m/Y') }}</td>
                            <td class="px-6 py-4"><a href="{{ route('configuration.users.edit', $user) }}" aria-label="Editar a {{ $user->full_name }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Editar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-semibold">No se encontraron usuarios</p><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Prueba con otra búsqueda o crea un nuevo usuario.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4 dark:border-white/10">
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">{{ $users->total() }} {{ $users->total() === 1 ? 'usuario' : 'usuarios' }}</p>
            {{ $users->links() }}
        </div>
    </div>
</section>
