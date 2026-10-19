<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('manage-users', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('manage-products', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('manage-categories', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('manage-factories', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('manage-vessels', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('manage-msc', fn (User $user): bool => $user->is_active);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
