<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        ]);
    }
}
