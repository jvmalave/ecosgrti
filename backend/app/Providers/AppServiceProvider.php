<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Policies\RequirementPolicy;
use App\Domains\Core\Models\Requirement;

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
        // Registrar rutas de migraciones DDD
        $this->loadMigrationsFrom([
            database_path('migrations/security'),
            database_path('migrations/catalogs'),
            database_path('migrations/core'),
            database_path('migrations/audit'),
            database_path('migrations/workflow'),
        ]);

        // Vinculamos tu modelo del dominio con su escudo de seguridad
        Gate::policy(Requirement::class, RequirementPolicy::class);
    }
}
