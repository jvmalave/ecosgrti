<?php

namespace App\Domains\Reporting\Providers;

use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // No necesitamos registrar bindings en el contenedor por ahora
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registramos la ruta de las vistas encapsuladas de este dominio.
        // A partir de ahora podremos invocarlas como view('reporting::nombre-de-la-vista')
        $this->loadViewsFrom(base_path('app/Domains/Reporting/Views'), 'reporting');
    }
}