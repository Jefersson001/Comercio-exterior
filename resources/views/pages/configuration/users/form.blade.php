<section class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Configuración / Usuarios</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ $userId ? 'Editar usuario' : 'Crear usuario' }}</h2>
            <p class="mt-3 leading-7 text-slate-600 dark:text-slate-400">{{ $userId ? 'Actualiza los datos y el acceso de esta cuenta.' : 'Completa los datos para dar acceso al sistema.' }}</p>
        </div>
        <a href="{{ route('configuration.users.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Volver a la lista</a>
    </div>

    <form data-record-form wire:submit="save" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-8">
        <div class="mb-8 border-b border-slate-200 pb-6 dark:border-white/10">
            <p class="text-xs font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300">Información del usuario</p>
            <h3 class="mt-2 text-2xl font-semibold">{{ $userId ? trim($name.' '.$last_name) : 'Nuevo usuario' }}</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Los campos con * son obligatorios.</p>
        </div>

        <fieldset class="grid min-w-0 gap-5 sm:grid-cols-2" wire:loading.attr="disabled" wire:target="save">
            <legend class="sr-only">Datos de la cuenta</legend>
            <x-forms.input name="name" label="Nombre *" wire:model="name" autocomplete="given-name" maxlength="100" required />
            <x-forms.input name="last_name" label="Apellido *" wire:model="last_name" autocomplete="family-name" maxlength="100" required />
            <x-forms.input name="email" label="Correo electrónico *" type="email" wire:model="email" autocomplete="off" maxlength="255" required />
            <div>
                <label for="role" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Rol *</label>
                <x-forms.select id="role" wire:model="role" required :disabled="$userId === auth()->id()" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 disabled:opacity-60 dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    @foreach ($roles as $userRole)<option value="{{ $userRole->value }}">{{ $userRole->label() }}</option>@endforeach
                </x-forms.select>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">El administrador puede crear y administrar usuarios.</p>
                @error('role')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
            </div>
            <x-forms.input name="password" :label="$userId ? 'Nueva contraseña' : 'Contraseña *'" type="password" wire:model="password" autocomplete="new-password" minlength="8" maxlength="255" :required="! $userId" :hint="$userId ? 'Déjala vacía para conservar la contraseña actual.' : 'Mínimo 8 caracteres.'" />
            <x-forms.input name="password_confirmation" :label="$userId ? 'Confirmar nueva contraseña' : 'Confirmar contraseña *'" type="password" wire:model="password_confirmation" autocomplete="new-password" minlength="8" maxlength="255" :required="! $userId" />
            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-3 text-sm font-semibold"><input id="is_active" type="checkbox" wire:model="is_active" @disabled($userId === auth()->id()) class="size-5 rounded border-slate-300 text-indigo-600 disabled:opacity-60">Usuario activo</label>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Una cuenta inactiva no puede acceder al sistema.</p>
                @if ($userId === auth()->id())<p class="mt-2 text-sm text-indigo-600 dark:text-indigo-300">Por seguridad, no puedes desactivar tu cuenta ni cambiar tu propio rol.</p>@endif
                @error('is_active')<p role="alert" class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2 rounded-xl border border-slate-200 p-5 dark:border-white/10">
                <h4 class="font-semibold">Permisos de acceso</h4>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Activa los accesos que tendrá esta cuenta. Configuración permite administrar Productos, Categorías, Fábricas y Buques. Usuarios es exclusivo de administradores. Los administradores conservan acceso completo.</p>
                <div class="permission-grid mt-4 grid gap-3 lg:grid-cols-3">
                    <label class="inline-flex items-center gap-3 text-sm font-semibold"><input type="checkbox" wire:model="access_international_trade" class="size-5 rounded border-slate-300 text-indigo-600">Acceso a Comercio Internacional</label>
                    <label class="inline-flex items-center gap-3 text-sm font-semibold"><input type="checkbox" wire:model="access_foreign_trade" class="size-5 rounded border-slate-300 text-indigo-600">Acceso a Comercio Exterior</label>
                    <label class="inline-flex items-center gap-3 text-sm font-semibold"><input type="checkbox" wire:model="access_configuration" class="size-5 rounded border-slate-300 text-indigo-600">Acceso a Configuración</label>
                </div>
                @error('access_international_trade')<p role="alert" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('access_foreign_trade')<p role="alert" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('access_configuration')<p role="alert" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
        </fieldset>

        <div id="record-actions" data-form-actions class="mt-8 flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-6 dark:border-white/10">
            <a href="{{ route('configuration.users.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400 disabled:opacity-60"><span wire:loading.remove wire:target="save">{{ $userId ? 'Guardar cambios' : 'Crear usuario' }}</span><span wire:loading wire:target="save">Guardando...</span></button>
        </div>
    </form>
</section>
